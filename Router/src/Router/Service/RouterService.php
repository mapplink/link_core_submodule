<?php
/**
 * The RouterService is responsible for distributing entities and related associations,
 *   as well as providing notifications to nodes when new data is available.
 * @category Router
 * @package Router\Service
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Router\Service;

use Entity\Entity;
use Entity\Update;
use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Router\Entity\RouterEdge;
use Router\Entity\RouterTransform;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;


class RouterService implements ServiceLocatorAwareInterface
{

    /** @var TableGateway[] $this->cachedTableGateways */
    protected $cachedTableGateways = array();
    /** @var ServiceLocatorInterface $this->serviceLocator */
    protected $serviceLocator;
    /** @var double[][]|NULL $this->timePerTransformationPart */
    protected $timePerTransformationPart = NULL;


    /**
     * Set service locator
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    /**
     * Get service locator
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    /**
     * Return the database adapter to be used to communicate with Entity storage.
     * @return Adapter
     */
    protected function getAdapter()
    {
        return $this->getServiceLocator()->get('zend_db');
    }

    /**
     * Returns a new TableGateway instance for the requested table
     * @param string $table
     * @return TableGateway
     */
    protected function getTableGateway($table)
    {
        if (!isset($this->cachedTableGateways[$table])) {
            $this->cachedTableGateways[$table] = new TableGateway($table, $this->getServiceLocator()->get('zend_db'));
        }

        return $this->cachedTableGateways[$table];
    }

    /**
     * @return double[][] $timePerTransformationPart
     */
    public function getTransformsDetails()
    {
        if (is_null($this->timePerTransformationPart)) {
            $times = array(array());
        }else{
            $times = $this->timePerTransformationPart;
            unset($this->timePerTransformationPart);
        }

        return $times;
    }

    /**
     * Process any Routing Transforms before processing an update.
     * @param Entity $entity The affected Entity
     * @param array $updatedData A key-value array of updated attributes
     * @param int $sourceNodeId The node that performed the original update
     * @param int $type The original update type (CUD)
     * @return array $transformedData
     */
    public function processTransforms(Entity $entity, array $updatedData, $sourceNodeId, $type = Update::TYPE_UPDATE)
    {
        /** @var \Entity\Service\EntityConfigService $entityConfigService */
        $entityConfigService = $this->getServiceLocator()->get('entityConfigService');

        $transformedData = array();
        $affectedAttributeIds = array();

        foreach (array_keys($updatedData) as $attributeCode) {
            if ($type != Update::TYPE_UPDATE || $updatedData[$attributeCode] !== $entity->getData($attributeCode)) {
                $affectedAttributeIds[] = $entityConfigService->parseAttribute($attributeCode, $entity->getType());
            }
        }

        $this->timePerTransformationPart = array();
        if (count($affectedAttributeIds)) {
            /** @var \Router\Transform\TransformFactory $transformFactory */
            $transformFactory = $this->getServiceLocator()->get('transformFactory');
            /** @var \Router\Entity\RouterTransform[] $transformEntities */
            $transformEntities = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')
                ->getRepository('Router\Entity\RouterTransform')
                ->getApplicableTransforms($entity->getType(), $affectedAttributeIds, $type);

            /** @var \Router\Transform\AbstractTransform[] $transforms */
            $transforms = array();
            foreach ($transformEntities as $transformEntity) {
                $startTransform = microtime(TRUE);
                $code = $transformEntity->getTransformType();

                if (!isset($transforms[$code])) {
                    $this->timePerTransformationPart[$code] = array();
                    $transforms[$code] = $transformFactory->getTransform($transformEntity);
                }

                $transform = $transforms[$code];
                $fullTransformClass = get_class($transform);
                $transformClassArray = explode('\\', $fullTransformClass);

                $logLevel = LogService::LEVEL_INFO;
                $logCode = 'trans_';
                $logMessagePrefix = 'processTransforms: ';
                $logMessage = '';
                $logMessageSuffix = ' from node '.$sourceNodeId.' on entity '.$entity->getId().' ('
                    .$entity->getUniqueId().') with transform '.$transformEntity->getTransformId();
                $logData = array(
                    'tfid'=>$transformEntity->getTransformid(),
                    'type'=>$transformEntity->getTransformType(),
                    'attributes'=>$affectedAttributeIds
                );
                $logEntities = array('entity'=>$entity, 'node'=>$sourceNodeId);

                if (!$transform) {
                    $logLevel = LogService::LEVEL_ERROR;
                    $logCode .= 'ivld';
                    $logMessage = 'invalid transform or error creating';
                    $logMessageSuffix .= ' (type: '.$transformEntity->getTransformType().')';
                }else{
                    $logCode .= strtolower(substr(end($transformClassArray), 0, 4));
                    $logMessageSuffix .= ' (class: '.$fullTransformClass.')';

                    if (!$this->checkFiltersTransform($entity, $transformEntity, $type, $updatedData)) {
                        $logCode .= '_skip';
                        $logMessage = 'rejected by filter';
                    }elseif ($transform->init($entity, $sourceNodeId, $transformEntity, $updatedData)){
                        $this->getServiceLocator()->get('logService')->log($logLevel, $logCode,
                            $logMessagePrefix.$logMessage.$logMessageSuffix, $logData, $logEntities);
                        $data = $transform->apply();
                        if ($data && count($data)) {
                            $transformedData = array_merge($transformedData, $data);
                        }
                        $logLevel = LogService::LEVEL_DEBUGEXTRA;
                        $logCode .= '_apply';
                    }else{
                        $logLevel = LogService::LEVEL_WARN;
                        $logCode .= 'ignore';
                    }
                }

                $this->getServiceLocator()->get('logService')
                    ->log($logLevel, $logCode, $logMessagePrefix.$logMessage.$logMessageSuffix, $logData, $logEntities);
/* ToDo (maybe): automate switch depending on the transformed being shared or not
                $timePerTransformationPart = $transform->getTransformationPartTimes();
                foreach ($timePerTransformationPart as $part=>$time) {
                    if (!array_key_exists($part, $this->timePerTransformationPart[$code])) {
                        $this->timePerTransformationPart[$code][$part] = $time;
                    }else{
                        $this->timePerTransformationPart[$code][$part] += $time;
                    }
                }
*/
                $this->timePerTransformationPart[$code]['total'] = microtime(TRUE) - $startTransform;
            }
        }

        return $transformedData;
    }

    /**
     * Records an entity update, including all audit/update tables needed for the update to be processed.
     * @param Entity $entity The affected Entity
     * @param string $attributes A key-value array of updated attributes
     * @param int $sourceNodeId The node that performed the original update
     * @param int $type The original update type (CUD)
     * @throws MagelinkException If invalid data is passed
     */
    public function distributeUpdate(Entity $entity, $attributes, $sourceNodeId, $type=Update::TYPE_UPDATE)
    {
        /** @var \Router\Entity\RouterEdge[] $edges */
        $edges = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')
            ->getRepository('Router\Entity\RouterEdge')
            ->getAssignedEdges($sourceNodeId, $entity->getType(), $type);

        /** @var \Node\Entity\Node[] $affectedNodes */
        $affectedNodes = array();
        $affectedNodeIds = array();

        $sql = "UPDATE router_stat_node SET `count_from` = `count_from` + 1 WHERE node_id = ".$sourceNodeId.";";
        $this->getAdapter()->query($sql, Adapter::QUERY_MODE_EXECUTE);

        if($edges && count($edges)){
            foreach($edges as $edge){
                if(!$this->checkFiltersEdge($entity, $edge, $type, $attributes)){
                    // Some filter blocked, skip.
                    continue;
                }

                $affectedNodes[] = $edge->getNodeTo();
                $affectedNodeIds[] = $edge->getNodeTo()->getId();

                $sql = "UPDATE router_stat_edge SET `count` = `count` + 1 WHERE edge_id = ".$edge->getEdgeId().";";
                $this->getAdapter()->query($sql, Adapter::QUERY_MODE_EXECUTE);
                $sql = "UPDATE router_stat_node SET `count_to` = `count_to` + 1 WHERE node_id = "
                    .$edge->getNodeTo()->getId().";";
                $this->getAdapter()->query($sql, Adapter::QUERY_MODE_EXECUTE);
            }
        }

        $message = 'distributeUpdate - from '.$sourceNodeId.' - '.$entity->getId().' ('.$entity->getTypeStr().') - '
            .count($affectedNodes).' nodes';
        $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_INFO,
            'distup',
            $message,
            array('nodes'=>$affectedNodeIds, 'attributes'=>array_keys($attributes)),
            array('entity'=>$entity, 'node'=>$sourceNodeId)
        );

        $affectedRows = $this->getTableGateway('entity_update_log')->insert(array(
            'entity_id'=>$entity->getId(),
            'entity_type'=>$entity->getType(),
            // 'timestamp'=>'', should be auto generated
            'source_node'=>$sourceNodeId,
            'affected_nodes'=>implode(',', $affectedNodeIds),
            'affected_attributes'=>implode(',', array_keys($attributes)),
            'type'=>$type,
        ));

        $logId = $this->getAdapter()->getDriver()->getLastGeneratedValue();
        if (!$affectedRows || !$logId){
            throw new MagelinkException('Error recording update to entity ' . $entity->getId());
        }else{
            foreach ($affectedNodes as $node) {
                try{
                    $affectedRows = $this->getTableGateway('entity_update')->insert(
                        array(
                            'entity_id'=>$entity->getId(),
                            'node_id'=>$node->getId(),
                            'log_id'=>$logId,
                            'type'=>$type,
                            'complete'=>0,
                        )
                    );

                    if (!$affectedRows) {
                        $this->getServiceLocator()->get('logService')
                            ->log(
                                LogService::LEVEL_ERROR,
                                'distup_err',
                                'distributeUpdate had error while inserting update entries (unknown)',
                                array('log id'=>$logId, 'type'=>'unknown'),
                                array('entity'=>$entity, 'node'=>$sourceNodeId)
                            );
                    }
                }catch (\Exception $exception){
                    $this->getServiceLocator()->get('logService')
                        ->log(
                            LogService::LEVEL_ERROR,
                            'distup_err',
                            'distributeUpdate had error while inserting update entries (ex)',
                            array('log id'=>$logId, 'type'=>'ex'),
                            array('entity'=>$entity, 'node'=>$sourceNodeId, 'exception'=>$exception)
                        );
                }
            }
        }
    }

    /**
     * Records an entity action, including all audit/update tables needed for the action to be processed.
     * @param Entity $entity The affected Entity
     * @param int $sourceNodeId The node that performed the original action
     * @param string $actionType The type of action being performed
     * @param array $action_data Key-value data required to perform this action
     * @return bool $response indicator, if the action was successful
     * @throws \Magelink\Exception\MagelinkException
     */
    public function distributeAction(Entity $entity, $sourceNodeId, $actionType, array $action_data = array())
    {
        /** @var \Router\Entity\RouterEdge[] $edges */
        $edges = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')
            ->getRepository('Router\Entity\RouterEdge')
            ->getAssignedEdges($sourceNodeId, $entity->getType(), Update::TYPE_ACTION);

        $affectedNodeIds = array();

        $sql = "UPDATE router_stat_node SET `count_from` = `count_from` + 1 WHERE node_id = ".$sourceNodeId.";";
        $this->getAdapter()->query($sql, Adapter::QUERY_MODE_EXECUTE);

        foreach ($edges as $edge) {
            if(!$this->checkFiltersEdge($entity, $edge, Update::TYPE_ACTION, array())){
                // Some filter blocked, skip.
                continue;
            }
            $affectedNodeIds[] = $edge->getNodeTo()->getId();
            $sql = "UPDATE router_stat_edge SET `count` = `count` + 1 WHERE edge_id = ".$edge->getEdgeId().";";
            $this->getAdapter()->query($sql, Adapter::QUERY_MODE_EXECUTE);
            $sql = "UPDATE router_stat_node SET `count_to` = `count_to` + 1 WHERE node_id = "
                .$edge->getNodeTo()->getId().";";
            $this->getAdapter()->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }

        $action = new \Entity\Entity\EntityAction();
        $action->setEntityId($entity->getId());
        $action->setActionType($actionType);
        $action->setAllSimpleData($action_data);

        $doctrine = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $doctrine->persist($action);
        $doctrine->flush($action);

        $actionId = $action->getId();
        $action->saveSimpleData();

        if (!$action || !$actionId) {
            throw new MagelinkException('Error recording action to entity ' . $entity->getId());
        }

        $response = NULL;
        foreach($affectedNodeIds as $node){
            try{
                $response = $this->getTableGateway('entity_action_status')->insert(array(
                    'action_id'=>$actionId,
                    'node_id'=>$node,
                    'status'=>0,
                ));
                if (!$response) {
                    $this->getServiceLocator()->get('logService')
                        ->log(LogService::LEVEL_ERROR,
                            'distact_err',
                            'distributeAction had error while inserting update entries (unknown)',
                            array('aid'=>$actionId, 'type'=>'unknown'),
                            array('entity'=>$entity, 'node'=>$sourceNodeId)
                        );
                }
            }catch (\Exception $exception) {
                $this->getServiceLocator()->get('logService')
                    ->log(LogService::LEVEL_ERROR,
                        'distact_err',
                        'distributeAction had error while inserting update entries (ex)',
                        array('action id'=>$actionId, 'type'=>'ex'),
                        array('entity'=>$entity, 'node'=>$sourceNodeId, 'exception'=>$exception)
                    );
            }
        }

        return (bool) $response;
    }

    /**
     * Check all assigned filters for the provided edge.
     * @param Entity $entity The entity being affected
     * @param RouterEdge $edge The edge to check for
     * @param string $actionType The action type (CUDA)
     * @param array $newData A key-value array of changed attributes and their new values
     * @return bool Whether this has met filters
     */
    public function checkFiltersEdge(Entity $entity, RouterEdge $edge, $actionType, array $newData)
    {
        /** @var \Router\Filter\FilterFactory $factory */
        $factory = $this->getServiceLocator()->get('filterFactory');

        $filters = $this->loadFiltersEdge($edge);
        foreach ($filters as $filterEntity) {
            $filter = $factory->getFilter($filterEntity);
            $filter->init($entity, $filterEntity, $actionType);
            if (!$filter->checkEdge($edge->getNodeFrom(), $edge->getNodeTo(), $newData)) {
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * Check all assigned filters for the provided transform.
     * @param Entity $entity The entity being affected
     * @param RouterTransform $transform The transform to check for
     * @param $actionType The action type (CUD)
     * @param $newData A key-value array of changed attributes and their new values
     * @return bool Whether this has met filters
     */
    public function checkFiltersTransform(Entity $entity, RouterTransform $transform, $actionType, $newData)
    {
        /** @var \Router\Filter\FilterFactory $factory */
        $factory = $this->getServiceLocator()->get('filterFactory');

        $filters = $this->loadFiltersTransform($transform);
        foreach ($filters as $filterEntity) {
            $filter = $factory->getFilter($filterEntity);
            $filter->init($entity, $filterEntity, $actionType);

            if (!$filter->checkTransform($transform->getSrcAttribute(), $transform->getDestAttribute(), $newData)) {
                return FALSE;
            }
        }

        return TRUE;

    }

    /**
     * Return all Filter entities assigned to the given Edge
     * @param \Router\Entity\RouterEdge $edge
     * @return array
     */
    protected function loadFiltersEdge(\Router\Entity\RouterEdge $edge)
    {
        $rows = $this->getTableGateway('router_edge_filter')->select(array(
            'edge_id'=>$edge->getEdgeId(),
            'enabled'=>1,
        ));

        /** @var \Router\Repository\RouterFilterRepository $repository */
        $repository = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')
            ->getRepository('Router\Entity\RouterFilter');

        $edgeFilter = array();
        foreach($rows as $row){
            $edgeFilter[] = $repository->find($row['filter_id']);
        }

        return $edgeFilter;
    }

    /**
     * Return all Filter entities assigned to the given Transform
     * @param RouterTransform $transform
     * @return array
     */
    protected function loadFiltersTransform(RouterTransform $transform)
    {
        $rows = $this->getTableGateway('router_transform_filter')
            ->select(array('transform_id'=>$transform->getTransformId(), 'enabled'=>1));

        /** @var \Router\Repository\RouterFilterRepository $repository */
        $repository = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')
            ->getRepository('Router\Entity\RouterFilter');

        $transformFilter = array();
        foreach($rows as $row){
            $transformFilter[] = $repository->find($row['filter_id']);
        }

        return $transformFilter;
    }

}

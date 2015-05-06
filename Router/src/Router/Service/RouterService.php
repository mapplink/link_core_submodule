<?php
/**
 * The RouterService is responsible for distributing entities and related associations, as well as providing notifications to nodes when new data is available
 * @category Router
 * @package Router\Service
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2015 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Router\Service;

use Entity\Entity;
use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Router\Transform\AbstractTransform;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


class RouterService implements ServiceLocatorAwareInterface
{

    /**
     * Process any Routing Transforms before processing an update.
     * @param Entity $entity The affected Entity
     * @param array $updated_data A key-value array of updated attributes
     * @param int $sourceNodeId The node that performed the original update
     * @param int $type The original update type (CUD)
     * @return array
     */
    public function processTransforms(Entity $entity, $updated_data, $sourceNodeId, $type=\Entity\Update::TYPE_UPDATE)
    {
        /** @var \Entity\Service\EntityConfigService $entityConfigService */
        $entityConfigService = $this->getServiceLocator()->get('entityConfigService');

        $affectedAttributeIds = array();
        foreach (array_keys($updated_data) as $attributeCode) {
            if($type == \Entity\Update::TYPE_UPDATE && $updated_data[$attributeCode] === $entity->getData($attributeCode)){
                // Skip
                continue;
            }
            $affectedAttributeIds[] = $entityConfigService->parseAttribute($att, $entity->getType());
        }

        if(!count($affectedAttributeIds)){
            return array();
        }

        $returnData = array();

        /** @var \Router\Transform\TransformFactory $transformFactory */
        $transformFactory = $this->getServiceLocator()->get('transformFactory');
        /** @var \Router\Entity\RouterTransform[] $transforms */
        $transforms = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')
            ->getRepository('Router\Entity\RouterTransform')
            ->getApplicableTransforms($entity->getType(), $affectedAttributeIds, $type);

        foreach ($transforms as $transformEntity) {
            /** @var AbstractTransform $transform */
            $transform = $transformFactory->getTransform($transformEntity);

            $logLevel = LogService::LEVEL_INFO;
            $logCode = 'trans_';
            $logMessagePrefix = 'processTransforms: ';
            $logMessage = '';
            $logMessageSuffix = ' from node '.$sourceNodeId.' on entity '.$entity->getId();
                .' with transform '.$transformEntity->getTransformId();
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
                $logCode .= substr(get_class($transform), 0, 4);
                $logMessageSuffix .= ' (class: '.get_class($transform).')';
                if (!$this->checkFiltersTransform($entity, $transformEntity, $type, $updated_data)) {
                    $logCode .= '_skip';
                    $logMessage = 'rejected by filter';
                }elseif ($transform->init($entity, $sourceNodeId, $transformEntity, $updated_data)){
                    $this->getServiceLocator()->get('logService')
                        ->log($logLevel, $logCode, $logMessagePrefix.$logMessage.$logMessageSuffix, $logData, $logEntities);
                    $data = $transform->apply();
                    if ($data && count($data)) {
                        $returnData = array_merge($returnData, $data);
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
        }

        return $returnData;
    }

    /**
     * Records an entity update, including all audit/update tables needed for the update to be processed.
     * @param Entity $entity The affected Entity
     * @param string $attributes A key-value array of updated attributes
     * @param int $source_node_id The node that performed the original update
     * @param int $type The original update type (CUD)
     * @throws MagelinkException If invalid data is passed
     */
    public function distributeUpdate(Entity $entity, $attributes, $source_node_id, $type=\Entity\Update::TYPE_UPDATE){
        /** @var \Router\Entity\RouterEdge[] $edges */
        $edges = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')->getRepository('Router\Entity\RouterEdge')->getAssignedEdges($source_node_id, $entity->getType(), $type);

        /** @var \Node\Entity\Node[] $affectedNodes */
        $affectedNodes = array();
        $affectedNodeIds = array();

        $this->getAdapter()->query('UPDATE router_stat_node SET `count_from` = `count_from` + 1 WHERE node_id = ' . $source_node_id . ';', \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);

        if($edges && count($edges)){
            foreach($edges as $edge){
                if(!$this->checkFiltersEdge($entity, $edge, $type, $attributes)){
                    // Some filter blocked, skip.
                    continue;
                }
                $affectedNodes[] = $edge->getNodeTo();
                $affectedNodeIds[] = $edge->getNodeTo()->getId();
                $this->getAdapter()->query('UPDATE router_stat_edge SET `count` = `count` + 1 WHERE edge_id = ' . $edge->getEdgeId() . ';', \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
                $this->getAdapter()->query('UPDATE router_stat_node SET `count_to` = `count_to` + 1 WHERE node_id = ' . $edge->getNodeTo()->getId() . ';', \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
            }
        }

        $message = 'distributeUpdate - from '.$source_node_id.' - '.$entity->getId().' ('.$entity->getTypeStr().') - '
            .count($affectedNodes).' nodes';
        $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_INFO,
            'distup',
            $message,
            array('nodes'=>$affectedNodeIds, 'attributes'=>array_keys($attributes)),
            array('entity'=>$entity, 'node'=>$source_node_id)
        );

        $res = $this->getTableGateway('entity_update_log')->insert(array(
            'entity_id'=>$entity->getId(),
            'entity_type'=>$entity->getType(),
            // 'timestamp'=>'', should be auto generated
            'source_node'=>$source_node_id,
            'affected_nodes'=>implode(',', $affectedNodeIds),
            'affected_attributes'=>implode(',', array_keys($attributes)),
            'type'=>$type,
        ));
        $uid = $this->getAdapter()->getDriver()->getLastGeneratedValue();
        if(!$res || !$uid){
            throw new MagelinkException('Error recording update to entity ' . $entity->getId());
        }

        foreach($affectedNodes as $node){
            try{
                $res = $this->getTableGateway('entity_update')->insert(array(
                    'entity_id'=>$entity->getId(),
                    'node_id'=>$node->getId(),
                    'log_id'=>$uid,
                    'type'=>$type,
                    'complete'=>0,
                ));
                if(!$res){
                    $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_ERROR, 'distup_err', 'distributeUpdate had error while inserting update entries (unknown)', array('uid'=>$uid, 'type'=>'unknown'), array('entity'=>$entity, 'node'=>$source_node_id));
                }
            }catch(\Exception $e){
                $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_ERROR, 'distup_err', 'distributeUpdate had error while inserting update entries (ex)', array('uid'=>$uid, 'type'=>'ex'), array('entity'=>$entity, 'node'=>$source_node_id, 'exception'=>$e));
            }
        }
    }

    /**
     * Records an entity action, including all audit/update tables needed for the action to be processed.
     * @param Entity $entity The affected Entity
     * @param int $source_node_id The node that performed the original action
     * @param string $action_type The type of action being performed
     * @param array $action_data Key-value data required to perform this action
     * @return bool $res indicator, if the action was successful
     * @throws \Magelink\Exception\MagelinkException
     */
    public function distributeAction(Entity $entity, $source_node_id, $action_type, $action_data=array()){
        /** @var \Router\Entity\RouterEdge[] $edges */
        $edges = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')->getRepository('Router\Entity\RouterEdge')->getAssignedEdges($source_node_id, $entity->getType(), \Entity\Update::TYPE_ACTION);

        $affectedNodeIds = array();

        $this->getAdapter()->query('UPDATE router_stat_node SET `count_from` = `count_from` + 1 WHERE node_id = ' . $source_node_id . ';', \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);

        foreach($edges as $edge){
            if(!$this->checkFiltersEdge($entity, $edge, \Entity\Update::TYPE_ACTION, array())){
                // Some filter blocked, skip.
                continue;
            }
            $affectedNodeIds[] = $edge->getNodeTo()->getId();
            $this->getAdapter()->query('UPDATE router_stat_edge SET `count` = `count` + 1 WHERE edge_id = ' . $edge->getEdgeId() . ';', \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
            $this->getAdapter()->query('UPDATE router_stat_node SET `count_to` = `count_to` + 1 WHERE node_id = ' . $edge->getNodeTo()->getId() . ';', \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        }

        $act = new \Entity\Entity\EntityAction();
        $act->setEntityId($entity->getId());
        $act->setActionType($action_type);
        $act->setAllSimpleData($action_data);
        $doctrine = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $doctrine->persist($act);
        $doctrine->flush($act);
        $aid = $act->getId();
        $act->saveSimpleData();

        if(!$act || !$aid){
            throw new MagelinkException('Error recording action to entity ' . $entity->getId());
        }

        $res = NULL;
        foreach($affectedNodeIds as $node){
            try{
                $res = $this->getTableGateway('entity_action_status')->insert(array(
                    'action_id'=>$aid,
                    'node_id'=>$node,
                    'status'=>0,
                ));
                if(!$res){
                    $this->getServiceLocator()->get('logService')
                        ->log(LogService::LEVEL_ERROR, 'distact_err', 'distributeAction had error while inserting update entries (unknown)', array('aid'=>$aid, 'type'=>'unknown'), array('entity'=>$entity, 'node'=>$source_node_id));
                }
            }catch(\Exception $e){
                $this->getServiceLocator()->get('logService')
                    ->log(LogService::LEVEL_ERROR, 'distact_err', 'distributeAction had error while inserting update entries (ex)', array('aid'=>$aid, 'type'=>'ex'), array('entity'=>$entity, 'node'=>$source_node_id, 'exception'=>$e));
            }
        }

        return (bool) $res;
    }

    /**
     * Check all assigned filters for the provided edge.
     * @param Entity $e The entity being affected
     * @param \Router\Entity\RouterEdge $edge The edge to check for
     * @param $action_type The action type (CUDA)
     * @param $newData A key-value array of changed attributes and their new values
     * @return bool Whether this has met filters
     */
    public function checkFiltersEdge(\Entity\Entity $e, \Router\Entity\RouterEdge $edge, $action_type, $newData){
        /** @var \Router\Filter\FilterFactory $factory */
        $factory = $this->getServiceLocator()->get('filterFactory');
        $filters = $this->loadFiltersEdge($edge);
        foreach($filters as $filtEnt){
            $filt = $factory->getFilter($filtEnt);
            $filt->init($e, $filtEnt, $action_type);
            if(!$filt->checkEdge($edge->getNodeFrom(), $edge->getNodeTo(), $newData)){
                return false;
            }
        }
        return true;
    }

    /**
     * Check all assigned filters for the provided transform.
     * @param Entity $e The entity being affected
     * @param \Router\Entity\RouterTransform $transform The transform to check for
     * @param $action_type The action type (CUD)
     * @param $newData A key-value array of changed attributes and their new values
     * @return bool Whether this has met filters
     */
    public function checkFiltersTransform(\Entity\Entity $e, \Router\Entity\RouterTransform $transform, $action_type, $newData){
        /** @var \Router\Filter\FilterFactory $factory */
        $factory = $this->getServiceLocator()->get('filterFactory');
        $filters = $this->loadFiltersTransform($transform);
        foreach($filters as $filtEnt){
            $filt = $factory->getFilter($filtEnt);
            $filt->init($e, $filtEnt, $action_type);
            if(!$filt->checkTransform($transform->getSrcAttribute(), $transform->getDestAttribute(), $newData)){
                return false;
            }
        }
        return true;

    }

    /**
     * Return all Filter entities assigned to the given Edge
     * @param \Router\Entity\RouterEdge $edge
     * @return array
     */
    protected function loadFiltersEdge(\Router\Entity\RouterEdge $edge){

        $rows = $this->getTableGateway('router_edge_filter')->select(array(
            'edge_id'=>$edge->getEdgeId(),
            'enabled'=>1,
        ));

        /** @var \Router\Repository\RouterFilterRepository $repository */
        $repository = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')->getRepository('Router\Entity\RouterFilter');

        $ret = array();
        foreach($rows as $row){
            $ret[] = $repository->find($row['filter_id']);
        }
        return $ret;
    }

    /**
     * Return all Filter entities assigned to the given Transform
     * @param \Router\Entity\RouterTransform $tf
     * @return array
     */
    protected function loadFiltersTransform(\Router\Entity\RouterTransform $tf){

        $rows = $this->getTableGateway('router_transform_filter')->select(array(
            'transform_id'=>$tf->getTransformId(),
            'enabled'=>1,
        ));

        /** @var \Router\Repository\RouterFilterRepository $repository */
        $repository = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')->getRepository('Router\Entity\RouterFilter');

        $ret = array();
        foreach($rows as $row){
            $ret[] = $repository->find($row['filter_id']);
        }
        return $ret;
    }



    /**
     * Return the database adapter to be used to communicate with Entity storage.
     * @return \Zend\Db\Adapter\Adapter
     */
    protected function getAdapter(){
        return $this->getServiceLocator()->get('zend_db');
    }

    /**
     * Cache of preloaded table gateways
     * @var TableGateway[]
     */
    protected $_tgCache = array();

    /**
     * Returns a new TableGateway instance for the requested table
     * @param string $table
     * @return \Zend\Db\TableGateway\TableGateway
     */
    protected function getTableGateway($table){
        if(isset($this->_tgCache[$table])){
            return $this->_tgCache[$table];
        }
        $this->_tgCache[$table] = new TableGateway($table, $this->getServiceLocator()->get('zend_db'));
        return $this->_tgCache[$table];
    }

    /**
     * @var ServiceLocatorInterface The service locator
     */
    protected $_serviceLocator;

    /**
     * Set service locator
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->_serviceLocator = $serviceLocator;
    }

    /**
     * Get service locator
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->_serviceLocator;
    }

}
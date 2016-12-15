<?php
/**
 * Responsible for providing node_status data (i.e. updated timestamps), attribute/node assignments
 *   and locating/updating pending updates and actions.
 * @category Node
 * @package Node\Service
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Node\Service;

use Entity\Service\EntityService;
use Entity\Action;
use Entity\Update;
use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Node\AbstractNode;
use Node\Entity\Node;
use Node\Entity\NodeStatus;
use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


class NodeService implements ServiceLocatorAwareInterface
{

    const MAX_UPDATE_ROWS_BEING_PROCESSED = 50000;

    protected $_subscribedAttributeCodeCache = array();
    protected $_subscribedUpdateAttributeCodeCache = array();

    /** @var TableGateway[]  Cache of preloaded table gateways */
    protected $_tgCache = array();

    /** @var ServiceLocatorAwareInterface $_serviceLocator */
    protected $_serviceLocator;


    /**
     * Set service locator
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->_serviceLocator = $serviceLocator;
    }

    /**
     * Get service locator
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->_serviceLocator;
    }

    /**
     * Returns a new TableGateway instance for the requested table
     * @param string $table
     * @return \Zend\Db\TableGateway\TableGateway
     */
    protected function getTableGateway($table)
    {
        if (!isset($this->_tgCache[$table])) {
            $this->_tgCache[$table] = new TableGateway($table, $this->getServiceLocator()->get('zend_db'));
        }

        return $this->_tgCache[$table];
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
     * Return an array of all currently active node entities
     * @return Node[]
     */
    public function getActiveNodes()
    {
        return $this->getServiceLocator()
            ->get('Doctrine\ORM\EntityManager')
            ->getRepository('Node\Entity\Node')
            ->getActiveNodes();
    }
    /**
     * Return an array of all currently active node entities of a specified type
     * @param string $type
     * @return Node[]
     */
    public function getActiveNodesByType($type)
    {
        return $this->getServiceLocator()
            ->get('Doctrine\ORM\EntityManager')
            ->getRepository('Node\Entity\Node')
            ->getActiveNodesByType($type);
    }

    /**
     * Return all node IDs with the given type
     * @param string $type
     * @return array $nodeIds
     */
    public function getNodesByType($type)
    {
        $response = $this->getTableGateway('node')->select(array('type'=>$type));
        $nodeIds = array();
        foreach ($response as $row) {
            $nodeIds[] = $row['node_id'];
        }

        return $nodeIds;
    }

    /**
     * Get all pending updates for the given node
     * @param Node $nodeEntity
     * @throws MagelinkException If there is invalid data
     * @return Update[]
     */
    public function getPendingUpdates(Node $nodeEntity)
    {
        $startMethod = microtime(TRUE);

        /** @var LogService $logService */
        $logService = $this->getServiceLocator()->get('logService');

        $logCode = 'nod_svc_pupd';
        $logData = array('node id'=>$nodeEntity->getId());
        $logMessage = 'NodeService->getPendingUpdates() started at '.date('d/m H:i:s').'.';
        $logService->log(LogService::LEVEL_INFO, $logCode, $logMessage, $logData);

        $sqlSelect = $this->getTableGateway('entity_update')->getSql()->select()
            ->columns(array('update_id', 'entity_id', 'log_id'))
            ->join('entity_update_log', 'entity_update_log.log_id = entity_update.log_id',
                array('type', 'timestamp', 'source_node', 'affected_nodes', 'affected_attributes'), 'left')
            ->where(array('node_id'=>$nodeEntity->getId(), 'complete'=>0))
            ->order('update_id ASC')
            ->limit(self::MAX_UPDATE_ROWS_BEING_PROCESSED);
        $updateRowSet = $this->getTableGateway('entity_update')->selectWith($sqlSelect);

        $updates = $entities = array();
        $selectTime = -$startMethod + ($start = microtime(TRUE));

        foreach ($updateRowSet as $row) {
            if (!isset($entities[$row['entity_id']])) {
                $entities[$row['entity_id']] = $this->getServiceLocator()->get('entityService')
                    ->loadEntityId($nodeEntity->getId(), $row['entity_id']);
            }

            try{
                $update = new Update();
                $update->init($entities[$row['entity_id']], (array) $row);
                $updates[] = $update;
            }catch (\Exception $exception) {
                $logService->log(LogService::LEVEL_ERROR, $logCode.'_err', $exception->getMessage(), $row);
            }
        }

        unset($entities);

        $methodRuntime = round(microtime(TRUE) - $startMethod, 1);
        $loopRuntime = round(microtime(TRUE) - $start, 1);
        $perUpdate = count($updates) ? round($loopRuntime / count($updates), 4) : FALSE;

        $logMessage = 'NodeService->pendingUpdates() finished at '.date('m/d H:i:s').' and took '.$methodRuntime.'s.'
            .' Entity_update query took '.round($selectTime, 1).'s, the update creation loop '
            .round($loopRuntime, 1).'s'.($perUpdate ? ' ('.count($updates).' x '.$perUpdate.'s per each).' : '.');
        $logData = array('method runtime'=>$methodRuntime, 'loop runtime'=>$loopRuntime, 'per each'=>$perUpdate);
        $logService->log(LogService::LEVEL_DEBUGINTERNAL, $logCode.'_end', $logMessage, $logData);

        return $updates;
    }

    /**
     * Get all pending actions for the given node
     * @param Node $nodeEntity
     * @throws MagelinkException If there is invalid data
     * @return Action[]
     */
    public function getPendingActions(Node $nodeEntity)
    {
        $startMethod = microtime(TRUE);

        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        /** @var EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');
        /** @var LogService $logService */
        $logService = $this->getServiceLocator()->get('logService');

        $logCode = 'nod_svc_pen_act';
        $logData = array();
        $logMessage = 'NodeService->getPendingActions() started at '.date('d/m H:i:s').'.';
        $logService->log(LogService::LEVEL_INFO, $logCode, $logMessage, $logData);

        $response = $this->getTableGateway('entity_action_status')
            ->select(array('node_id'=>$nodeEntity->getId(), 'status'=>0));

        $actions = array();
        $loadTime = $createTime = 0;
        $startTimestamp = $start = microtime(TRUE);

        foreach ($response as $row) {
            /** @var \Entity\Entity\EntityAction $entityAction */
            $entityAction = $entityManager->find('Entity\Entity\EntityAction', $row['action_id']);

            if ($entityAction === FALSE|| !($entityAction instanceof \Entity\Entity\EntityAction)) {
                throw new MagelinkException('Could not find action entry for action '.$row['action_id']);
            }

            $entityAction->loadSimpleData();
            $entity = $this->getServiceLocator()->get('entityService')
                ->loadEntityId($nodeEntity->getId(), $entityAction->getEntityId());

            $loadTime += -$start + ($start = microtime(TRUE));

            $action = new Action();
            $action->init($entityAction->getId(), $entity, $entityAction->getActionType(), $entityAction->getSimpleData());
            $actions[] = $action;

            $createTime += -$start + ($start = microtime(TRUE));
        }

        $methodRuntime = round(microtime(TRUE) - $startMethod, 1);
        $loopRuntime = round(microtime(TRUE) - $startTimestamp, 1);
        $perAction = count($actions) ? round($loopRuntime / count($actions), 4) : FALSE;

        $logMessage = 'NodeService->pendingActions() finished at '.date('m/d H:i:s').' and took '.$methodRuntime.'s.'
            .' Entity_actions_status loop took '.$loopRuntime
                .($perAction ? 's ('.count($actions).' x '.$perAction.'s per each). Accumulated actionLog time: '
                     .round($loadTime, 1).'s, createAction time: '.round($createTime, 1) : '')
            .'s.';
        $logData = array('method runtime'=>$methodRuntime, 'loop runtime'=>$loopRuntime, 'per each'=>$perAction);
        $logService->log(LogService::LEVEL_DEBUGINTERNAL, $logCode, $logMessage, $logData);

        return $actions;
    }

    /**
     * Update an action status (i.e. when completed)
     * @param Node $node The node to update for
     * @param Action $action The action instance being updated
     * @param $status The status to update to (0=new, 1=done)
     */
    public function setActionStatus(Node $node, Action $action, $status)
    {
        $sql = "UPDATE entity_action_status SET status = ".intval($status)
            ." WHERE action_id = ".$action->getId().' AND node_id = '.$node->getId().";";
        $this->getAdapter()->query($sql, Adapter::QUERY_MODE_EXECUTE);
    }

    /**
     * Update an update status (i.e. when completed)
     * @param Node $node The node to update for
     * @param Update $update The update instance being updated
     * @param $status The status to update to (0=new, 1=done)
     */
    public function setUpdateStatus(Node $node, Update $update, $status)
    {
        $sql = "UPDATE entity_update SET complete = ".intval($status)
            ." WHERE log_id = ".$update->getLogId()." AND node_id = ".$node->getId().";";
        $this->getAdapter()->query($sql, Adapter::QUERY_MODE_EXECUTE);

    }

    /**
     * Returns the since id entry from node_status, or 0 if none exists.
     * @param int $nodeId
     * @param int|string $entityType
     * @param string $action Normally one of retrieve or update
     * @return string $sinceId
     */
    public function getSinceId($nodeId, $entityType, $action)
    {
        if (is_int($entityType)) {
            $entityTypeId = $entityType;
        }else{
            $entityTypeId = $this->getServiceLocator()->get('entityConfigService')->parseEntityType($entityType);
        }

        /** @var \Node\Entity\NodeStatus $nodeStatus */
        $nodeStatus = $this->getServiceLocator()
            ->get('Doctrine\ORM\EntityManager')
            ->getRepository('Node\Entity\NodeStatus')
            ->getStatusForNode($nodeId, $entityTypeId, $action);

        if (is_null($nodeStatus)) {
            $sinceId = 0;
        }else{
            $sinceId = $nodeStatus->getSinceId();
        }

        return $sinceId;
    }

    /**
     * Updates the since id entry in node_status
     * @param int $nodeId
     * @param int|string $entityType
     * @param string $action Normally one of retrieve or update
     * @param int|null $sinceId The since id to update to
     * @throws MagelinkException
     */
    public function setSinceId($nodeId, $entityType, $action, $sinceId = NULL)
    {
        if (is_int($entityType)) {
            $entityTypeId = $entityType;
        }else{
            $entityTypeId = $this->getServiceLocator()->get('entityConfigService')->parseEntityType($entityType);
        }

        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        /** @var \Node\Entity\NodeStatus $nodeStatus */
        $nodeStatus = $entityManager
            ->getRepository('Node\Entity\NodeStatus')
            ->getStatusForNode($nodeId, $entityTypeId, $action);

        if (!$nodeStatus) {
            // We need to manually generate the ID as Doctrine doesn't like composite primary keys with auto increment
            //    (although MySQL does it fine)
            $idResult = $this->getAdapter()->query('SELECT MAX(id) AS max_id FROM node_status;', Adapter::QUERY_MODE_EXECUTE);

            $id = FALSE;
            foreach ($idResult as $result) {
                if (is_null($result['max_id'])) {
                    $result['max_id'] = 0;
                }
                $id = ++$result['max_id'];
            }

            if (!$id) {
                throw new MagelinkException('Unable to locate node_status ID!');
            }
            $nodeStatus = new \Node\Entity\NodeStatus();
            $nodeStatus->setNode($entityManager->getRepository('Node\Entity\Node')->find($nodeId));
            $nodeStatus->setAction($action);
            $nodeStatus->setEntityTypeId($entityTypeId);
            $nodeStatus->setId($id);
        }

        $entityManager->setSinceId($sinceId);
        $entityManager->persist($nodeStatus);
        $entityManager->flush($nodeStatus);
    }

    /**
     * Returns the timestamp entry from node_status, or 0 if none exists.
     * @param int $nodeId
     * @param int|string $entityType
     * @param string $action Normally one of retrieve or update
     * @return int
     */
    public function getTimestamp($nodeId, $entityType, $action)
    {
        if (is_int($entityType)) {
            $entity_type_id = $entityType;
        }else{
            $entity_type_id = $this->getServiceLocator()->get('entityConfigService')->parseEntityType($entityType);
        }
        /** @var \Node\Entity\NodeStatus $ts */
        $ts = $this->getServiceLocator()
            ->get('Doctrine\ORM\EntityManager')
            ->getRepository('Node\Entity\NodeStatus')
            ->getStatusForNode($nodeId, $entity_type_id, $action);

        if ($ts == null) {
            return 0;
        }
        if ($ts->getTimestamp() instanceof \DateTime) {
            return $ts->getTimestamp()->getTimestamp();
        }
        return $ts->getTimestamp();
    }

    /**
     * Updates the timestamp entry in node_status
     * @param int $nodeId
     * @param int|string $entityType
     * @param string $action Normally one of retrieve or update
     * @param int|null $timestamp The timestamp to update to - if not specified/null, uses the current time
     * @throws MagelinkException
     */
    public function setTimestamp($nodeId, $entityType, $action, $timestamp=null)
    {
        if ($timestamp == null) {
            $timestamp = time();
        }
        if (is_int($entityType)) {
            $entity_type_id = $entityType;
        }else{
            $entity_type_id = $this->getServiceLocator()->get('entityConfigService')->parseEntityType($entityType);
        }
        /** @var \Doctrine\ORM\EntityManager $es */
        $es = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        /** @var \Node\Entity\NodeStatus $ts */
        $ts = $es
            ->getRepository('Node\Entity\NodeStatus')
            ->getStatusForNode($nodeId, $entity_type_id, $action);

        if (!$ts) {
            // We need to manually generate the ID as Doctrine doesn't like composite primary keys with auto increment (although MySQL does it fine)
            $idRes = $this->getAdapter()->query('SELECT MAX(id) AS max_id FROM node_status;', Adapter::QUERY_MODE_EXECUTE);
            $id = false;
            foreach ($idRes as $arr) {
                if ($arr['max_id'] === null) {
                    $arr['max_id'] = 0;
                }
                $id = $arr['max_id']+1;
            }
            if (!$id) {
                throw new MagelinkException('Unable to locate node_status ID!');
            }
            $ts = new \Node\Entity\NodeStatus();
            $ts->setNode($es->getRepository('Node\Entity\Node')->find($nodeId));
            $ts->setAction($action);
            $ts->setEntityTypeId($entity_type_id);
            $ts->setId($id);
        }
        $ts->setTimestamp(new \DateTime('@'.$timestamp));
        $es->persist($ts);
        $es->flush($ts);
    }

    /**
     * Associates the given attribute with the given node
     * @param int $nodeId
     * @param string $attribute_code
     * @param string|int $entityType
     * @param bool $can_update Whether this node desires to update the given attribute. Defaults true.
     */
    public function subscribeAttribute($nodeId, $attribute_code, $entityType, $can_update = true)
    {
        unset($this->_subscribedAttributeCodeCache[$nodeId]);

        $entityType = $this->verifyEntityType($entityType);
        $attribute_id = $this->verifyAttribute($attribute_code, $entityType);

        $this->getTableGateway('node_attribute')->insert(array(
            'node_id'=>$nodeId,
            'attribute_id'=>$attribute_id,
            'can_update'=>($can_update ? 1 : 0),
        ));
    }

    /**
     * Unassociates the given attribute with the given node. No data is removed and the attribute is left in place.
     * @param int $nodeId
     * @param string $attribute_code
     * @param string $entityType
     */
    public function unsubscribeAttribute($nodeId, $attribute_code, $entityType) {
        unset($this->_subscribedAttributeCodeCache[$nodeId]);

        $entityType = $this->verifyEntityType($entityType);
        $attribute_id = $this->verifyAttribute($attribute_code, $entityType);

        $this->getTableGateway('node_attribute')->delete(array(
            'node_id'=>$nodeId,
            'attribute_id'=>$attribute_id,
        ));
    }

    /**
     * Associates a number of attributes with the given node.
     * @see subscribeAttribute()
     * @param int $nodeId
     * @param string[] $attribute_codes
     * @param string|int $entityType
     * @param bool $can_update Whether this node desires to update the given attributes. Defaults true.
     */
    public function bulkSubscribeAttribute($nodeId, $attribute_codes, $entityType, $can_update = true)
    {
        unset($this->_subscribedAttributeCodeCache[$nodeId]);
        // @todo optimize

        $entityType = $this->verifyEntityType($entityType);
        foreach ($attribute_codes as $code) {
            $this->subscribeAttribute($nodeId, $code, $entityType, $can_update);
        }
    }

    /**
     * Unassociates a number of attributes with the given node.
     * @see unsubscribeAttribute()
     * @param int $nodeId
     * @param string[] $attribute_codes
     * @param string|int $entityType
     */
    public function bulkUnsubscribeAttribute($nodeId, $attribute_codes, $entityType)
    {
        unset($this->_subscribedAttributeCodeCache[$nodeId]);
        // @todo optimize

        $entityType = $this->verifyEntityType($entityType);
        foreach ($attribute_codes as $code) {
            $this->unsubscribeAttribute($nodeId, $code, $entityType);
        }
    }

    /**
     * Return all attributes a given node is subscribed to, optionally filtered by entity type
     *
     * @param int $nodeId
     * @param bool|int|string $entityType
     * @param bool $updateOnly Whether to only return attributes with can_update set. Note: expensive, not cached.
     * @return array
     */
    public function getSubscribedAttributeCodes($nodeId, $entityType = FALSE, $updateOnly = FALSE)
    {
        if ($entityType === false) {
            $entityType = 0;
        }else{
            $entityType = $this->getServiceLocator()->get('entityConfigService')->parseEntityType($entityType);
        }

        if ($nodeId === 0 && $entityType !== false) {
            $entityConfigService = $this->getServiceLocator()->get('entityConfigService');
            return array_values($entityConfigService->getAttributesCode($entityType));
        }

        if ($updateOnly) {
            if (isset($this->_subscribedUpdateAttributeCodeCache[$nodeId])) {
                if (isset($this->_subscribedUpdateAttributeCodeCache[$nodeId][$entityType])) {
                    return $this->_subscribedUpdateAttributeCodeCache[$nodeId][$entityType];
                }
            }else{
                $this->_subscribedUpdateAttributeCodeCache[$nodeId] = array();
            }
        }else{
            if (isset($this->_subscribedAttributeCodeCache[$nodeId])) {
                if (isset($this->_subscribedAttributeCodeCache[$nodeId][$entityType])) {
                    return $this->_subscribedAttributeCodeCache[$nodeId][$entityType];
                }
            }else{
                $this->_subscribedAttributeCodeCache[$nodeId] = array();
            }
        }

        /* @var $select \Zend\Db\Sql\Select */
        $zendDb = new \Zend\Db\Sql\Sql($this->getAdapter());
        $select = $zendDb->select();
        /* @var $select \Zend\Db\Sql\Select */
        $select->from(array('na'=>'node_attribute'));
        $select->columns(array('attribute_id'=>'attribute_id'));
        if ($nodeId > 0) {
            $select->where(array('na.node_id'=>$nodeId));
        }
        if ($entityType) {
            $select->where(array('att.entity_type_id'=>$entityType));
        }
        if ($updateOnly) {
            $select->where(array('na.can_update'=>1));
        }
        $select->join(
            array('att'=>'entity_attribute'),
            new \Zend\Db\Sql\Expression('att.attribute_id = na.attribute_id'.($entityType != 0 ? ' AND att.entity_type_id = '.$entityType : '')),
            array('attribute_code'=>'code'), $select::JOIN_INNER
        );
        $this->getServiceLocator()->get('logService')->log(
            LogService::LEVEL_DEBUGEXTRA,
            'getsubattr',
            'getSubscribedAttributeCodes - '.$nodeId.'_'.$entityType.': '.$select->getSqlString($this->getAdapter()->getPlatform()),
            array('node_id'=>$nodeId, 'sql'=>$select->getSqlString($this->getAdapter()->getPlatform())),
            array('node'=>$nodeId)
        );

        $response = $this->getAdapter()
            ->query($select->getSqlString($this->getAdapter()->getPlatform()), Adapter::QUERY_MODE_EXECUTE);

        $retArr = array();
        foreach ($response as $row) {
            if (in_array($row['attribute_code'], $retArr)) {
                continue;
            }
            $retArr[] = $row['attribute_code'];
        }

        if ($updateOnly) {
            $this->_subscribedUpdateAttributeCodeCache[$nodeId][$entityType] = $retArr;
        }else{
            $this->_subscribedAttributeCodeCache[$nodeId][$entityType] = $retArr;
        }

        return $retArr;
    }

    /**
     * Verify that given entity type is valid (and transform as needed)
     *
     * @param int|string $entityType The entity type to process (by-reference)
     * @throws MagelinkException If the passed entity type is invalid
     * @return int Processed entity type
     */
    protected function verifyEntityType(&$entityType)
    {
        $entity_type_in = $entityType;
        //if ($entityType instanceof Entity\Model\Type) {
        //    $entityType = $entityType->getId();
        //}
        if (is_string($entityType)) {
            $entityType = $this->getServiceLocator()->get('entityConfigService')->parseEntityType($entityType);
        }
        if ($entityType <= 0 || !is_int($entityType)) {
            throw new MagelinkException('Invalid entity type passed to EntityService - '.$entity_type_in.' - '.$entityType);
        }

        return $entityType;
    }

    /**
     * Verify that given attribute is valid (and return it's ID)
     *
     * @param string $attribute_code
     * @param int|string $entityType The entity type
     * @throws MagelinkException If the passed attribute is invalid
     * @return int Processed entity type
     */
    protected function verifyAttribute($attribute_code, $entityType) {
        $entityType = $this->verifyEntityType($entityType);
        if (is_string($attribute_code)) {
            $attribute_code = $this->getServiceLocator()->get('entityConfigService')
                ->parseAttribute($attribute_code, $entityType);
        }
        if (is_numeric($attribute_code)) {
            return intval($attribute_code);
        }
        if ($attribute_code <= 0 || !is_int($attribute_code)) {
            throw new MagelinkException('Invalid attribute passed to NodeService - '.$entityType.' - '.$attribute_code);
        }

        return $attribute_code;
    }

}

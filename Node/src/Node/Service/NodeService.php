<?php

namespace Node\Service;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Node\AbstractNode;
use Node\Entity\Node;
use Node\Entity\NodeStatus;
use Zend\Db\TableGateway\TableGateway;
use Magelink\Exception\MagelinkException;

/**
 * Responsible for providing node_status data (i.e. updated timestamps), attribute/node assignments, and locating/updating pending updates and actions.
 *
 * @package Node\Service
 */
class NodeService implements ServiceLocatorAwareInterface {

    /**
     * Return an array of all currently active node entities
     *
     * @return Node[]
     */
    public function getActiveNodes(){
        return $this->getServiceLocator()
            ->get('Doctrine\ORM\EntityManager')
            ->getRepository('Node\Entity\Node')
            ->getActiveNodes();
    }
    /**
     * Return an array of all currently active node entities of a specified type
     *
     * @param string $type_str
     * @return Node[]
     */
    public function getActiveNodesByType($type_str){
        return $this->getServiceLocator()
            ->get('Doctrine\ORM\EntityManager')
            ->getRepository('Node\Entity\Node')
            ->getActiveNodesByType($type_str);
    }

    /**
     * Return all node IDs with the given type
     * @param string $type
     * @return array
     */
    public function getNodesByType($type){
        $res = $this->getTableGateway('node')->select(array('type'=>$type));
        $ret = array();
        foreach($res as $row){
            $ret[] = $row['node_id'];
        }
        return $ret;
    }

    /**
     * Get all pending updates for the given node
     * @param Node $nodeEnt
     * @throws MagelinkException If there is invalid data
     * @return \Entity\Update[]
     */
    public function getPendingUpdates(\Node\Entity\Node $nodeEnt){
        $updates = array();

        $res = $this->getTableGateway('entity_update')->select(array('node_id'=>$nodeEnt->getId(), 'complete'=>0));
        foreach($res as $row){
            $logs = $this->getTableGateway('entity_update_log')->select(array('log_id'=>$row['log_id']));
            $log = false;
            foreach($logs as $logRow){
                $log = $logRow;
                break;
            }
            if($log === false){
                throw new MagelinkException('Could not find log entry for update ' . $row['update_id']);
            }
            $ent = $this->getServiceLocator()->get('entityService')->loadEntityId($nodeEnt->getId(), $row['entity_id']);

            $upd = new \Entity\Update();
            $upd->init($log['log_id'], $ent, $log['type'], $log['timestamp'], $log['source_node'], $log['affected_nodes'], $log['affected_attributes']);

            $updates[] = $upd;
        }

        return $updates;
    }

    /**
     * Get all pending actions for the given node
     * @param Node $nodeEnt
     * @throws MagelinkException If there is invalid data
     * @return \Entity\Action[]
     */
    public function getPendingActions(\Node\Entity\Node $nodeEnt){
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $actions = array();

        $res = $this->getTableGateway('entity_action_status')->select(array('node_id'=>$nodeEnt->getId(), 'status'=>0));
        foreach($res as $row){

            /** @var \Entity\Entity\EntityAction $act */
            $act = $entityManager->find('Entity\Entity\EntityAction', $row['action_id']);

            if($act === false || !($act instanceof \Entity\Entity\EntityAction)){
                throw new MagelinkException('Could not find action entry for action ' . $row['action_id']);
            }
            $act->loadSimpleData();

            $ent = $this->getServiceLocator()->get('entityService')->loadEntityId($nodeEnt->getId(), $act->getEntityId());

            $obj = new \Entity\Action();
            $obj->init($act->getId(), $ent, $act->getActionType(), $act->getSimpleData());
            $actions[] = $obj;
        }

        return $actions;
    }

    /**
     * Update an action status (i.e. when completed)
     * @param Node $node The node to update for
     * @param \Entity\Action $act The action instance being updated
     * @param $status The status to update to (0=new, 1=done)
     */
    public function setActionStatus(\Node\Entity\Node $node, \Entity\Action $act, $status) {
        $this->getAdapter()->query('UPDATE entity_action_status SET status = ' . intval($status) . ' WHERE action_id = ' . $act->getId() . ' AND node_id = ' . $node->getId() . ';', \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
    }

    /**
     * Update an update status (i.e. when completed)
     * @param Node $node The node to update for
     * @param \Entity\Update $upd The update instance being updated
     * @param $status The status to update to (0=new, 1=done)
     */
    public function setUpdateStatus(\Node\Entity\Node $node, \Entity\Update $upd, $status) {
        $this->getAdapter()->query('UPDATE entity_update SET complete = ' . intval($status) . ' WHERE log_id = ' . $upd->getLogId() . ' AND node_id = ' . $node->getId() . ';', \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);

    }

    /**
     * Returns the timestamp entry from node_status, or 0 if none exists.
     * @param int $node_id
     * @param int|string $entity_type
     * @param string $action Normally one of retrieve or update
     * @return int
     */
    public function getTimestamp($node_id, $entity_type, $action){
        if(is_int($entity_type)){
            $entity_type_id = $entity_type;
        }else{
            $entity_type_id = $this->getServiceLocator()->get('entityConfigService')->parseEntityType($entity_type);
        }
        /** @var \Node\Entity\NodeStatus $ts */
        $ts = $this->getServiceLocator()
            ->get('Doctrine\ORM\EntityManager')
            ->getRepository('Node\Entity\NodeStatus')
            ->getStatusForNode($node_id, $entity_type_id, $action);

        if($ts == null){
            return 0;
        }
        if($ts->getTimestamp() instanceof \DateTime){
            return $ts->getTimestamp()->getTimestamp();
        }
        return $ts->getTimestamp();
    }

    /**
     * Updates the timestamp entry in node_status
     * @param int $node_id
     * @param int|string $entity_type
     * @param string $action Normally one of retrieve or update
     * @param int|null $timestamp The timestamp to update to - if not specified/null, uses the current time
     * @throws MagelinkException
     */
    public function setTimestamp($node_id, $entity_type, $action, $timestamp=null){
        if($timestamp == null){
            $timestamp = time();
        }
        if(is_int($entity_type)){
            $entity_type_id = $entity_type;
        }else{
            $entity_type_id = $this->getServiceLocator()->get('entityConfigService')->parseEntityType($entity_type);
        }
        /** @var \Doctrine\ORM\EntityManager $es */
        $es = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        /** @var \Node\Entity\NodeStatus $ts */
        $ts = $es
            ->getRepository('Node\Entity\NodeStatus')
            ->getStatusForNode($node_id, $entity_type_id, $action);

        if(!$ts){
            // We need to manually generate the ID as Doctrine doesn't like composite primary keys with auto increment (although MySQL does it fine)
            $idRes = $this->getAdapter()->query('SELECT MAX(id) AS max_id FROM node_status;', \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
            $id = false;
            foreach($idRes as $arr){
                if($arr['max_id'] === null){
                    $arr['max_id'] = 0;
                }
                $id = $arr['max_id']+1;
            }
            if(!$id){
                throw new MagelinkException('Unable to locate node_status ID!');
            }
            $ts = new \Node\Entity\NodeStatus();
            $ts->setNode($es->getRepository('Node\Entity\Node')->find($node_id));
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
     * @param int $node_id
     * @param string $attribute_code
     * @param string|int $entity_type
     * @param bool $can_update Whether this node desires to update the given attribute. Defaults true.
     */
    public function subscribeAttribute($node_id, $attribute_code, $entity_type, $can_update = true){
        unset($this->_subscribedAttributeCodeCache[$node_id]);

        $entity_type = $this->verifyEntityType($entity_type);
        $attribute_id = $this->verifyAttribute($attribute_code, $entity_type);

        $this->getTableGateway('node_attribute')->insert(array(
            'node_id'=>$node_id,
            'attribute_id'=>$attribute_id,
            'can_update'=>($can_update ? 1 : 0),
        ));
    }

    /**
     * Unassociates the given attribute with the given node. No data is removed and the attribute is left in place.
     * @param int $node_id
     * @param string $attribute_code
     * @param string $entity_type
     */
    public function unsubscribeAttribute($node_id, $attribute_code, $entity_type){
        unset($this->_subscribedAttributeCodeCache[$node_id]);

        $entity_type = $this->verifyEntityType($entity_type);
        $attribute_id = $this->verifyAttribute($attribute_code, $entity_type);

        $this->getTableGateway('node_attribute')->delete(array(
            'node_id'=>$node_id,
            'attribute_id'=>$attribute_id,
        ));
    }

    /**
     * Associates a number of attributes with the given node.
     * @see subscribeAttribute()
     * @param int $node_id
     * @param string[] $attribute_codes
     * @param string|int $entity_type
     * @param bool $can_update Whether this node desires to update the given attributes. Defaults true.
     */
    public function bulkSubscribeAttribute($node_id, $attribute_codes, $entity_type, $can_update = true){
        unset($this->_subscribedAttributeCodeCache[$node_id]);
        // TODO optimize

        $entity_type = $this->verifyEntityType($entity_type);
        foreach($attribute_codes as $code){
            $this->subscribeAttribute($node_id, $code, $entity_type, $can_update);
        }
    }

    /**
     * Unassociates a number of attributes with the given node.
     * @see unsubscribeAttribute()
     * @param int $node_id
     * @param string[] $attribute_codes
     * @param string|int $entity_type
     */
    public function bulkUnsubscribeAttribute($node_id, $attribute_codes, $entity_type){
        unset($this->_subscribedAttributeCodeCache[$node_id]);
        // TODO optimize

        $entity_type = $this->verifyEntityType($entity_type);
        foreach($attribute_codes as $code){
            $this->unsubscribeAttribute($node_id, $code, $entity_type);
        }
    }

    protected $_subscribedAttributeCodeCache = array();
    protected $_subscribedUpdateAttributeCodeCache = array();

    /**
     * Return all attributes a given node is subscribed to, optionally filtered by entity type
     *
     * @param int $node_id
     * @param bool|int|string $entity_type
     * @param bool $update_only Whether to only return attributes with can_update set. Note: expensive, not cached.
     * @return array
     */
    public function getSubscribedAttributeCodes($node_id, $entity_type = false, $update_only = false){
        if($entity_type === false){
            $entity_type = 0;
        }else{
            $entity_type = $this->getServiceLocator()->get('entityConfigService')->parseEntityType($entity_type);
        }

        if($node_id === 0 && $entity_type !== false){
            $entityConfigService = $this->getServiceLocator()->get('entityConfigService');
            return array_values($entityConfigService->getAttributes($entity_type));
        }

        if($update_only){
            if(isset($this->_subscribedUpdateAttributeCodeCache[$node_id])){
                if(isset($this->_subscribedUpdateAttributeCodeCache[$node_id][$entity_type])){
                    return $this->_subscribedUpdateAttributeCodeCache[$node_id][$entity_type];
                }
            }else{
                $this->_subscribedUpdateAttributeCodeCache[$node_id] = array();
            }
        }else{
            if(isset($this->_subscribedAttributeCodeCache[$node_id])){
                if(isset($this->_subscribedAttributeCodeCache[$node_id][$entity_type])){
                    return $this->_subscribedAttributeCodeCache[$node_id][$entity_type];
                }
            }else{
                $this->_subscribedAttributeCodeCache[$node_id] = array();
            }
        }

        /* @var $select \Zend\Db\Sql\Select */
        $zendDb = new \Zend\Db\Sql\Sql($this->getAdapter());
        $select = $zendDb->select();
        /* @var $select \Zend\Db\Sql\Select */
        $select->from(array('na'=>'node_attribute'));
        $select->columns(array('attribute_id'=>'attribute_id'));
        if($node_id > 0){
            $select->where(array('na.node_id'=>$node_id));
        }
        if($entity_type){
            $select->where(array('att.entity_type_id'=>$entity_type));
        }
        if($update_only){
            $select->where(array('na.can_update'=>1));
        }
        $select->join(array('att'=>'entity_attribute'), new \Zend\Db\Sql\Expression('att.attribute_id = na.attribute_id'.($entity_type != 0 ? ' AND att.entity_type_id = ' . $entity_type : '')), array('attribute_code'=>'code'), $select::JOIN_INNER);
        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA, 'getsubattr', 'getSubscribedAttributeCodes - ' . $node_id . '_' . $entity_type.': '.$select->getSqlString($this->getAdapter()->getPlatform()), array('node_id'=>$node_id, 'sql'=>$select->getSqlString($this->getAdapter()->getPlatform())), array('node'=>$node_id));

        $res = $this->getAdapter()->query($select->getSqlString($this->getAdapter()->getPlatform()), \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);

        $retArr = array();
        foreach($res as $row){
            if(in_array($row['attribute_code'], $retArr)){
                continue;
            }
            $retArr[] = $row['attribute_code'];
        }

        if($update_only){
            $this->_subscribedUpdateAttributeCodeCache[$node_id][$entity_type] = $retArr;
        }else{
            $this->_subscribedAttributeCodeCache[$node_id][$entity_type] = $retArr;
        }

        return $retArr;
    }

    /**
     * Verify that given entity type is valid (and transform as needed)
     *
     * @param int|string $entity_type The entity type to process (by-reference)
     * @throws MagelinkException If the passed entity type is invalid
     * @return int Processed entity type
     */
    protected function verifyEntityType(&$entity_type){
        $entity_type_in = $entity_type;
        //if($entity_type instanceof Entity\Model\Type){
        //    $entity_type = $entity_type->getId();
        //}
        if(is_string($entity_type)){
            $entity_type = $this->getServiceLocator()->get('entityConfigService')->parseEntityType($entity_type);
        }
        if($entity_type <= 0 || !is_int($entity_type)){
            throw new \Magelink\Exception\MagelinkException('Invalid entity type passed to EntityService - ' . $entity_type_in . ' - ' . $entity_type);
        }

        return $entity_type;
    }

    /**
     * Verify that given attribute is valid (and return it's ID)
     *
     * @param string $attribute_code
     * @param int|string $entity_type The entity type
     * @throws MagelinkException If the passed attribute is invalid
     * @return int Processed entity type
     */
    protected function verifyAttribute($attribute_code, $entity_type){
        $entity_type = $this->verifyEntityType($entity_type);
        if(is_string($attribute_code)){
            $attribute_code = $this->getServiceLocator()->get('entityConfigService')->parseAttribute($attribute_code, $entity_type);
        }
        if(is_numeric($attribute_code)){
            return intval($attribute_code);
        }
        if($attribute_code <= 0 || !is_int($attribute_code)){
            throw new \Magelink\Exception\MagelinkException('Invalid attribute passed to NodeService - ' . $entity_type . ' - ' . $attribute_code);
        }

        return $attribute_code;
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
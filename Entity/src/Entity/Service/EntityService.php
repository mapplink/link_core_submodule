<?php
/**
 * Entity\Service
 * @category Entity
 * @package Entity\Service
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Entity\Service;

use \Zend\ServiceManager\ServiceLocatorAwareInterface;
use \Zend\ServiceManager\ServiceLocatorInterface;
use \Zend\Db\TableGateway\TableGateway;
use Magelink\Exception\MagelinkException;
use Magelink\Exception\NodeException;
use Entity\Comment;


class EntityService implements ServiceLocatorAwareInterface
{
    /** @var ServiceLocatorInterface */
    protected $_serviceLocator;

    /** @var TableGateway[]  Cache of preloaded table gateways */
    protected $_tgCache = array();

    /** @var \Entity\Helper\Saver  Helper used for saving records to the database */
    protected $_saver;

    /** @var \Entity\Helper\Loader  Helper used for loading records from the database */
    protected $_loader;

    /** @var \Entity\Helper\Querier  Helper used for loading records from the database using MLQL */
    protected $_querier;


    /**
     * Retreive Saving helper
     * @see $_saver
     * @return \Entity\Helper\Saver
     */
    protected function getSaver()
    {
        if($this->_saver){
            return $this->_saver;
        }
        $this->_saver = new \Entity\Helper\Saver();
        $this->_saver->setServiceLocator($this->getServiceLocator());
        return $this->_saver;
    }

    /**
     * Retrieve Loading helper
     * @see $_loader
     * @return \Entity\Helper\Loader
     */
    protected function getLoader()
    {
        if($this->_loader){
            return $this->_loader;
        }
        $this->_loader = new \Entity\Helper\Loader();
        $this->_loader->setServiceLocator($this->getServiceLocator());
        return $this->_loader;
    }

    /**
     * Retrieve Querying helper
     * @see $_querier
     * @return \Entity\Helper\Querier
     */
    protected function getQuerier(){
        if($this->_querier){
            return $this->_querier;
        }
        $this->_querier = new \Entity\Helper\Querier();
        $this->_querier->setServiceLocator($this->getServiceLocator());
        return $this->_querier;
    }

    /**
     * Loads the entity with the given ID from the database for the given node.
     * @param int $nodeId
     * @param int $entityId
     * @return \Entity\Entity|null
     */
    public function loadEntityId($nodeId, $entityId)
    {
        $this->verifyNodeId($nodeId);

        if (is_string($entityId) || is_numeric($entityId)) {
            if ((string) $entityId == (string) (int) $entityId || (string) $entityId == (string) (float) $entityId) {
                $entityId = intval($entityId);
            }else{
                throw new NodeException('Invalid entity ID passed to loadEntityId - '.$entityId);
            }
        }

        $entityTypeId = $this->getLoader()->getEntityTypeId($entityId);
        $this->getServiceLocator()->get('logService')->log(
            \Log\Service\LogService::LEVEL_DEBUG,
            'loadeid',
            'loadEntityId - '.$nodeId.' - '.$entityId.' ('.$entityTypeId.')',
            array('node_id'=>$nodeId, 'entity_id'=>$entityId), array('entity'=>$entityId)
        );

        $attributes = $this->getServiceLocator()->get('nodeService')
            ->getSubscribedAttributeCodes($nodeId, $entityTypeId);
        $entities = $this->getLoader()->loadEntities(
            $entityTypeId,
            0,
            array('ENTITY_ID'=>$entityId),
            $attributes,
            array('ENTITY_ID'=>'eq'),
            array('limit'=>1, 'node_id'=>$nodeId)
        );

        if (!$entities || !count($entities)) {
            $entity = NULL;
        }else{
            $entity = array_shift($entities);
        }

        return $entity;
    }

    /**
     * Load segregated orders
     * @param $nodeId
     * @param \Entity\Entity $entity
     * @return array|\Entity\Entity[]
     * @throws \Magelink\Exception\NodeException
     */
    public function loadSegregatedOrders($nodeId, \Entity\Entity $entity)
    {
        $this->verifyNodeId($nodeId);

        if ($entity->getTypeStr() != 'order') {
            throw new NodeException('Invalid entity passed to loadSegregatedOrders: '.$entity->getTypeStr().'.');
        }

        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG,
                'loadeid',
                'loadEntityId - '.$nodeId.' - '.$entity->getId().' ('.$entity->getTypeStr().')',
                array('node_id'=>$nodeId, 'entity_id'=>$entity->getId()),
                array('entity'=>$entity)
            );

        $attributes = $this->getServiceLocator()->get('nodeService')
            ->getSubscribedAttributeCodes($nodeId, $entity->getType());
        $entities = $this->getLoader()->loadEntities(
            $entity->getType(),
            0,
            array('original_order'=>$entity->getId()),
            $attributes,
            array('original_order'=>'eq'),
            array('node_id'=>$nodeId)
        );

        if (!$entities || !count($entities)) {
            $entities = array();
        }

        return $entities;
    }

    /**
     * Reloads the provided entity from the database to capture any freshly updated data.
     * @param \Entity\Entity $entity The entity to reload (passed by reference)
     * @return \Entity\Entity
     */
    public function reloadEntity(\Entity\Entity &$entity)
    {
        $entity = $this->loadEntityId($entity->getLoadedNodeId(), $entity->getId());
        if (!$entity) {
            throw new NodeException('Cannot reload entity - potentially deleted?');
        }
        return $entity;
    }
    
    /**
     * Loads the entity with the provided unique key from the database for the given node.
     * 
     * @param int $nodeId
     * @param int|string $entityType
     * @param string $storeId
     * @param string $uniqueId
     * @return \Entity\Entity|null
     */
    public function loadEntity($nodeId, $entityType, $storeId, $uniqueId)
    {
        $this->verifyNodeId($nodeId);
        $this->verifyEntityType($entityType);
        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG,
                'loade',
                'loadEntity - '.$nodeId.' - '.$entityType.' - '.$storeId.' - '.$uniqueId,
                array('node_id'=>$nodeId, 'entity_type'=>$entityType, 'store_id'=>$storeId, 'unique_id'=>$uniqueId)
            );

        $attributes = $this->getServiceLocator()->get('nodeService')
            ->getSubscribedAttributeCodes($nodeId, $entityType);

        $result = $this->getLoader()
            ->loadEntities(
                $entityType,
                $storeId,
                array('UNIQUE_ID'=>$uniqueId),
                $attributes,
                array('UNIQUE_ID'=>'eq'),
                array('limit'=>1, 'node_id'=>$nodeId)
            );

        $return = NULL;
        if ($result && count($result)) {
            foreach ($result as $entity) {
                $return = $entity;
                break;
            }
        }

        return $return;
    }

    /**
     * Loads the entity flat data from the database
     * @param string $entityType
     * @param string $where
     * @param boolean|string $orderBy
     */
    public function loadFlatEntity($entityType, $columns = '*', $where = FALSE, $orderBy = FALSE)
    {
        if ($entityType = $this->hasFlatTable($entityType)) {
            $sql = "SELECT ".$columns." FROM entity_flat_".$entityType
                .($where ? " WHERE ".$where : "")
                .($orderBy ? " ORDER BY ".$orderBy : "")
                .";";
            $itemData = $this->getAdapter()->query($sql, \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE); //$this->executeSqlQuery($sql);

            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA,
                'ld_flat_ety', 'load flat entity: '.$sql, array('sql'=>$sql, 'response'=>$itemData));
        }else{
            $itemData = array();
        }

        return $itemData;
    }

    /**
     * @param string$entityType
     * @param array $set
     * @param string $where
     * @return bool
     */
    public function updateFlatEntity($entityType, array $set, $where)
    {
        $success = FALSE;
        if ($entityType = $this->hasFlatTable($entityType)) {
            $sets = array();
            foreach ($set as $field=>$expression) {
                $sets[] = "`".$field."` = ".$expression;
            }
            $set = trim(implode(', ', $sets));
            $where = trim($where);

            if ($set && $where) {
                $sql = "UPDATE entity_flat_".$entityType
                    ." SET ".$set
                    ." WHERE ".$where
                    .";";

                $success = (bool) $this->getAdapter()->query($sql, \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE); //$this->executeSqlQuery($sql);

                $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA,
                        'upd_flat_ety', 'update flat entity: '.$sql, array('sql'=>$sql, 'success'=>$success));
            }
        }

        return $success;
    }

    /**
     * Loads the entity identified by the given local_id from the database for the given node.
     * 
     * @param int $nodeId
     * @param int|string $entityType
     * @param string $storeId
     * @param string $localId
     * @return \Entity\Entity|null
     */
    public function loadEntityLocal($nodeId, $entityType, $storeId, $localId)
    {
        $this->verifyNodeId($nodeId);
        $this->verifyEntityType($entityType);

        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG,
                'loadeloc',
                'loadEntityLocal - '.$nodeId.' - '.$entityType.' - '.$storeId.' - '.$localId,
                array('node_id'=>$nodeId, 'entity_type'=>$entityType, 'store_id'=>$storeId, 'local_id'=>$localId)
            );

        $attributes = $this->getServiceLocator()->get('entityConfigService')->getAttributesCode($entityType);

        $result = $this->getLoader()
            ->loadEntities(
                $entityType,
                $storeId,
                array('LOCAL_ID'=>$localId),
                $attributes,
                array('LOCAL_ID'=>'eq'),
                array('linked_to_node'=>$nodeId, 'limit'=>1, 'node_id'=>$nodeId)
            );

        if(!$result || !count($result)){
            return NULL;
        }else{
            foreach($result as $entity){
                // Return first row
                return $entity;
            }
        }

        return NULL;
    }

    /**
     * Loads all the child entities of the provided Entity, for the given node. Empty array if none exist.
     * @param int $nodeId
     * @param \Entity\Entity $parent
     * @param int|string $entityType_id
     * @return \Entity\Entity[]
     */
    public function loadChildren($nodeId, \Entity\Entity $parent, $entityTypeId)
    {
        $this->verifyNodeId($nodeId);
        $this->verifyEntityType($entityTypeId);
        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG,
                'loadchildren',
                'loadChildren - '.$nodeId.' - '.$parent->getId().' - '.$entityTypeId,
                array('node_id'=>$nodeId, 'parent_id'=>$parent->getId(), 'entity_type_id'=>$entityTypeId),
                array('entity'=>$parent)
            );

        $attributes = $this->getServiceLocator()->get('nodeService')
            ->getSubscribedAttributeCodes($nodeId, $entityTypeId);

        $result = $this->getLoader()->loadEntities(
            $entityTypeId,
            0,
            array('PARENT_ID'=>$parent->getId()),
            $attributes,
            array('PARENT_ID'=>'eq'), array('node_id'=>$nodeId)
        );

        if(!$result || !count($result)){
            return array();
        }else{
            return $result;
        }
    }

    /**
     * Loads the parent entity of the provided Entity, for the given node. Null if not specified.
     * 
     * @param int $nodeId
     * @param \Entity\Entity $child
     * @return \Entity\Entity|null
     */
    public function loadParent($nodeId, \Entity\Entity $child)
    {
        $this->verifyNodeId($nodeId);
        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG,
                'loadparent',
                'loadParent - '.$nodeId.' - '.$child->getId(),
                array('node_id'=>$nodeId, 'parent_id'=>$child->getId()),
                array('entity'=>$child)
            );


        if($child->getParentId()){
            return $this->loadEntityId($nodeId, $child->getParentId());
        }else{
            return null;
        }
    }
    
    /**
     * Searches for entities that match the given parameters.
     * 
     * $searchData is an associative array where the keys are attribute codes, and the values are the contents to match.
     * There is also a $searchType array, which defines how the values in the first array are treated. The search types are as follows:
     * * eq - Default for all scalar values, does an exact equals search, or in the case of an attribute that has multiple values in the DB, an exact equals search on any of the values in the DB.
     * * all_eq - Same as eq but in the case of an attribute with multiple values in the DB, all values must match the provided value.
     * * in - Default for all numeric array values, does an IN query, or in the case of an attribute that has multiple values in the DB, does an IN query on any of the values
     * * all_in - Same as in but in the case of an attribute with multiple values in the DB, all values must be in the provided values.
     * * multi_key - Only for multi type attributes, checks if the provided value is used as a key in any of the DB entries.
     * * multi_value - Only for multi type attributes, checks if the provided value is used as a value in any of the DB entries.
     * * gt - Same as eq but does a greater-than search instead of equals.
     * * all_gt - Same as all_eq but does a greater-than search instead of equals.
     * * lt - Same as eq but does a less-than search instead of equals
     * * all_lt - Same as all_eq but does a less-than search instead of equals
     * * not_eq - Does an exact not equals search, or in the case of an attribute that has multiple values in the DB, checks that none of them are equal to this value.
     * * not_in - Same as not_eq but performs either a check the DB value is not one of the provided values, or if there are multiple values in the DB, that none of them are equal to any of the provided values,
     * * null - Must be equal to null
     * * notnull - Must not be equal to null
     * * impossible - Will never be true (used internally)

     * In addition to the above, the options array can contain the following keys:
     * * order - An associative array of attribute codes to sort by, organized with the attribute code as the key and the direction as the value.
     * * limit - The maximum number of rows to return (MySQL LIMIT)
     * * offset - The number of rows to skip (MySQL OFFSET)
     * * aggregate - The aggregate configuration, see ->aggregateEntity
     * * select_prefix - A prefix to be defined to all fields in the result (internal use only)
     * * fkey - An array of fields to join to allow searching on entity fkey resolved attributes. Key should be attribute code (entity type) and value should be entity type of target entity.
     * * count - A field to perform a COUNT on. Can be * or the name of a static field.
     * * group - An array of attribute codes to be included in the GROUP BY of the locate select (internal use only)
     * * linked_to_node - If specified, filters only on entities linked to this node. Field LOCAL_ID is then available for searching on as well.
     * * no_select - Rarely used internal option, stops all static / searched fields being selected in the locate query (used for nested searches, deprecated)
     * * static_field - A static field name, which will cause locateEntity to return an array with the keys being entity IDs and the values being the values of this static field. Used to get an array of matching unique IDs, for instance.
     * * node_id - Generally populated automatically, used to set the loadedNodeId for any loaded entities (for loading of resolved entities and other data later on). Should not be overridden without good cause.
     * 
     * @param int $nodeId
     * @param int|string $entityType
     * @param string|array|false $store_id
     * @param array $searchData
     * @param array $searchType
     * @param array $options
     * @param array $attributes If specified, an array of attribute codes to load (should only be used when you want to load only a couple for performance reasons)
     * @return \Entity\Entity[]
     */
    public function locateEntity($nodeId, $entityType, $store_id, $searchData, $searchType = array(),
        $options = array(), $attributes = NULL)
    {
        if($nodeId !== 0){
            $this->verifyNodeId($nodeId);
        }
        $this->verifyEntityType($entityType);

        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG,
                'locate',
                'locateEntity - '.$nodeId.' - '.$entityType.' - '.$store_id.'. '.PHP_EOL.'SD: '.PHP_EOL.var_export($searchData, TRUE).PHP_EOL.'; ST: '.PHP_EOL.var_export($searchType, TRUE).PHP_EOL.'; OPT: '.PHP_EOL.var_export($options, TRUE).PHP_EOL,
                array(),
                array('node'=>$nodeId)
            );

        if($attributes == null){
            $attributes = $this->getServiceLocator()->get('nodeService')->getSubscribedAttributeCodes($nodeId, $entityType);
        }

        if(!isset($options['node_id'])){
            $options['node_id'] = $nodeId;
        }

        $result = $this->getLoader()->loadEntities($entityType, $store_id, $searchData, $attributes, $searchType, $options);
        return $result;
    }

    /**
     * Performs the same actions as locateEntity() however returns the count of all items instead. Used for paginating the results above.
     * @see locateEntity()
     * @param int $nodeId
     * @param int|string$entityType
     * @param int $store_id
     * @param array $searchData
     * @param array $searchType
     * @param array $options
     * @return int
     * @throws \Magelink\Exception\MagelinkException
     */
    public function countEntity($nodeId, $entityType, $store_id, $searchData, $searchType = array(), $options = array()) {
        if($nodeId !== 0){
            $this->verifyNodeId($nodeId);
        }
        $this->verifyEntityType($entityType);

        if(!array_key_exists('count', $options)){
            $options['count'] = '*';
        }

        if(!array_key_exists('node_id', $options)){
            $options['node_id'] = $nodeId;
        }

        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG,
                'count',
                'countEntity - '.$nodeId.' - '.$entityType.' - '.$store_id.'. '.PHP_EOL.'SD: '.PHP_EOL.var_export($searchData, TRUE).PHP_EOL.'; ST: '.PHP_EOL.var_export($searchType, TRUE).PHP_EOL.'; OPT: '.PHP_EOL.var_export($options, TRUE).PHP_EOL,
                array(),
                array('node'=>$nodeId)
            );

        $attributes = $this->getServiceLocator()->get('nodeService')->getSubscribedAttributeCodes($nodeId, $entityType);

        $result = $this->getLoader()->loadEntities($entityType, $store_id, $searchData, $attributes, $searchType, $options);
        if(!is_int($result)){
            throw new \Magelink\Exception\MagelinkException('Invalid result for countEntity - ' . gettype($result));
        }
        return $result;
    }

    /**
     * Performs the same actions as locateEntity() however returns an aggregated result
     *
     * @see locateEntity()
     * @param int $nodeId
     * @param int|string $entityType
     * @param int $storeId
     * @param array $aggregate
     * @param array $searchData
     * @param array $searchType
     * @param array $options
     * @throws \Magelink\Exception\MagelinkException
     * @return int
     */
    public function aggregateEntity($nodeId, $entityType, $storeId, $aggregate,
        $searchData = array(), $searchType = array(), $options = array())
    {
        $this->verifyNodeId($nodeId);
        $this->verifyEntityType($entityType);

        if(!array_key_exists('node_id', $options)){
            $options['node_id'] = $nodeId;
        }

        $options['aggregate'] = $aggregate;
        $attributes = $this->getServiceLocator()->get('nodeService')->getSubscribedAttributeCodes($nodeId, $entityType);
        $result = $this->getLoader()
            ->loadEntities($entityType, $storeId, $searchData, $attributes, $searchType, $options);
        if ($result instanceof \ArrayObject) {
            $result = $result->getArrayCopy();
        }

        return $result;
    }


    /**
     * Ensure all the attributes for the given node are loaded into the provided Entity, and if provided, also the attributes identified by $additional_attributes.
     * 
     * Used if Entities are passed around or if passed to core code that needs more data.
     * The original Entity instance should not be modified, instead it should be cloned and the new Entity returned.
     * 
     * @param int $nodeId
     * @param \Entity\Entity $entity
     * @param array $additional_attributes
     * @return \Entity\Entity
     */
    public function enhanceEntity ($nodeId, \Entity\Entity $entity, $additional_attributes = array())
    {
        if($nodeId !== false){
            $this->verifyNodeId($nodeId);

            $attributes = $this->getServiceLocator()->get('nodeService')->getSubscribedAttributeCodes($nodeId, $entity->getType());
            $attributes = array_unique(array_merge($additional_attributes, $attributes));
        }else{
            $attributes = $additional_attributes;
        }

        // Remove already loaded attributes
        foreach($attributes as $k=>$v){
            if(!$v || !strlen($v)){
                unset($attributes[$k]);
                continue;
            }
            if($entity->hasAttribute($v)){
                unset($attributes[$k]);
                continue;
            }
        }

        if(count($attributes)){
            $this->getLoader()->enhanceEntity($entity, $attributes);
        }

        return $entity;
    }

    /**
     * Begins an entity transaction.
     * The transaction MUST be committed or rolled back, or the entire request/process will be stuck in a transaction.
     * @param string $id An arbitrary entity ID
     */
    public function beginEntityTransaction($id)
    {
        $this->getSaver()->beginTransaction('enttr-'.$id);
    }

    /**
     * Commits an entity transaction.
     * @param string $id
     */
    public function commitEntityTransaction($id)
    {
        $this->getSaver()->commitTransaction('enttr-'.$id);
    }

    /**
     * Rolls back an entity transaction
     * @param string $id
     */
    public function rollbackEntityTransaction($id)
    {
        $this->getSaver()->rollbackTransaction('enttr-'.$id);
    }

    /**
     * Creates a new entity in the DB, loads it, and returns the newly loaded Entity object.
     * This Entity is not linked to the node at this stage.
     * @param int $nodeId
     * @param int|string $entityType
     * @param string $store_id
     * @param string $unique_id
     * @param array $data
     * @param \Entity\Entity|int $parent
     * @throws MagelinkException
     * @return \Entity\Entity
     */
    public function createEntity ($nodeId, $entityType, $store_id, $unique_id, $data, $parent = NULL)
    {
        $this->verifyNodeId($nodeId);
        $this->verifyEntityType($entityType);

        if(is_object($parent)){
            if($parent instanceof \Entity\Entity){
                $parent = $parent->getId();
            }else{
                throw new NodeException('Invalid object type '.get_class($parent).' passed to createEntity!');
            }
        }

        $allowedAttributes = $this->getServiceLocator()->get('nodeService')
            ->getSubscribedAttributeCodes($nodeId, $entityType, TRUE);
        foreach ($data as $k=>$v) {
            if (strlen(trim($k)) == 0){
                unset($data[$k]);
                continue;
            }
            if(!in_array($k, $allowedAttributes)){
                throw new NodeException('Invalid attribute specified for update ' . $k);
            }
        }

        $id = $this->getSaver()->createEntity($entityType, $store_id, $unique_id, ($parent ? $parent : 0), $data);

        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG,
                'create',
                'createEntity - '.$nodeId.' - new entity '.$id.' is '.$entityType,
                array('type'=>$entityType, 'store'=>$store_id, 'unique'=>$unique_id),
                array('entity'=>$id, 'node'=>$nodeId)
            );

        $entity = $this->loadEntityId($nodeId, $id);

        $transformedData = $this->getServiceLocator()->get('routerService')
            ->processTransforms($entity, $data, $nodeId, \Entity\Update::TYPE_CREATE);
        if(count($transformedData)){
            $this->silentUpdateEntity($entity, $transformedData, false);
            $data = array_merge($data, $transformedData);
        }

        $sql = 'UPDATE router_stat_type SET `count` = `count` + 1 WHERE entity_type_id = '.$entityType.';';
        $this->getAdapter()->query($sql, \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);

        if($nodeId !== 0){
            $this->getServiceLocator()->get('routerService')
                ->distributeUpdate($entity, $data, $nodeId, \Entity\Update::TYPE_CREATE);
        }

        return $entity;
    }

    /**
     * @param int $nodeId
     * @param array|string $flatFields
     * @return array $entityAttributeArray
     * @throws NodeException
     */
    protected function getEntityAttributeArray($nodeId, $flatFields)
    {
        if (!is_array($flatFields)) {
            $flatFields = array($flatFields);
        }

        $entityAttributeArray = array();
        foreach ($flatFields as $field) {
            $eavDetails = $this->getEntityEavDetails($field);
            list($entityType, $attributeCode) = each($eavDetails);
            if ($entityType && $attributeCode) {
                if (array_key_exists($entityType, $entityAttributeArray)) {
                    $entityAttributeArray[$entityType][] = $attributeCode;
                }else {
                    $entityAttributeArray[$entityType] = array($attributeCode);
                }
            }
        }

        foreach ($entityAttributeArray as $entityType=>$attributeCodes) {
            $entityTypeId = $this->verifyEntityType($entityType);
            $subscribedAttributes = $this->getServiceLocator()->get('nodeService')
                ->getSubscribedAttributeCodes($nodeId, $entityTypeId);

            foreach ($attributeCodes as $code) {
                if (!in_array($code, $subscribedAttributes)) {
                    unset($entityAttributeArray[$entityType][$code]);
                }
            }
        }

        return $entityAttributeArray;
    }

    /**
     * @param \Entity\Entity $entity
     * @param bool $insert
     * @return bool $success
     * @throws MagelinkException
     */
    protected function replaceFlatFromEav($nodeId, \Entity\Entity $entity, $entityToUpdate = NULL, $entityOnly = FALSE)
    {
        $entityType = $entity->getTypeStr();
        if ($entityToUpdate === NULL) {
            $entityToUpdate = $entity;
            $entityOnly = FALSE;
        }

        $entityArray = array('flat entity'=>$entity, 'entity to update'=>$entityToUpdate);

        $flatFields = $this->getServiceLocator()->get('entityConfigService')
            ->getFlatEntityTypeFields($entityType);
        $flatData = $entity->getFlatDataFromEav($flatFields, $entityToUpdate, $entityOnly);

        if (is_array($flatData) && count($flatData)) {
            $maxTries = 3;
            do {
                $replaceData = $flatData;
                foreach ($replaceData as $key=>$data) {
                    $replaces = array();
                    $escapedData = $this->getQuerier()->getEscapedColumnValueArray($data);
                    foreach ($escapedData as $field=>$value) {
                        $replaces[$field] = $field." = ".$value;
                    }

                    $sql = "REPLACE INTO entity_flat_".$entityType." SET ".implode(', ', $replaces).";";
                    try {
                        $response = $this->getAdapter()->query($sql, \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE); //$this->executeSqlQuery($sql);
                        $success = (bool) $response;
                    }catch(\Exception $exception) {
                        $response = NULL;
                        $success = FALSE;
                    }

                    $message = ' replaceFlat query: '.$sql;
                    $dataArray = array(
                        'entity_id'=>$entity->getId(),
                        'flat entity_type'=>$entityType,
                        'to update entity_type'=>$entityToUpdate->getTypeStr(),
                        'to update unique_id'=>$entity->getUniqueId(),
                        'data'=>$data,
                        'sql'=>$sql,
                        'response'=>$response
                    );

                    if ($success) {
                        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA,
                            'rpl_flat_row', 'Successful'.$message, $dataArray, $entityArray);
                        unset($flatData[$key]);
                    }else{
                        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_WARN,
                            'failed_rpl_flat_row', 'Failure of'.$message, $dataArray, $entityArray);
                    }
                }
            }while (count($flatData) && --$maxTries > 0);

            $allSuccessful = !count($flatData);
            unset($dataArray['data'], $dataArray['response']);

            if ($allSuccessful) {
                $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUG,
                    'rpl_flat', 'Successful replacement of flat data rows.', $dataArray, $entityArray);
            }else{
                $dataArray['not replaced'] = $flatData;
                $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR,
                    'failed_rpl_flat', 'Replacing of '.count($flatData).' flat rows failed.', $dataArray, $entityArray);
            }
        }else{
            $message = 'Creation of flat data on '.$entity->getId().' ('.$entity->getUniqueId().') failed completely.';
            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR,
                'failed_rpl_flat',
                $message,
                array(
                    'entity_id'=>$entity->getId(),
                    'flat entity_type'=>$entityType,
                    'to update entity_type'=>$entityToUpdate->getTypeStr(),
                    'to update unique_id'=>$entity->getUniqueId(),
                    'flat fields'=>$flatFields,
                    'flat data'=>$flatData,
                ),
                $entityArray
            );
            throw new MagelinkException($message);
        }

        return $allSuccessful;
    }

    /**
     * @param $nodeId
     * @param \Entity\Entity $entity
     * @return bool
     */
    public function createFlatFromEav($nodeId, \Entity\Entity $entity)
    {
        $success = FALSE;
        $this->verifyNodeId($nodeId);

        $logData = array(
            'node_id'=>$nodeId,
            'entity_type'=>$entity->getTypeStr(),
            'unique_id'=>$entity->getUniqueId()
        );

        if ($this->hasFlatTable($entity->getTypeStr())) {
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_DEBUG,
                    'cr_flat',
                    'createFlat - '.$nodeId.' - '.$entity->getTypeStr().' - '.$entity->getUniqueId(),
                    $logData
                );
            $success = $this->replaceFlatFromEav($nodeId, $entity);
        }else{
            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_WARN,
                'cr_no_flat',
                'no flat table for '.$entity->getTypeStr().' - '.$entity->getUniqueId(),
                $logData
            );

        }

        return $success;
    }

    /**
     * @param int $nodeId
     * @param \Entity\Entity $entity
     * @param int $flatEntityId
     * @return bool
     */
    public function updateFlatFromEav($nodeId, \Entity\Entity $entity, $flatEntityIdField = NULL, $entityOnly = FALSE) {
        $success = FALSE;
        $this->verifyNodeId($nodeId);

        if ($flatEntityIdField === NULL) {
            $flatEntity = $entity;
        }else{
            $flatEntityId = $entity->getData($flatEntityIdField);
            $flatEntity = $this->loadEntityId($nodeId, $flatEntityId);
        }
        $flatEntityType = $flatEntity->getTypeStr();

        $logData = array(
            'node_id'=>$nodeId,
            'flat entity_type'=>$flatEntityType,
            'entity_type'=>$entity->getTypeStr(),
            'unique_id'=>$entity->getUniqueId()
        );

        if ($this->hasFlatTable($flatEntityType)) {
            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUG,
                'upd_flat',
                'updateFlat - '.$nodeId.' - '.$entity->getTypeStr().' ('.$flatEntityType.') - '.$entity->getUniqueId(),
                $logData
            );
            $success = $this->replaceFlatFromEav($nodeId, $flatEntity, $entity, $entityOnly);
        }else{
            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_WARN,
                'upd_no_flat',
                'no flat table for '.$entity->getTypeStr().' ('.$flatEntityType.') - '.$entity->getUniqueId(),
                $logData
            );
        }

        return $success;
    }

    /**
     * @param int $nodeId
     * @param int $storeId
     * @param string|NULL $flatEntityType
     * @param string|null $flatUniqueId
     * @param array $fieldsToUpdate
     * @param \Entity\Entity $entity
     * @return bool $success
     * @throws MagelinkException
     * @throws NodeException
     */
    public function updateEavFromFlat($nodeId, $storeId, $flatEntityType, $flatUniqueId,
        array $fieldsToUpdate, $entity = NULL)
    {
        $success = FALSE;
        $this->verifyNodeId($nodeId);

        $logData = array(
            'node_id'=>$nodeId,
            'store_id'=>$storeId,
            'flat entity_type'=>$flatEntityType,
            'flat unique_id'=>$flatUniqueId
        );

        if ($entity === NULL) {
            $isValidEntity = TRUE;
            $entityType = $flatEntityType;
            $where = '';
        }elseif (is_object($entity) && $entity instanceof \Entity\Entity) {
            $isValidEntity = TRUE;
            $entityType = $entity->getTypeStr();
            $where = " AND `".$this->getFlatTableColumn($entityType, 'unique_id')."` = '".$entity->getUniqueId()."'";
        }else{
            $isValidEntity = FALSE;
        }

        if ($flatEntityType === NULL || $flatUniqueId === NULL || !$isValidEntity) {
            $message = 'updateEavFromFlat failed: '.$nodeId.' - '
                .var_export($flatEntityType, TRUE).' ('.var_export($flatUniqueId, TRUE).')';
            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR, 'upd_eav_fl_fail',
                    $message, $logData, array('entity'=>$entity)
            );
            //throw new MagelinkException($message);
        }elseif ($this->hasFlatTable($flatEntityType)) {
            $where = "`".$this->getFlatTableColumn($flatEntityType, 'unique_id')."` = '".$flatUniqueId."'".$where;
            $flatEntityRows = $this->loadFlatEntity($flatEntityType, '*', $where);

            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_DEBUG,
                    'upd_eav_flat', 'updateEavFromFlat - '.$nodeId.' - '.$flatEntityType.' - '.$flatUniqueId,
                    array(
                        'node_id'=>$nodeId,
                        'flat entity_type'=>$flatEntityType,
                        'flat unique_id'=>$flatUniqueId,
                        'entity_type'=>$entityType,
                        'where'=>$where,
                        'flat results count'=>count($flatEntityRows)
                    ),
                    array(
                        'entity'=>$entity,
                        'flatEntityRows'=>$flatEntityRows
                    )
                );

            foreach ($flatEntityRows as $row) {
                $success = TRUE;
                $updateArray = array();
                foreach ($fieldsToUpdate as $field) {
                    $eavDetails = $this->getEntityEavDetails($field);
                    list($entityType, $attributeCode) = each($eavDetails);
                    if (!array_key_exists($entityType, $updateArray)) {
                        $updateArray[$entityType] = array();
                    }
                    $uniqueId = $row[$this->getFlatTableColumn($entityType, 'unique_id')];
                    if (!array_key_exists($uniqueId, $updateArray[$entityType])) {
                        $updateArray[$entityType][$uniqueId] = array();
                    }
                    $updateArray[$entityType][$uniqueId][$attributeCode] = $row[$field];
                }

                foreach($updateArray as $entityType=>$updateEntityData) {
                    foreach ($updateEntityData as $uniqueId=>$updateData) {
                        $entityToUpdate = $this->loadEntity($nodeId, $entityType, $storeId, $uniqueId);
                        $this->updateEntity($nodeId, $entityToUpdate, $updateData);
                    }
                }
            }

            // ToDo : Implement real success control
            if (!$success) {
                $success = FALSE;
            }elseif ($entity !== NULL) {
                $success = $this->reloadEntity($entity);
            }else{
                $success = $success && TRUE;
            }
        }

        return $success;
    }

    /**
     * Creates an entity identifier entry to link the given entity to this node.
     * @param int $nodeId
     * @param \Entity\Entity $entity
     * @param string $localId
     * @throws MagelinkException
     */
    public function linkEntity($nodeId, \Entity\Entity $entity, $localId)
    {
        $this->verifyNodeId($nodeId);

        $existing = $this->getTableGateway('entity_identifier')
            ->select(array('entity_id'=>$entity->getId(), 'node_id'=>$nodeId));
        if ($existing && count($existing)) {
            throw new NodeException('Entity is already linked - '.$entity->getId().' with node ' . $nodeId);
        }

        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG,
                'link',
                'linkEntity - '.$nodeId.' - '.$entity->getId().': '.$localId,
                array('local'=>$localId),
                array('entity'=>$entity, 'node'=>$nodeId)
            );

        $entityIdentifier = $this->getTableGateway('entity_identifier')->insert(array(
            'entity_id'=>$entity->getId(),
            'node_id'=>$nodeId,
            'store_id'=>$entity->getStoreId(),
            'local_id'=>$localId,
        ));
        if (!$entityIdentifier) {
            throw new MagelinkException('Unknown error in linkEntity');
        }
    }
    
    /**
     * Removes the entity identifier entry to unlink the given entity from this node.
     * 
     * @param int $nodeId
     * @param \Entity\Entity $entity
     * @throws MagelinkException
     */
    public function unlinkEntity ($nodeId, \Entity\Entity $entity)
    {
        $this->verifyNodeId($nodeId);

        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG,
                'unlink',
                'unlinkEntity - '.$nodeId.' - '.$entity->getId(),
                array(),
                array('entity'=>$entity, 'node'=>$nodeId)
            );

        $res = $this->getTableGateway('entity_identifier')->delete(array(
            'entity_id'=>$entity->getId(),
            'node_id'=>$nodeId,
        ));
        if(!$res){
            throw new NodeException('Tried to unlink entity that was not linked');
        }
    }

    /**
     * Returns the local ID for the given entity on this node, if it exists.
     *
     * @param $nodeId
     * @param \Entity\Entity|int $entity
     * @return string|null
     */
    public function getLocalId($nodeId, $entity)
    {
        if (is_object($entity)) {
            $entity = $entity->getId();
        }

        $result = $this->getTableGateway('entity_identifier')->select(array(
            'entity_id'=>$entity,
            'node_id'=>$nodeId,
        ));

        $return = NULL;
        foreach ($result as $row) {
            $return = $row['local_id'];
            break;
        }

        return $return;
    }

    /**
     * Retrieves the Local ID for an entity from remote Nodes
     *
     * @param int $nodeId The ID of the node doing the retrieving
     * @param \Entity\Entity $entity The Entity to request an ID for
     * @param string $remote_type What type of Node to retrieve for
     * @param bool $allowMultiple If true, returns all found Local IDs as an array, otherwise simply returns the first.
     * @return int|array|null The local ID found or an array of local IDs found
     */
    public function getRemoteId($nodeId, \Entity\Entity $entity, $remote_type, $allowMultiple = TRUE)
    {
        $this->verifyNodeId($nodeId);

        $nodeRes = $this->getTableGateway('node')->select(array('type'=>$remote_type));
        if(!$nodeRes || !count($nodeRes)){
            return ($allowMultiple ? array() : NULL);
        }

        $nodeIds = array();
        foreach($nodeRes as $row){
            $nodeIds[] = $row['node_id'];
        }

        $ret = array();

        $res = $this->getTableGateway('entity_identifier')->select(array('node_id'=>$nodeIds, 'entity_id'=>$entity->getId()));
        foreach($res as $row){
            if($row['local_id'] == null || $row['local_id'] == ''){
                continue;
            }
            if(!$allowMultiple){
                return $row['local_id'];
            }else{
                $ret[] = $row['local_id'];
            }
        }

        if($allowMultiple){
            return $ret;
        }else{
            return null;
        }
    }

    /**
     * Returns all nodes linked to the given entity along with their local IDs
     *
     * @param \Entity\Entity|int $entity
     * @return string[] Array with keys being Node IDs and values being local IDs
     */
    public function getAllLinks( $entity ){
        if(is_object($entity)){
            $entity = $entity->getId();
        }
        $res = $this->getTableGateway('entity_identifier')->select(array(
            'entity_id'=>$entity,
        ));
        $return = array();
        foreach($res as $row){
            $return[$row['node_id']] = $row['local_id'];
        }
        return $return;
    }
    
    /**
     * Updates the entities updated_at timestamp.
     * If an array of attribute codes is provided, also updates their updated_at timestamps.
     * No update or event entries are created.
     * 
     * @param \Entity\Entity $entity
     * @param string[] $attributes
     */
    public function touchEntity(\Entity\Entity $entity, $attributes = array())
    {
        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG,
                'touch',
                'touchEntity - '.$entity->getId(),
                array(),
                array('entity'=>$entity)
            );
        $this->getSaver()->touchEntity($entity, $attributes);
    }

    /**
     * Update a records parent ID without performing any other actions. Does NOT update updated_at.
     *
     * @param \Entity\Entity|int $child
     * @param \Entity\Entity|int $parent
     * @throws \Magelink\Exception\MagelinkException
     */
    public function setEntityParent( $child, $parent ) {

        if(is_object($parent)){
            if($parent instanceof \Entity\Entity){
                $parent = $parent->getId();
            }else{
                throw new NodeException('Invalid object type ' . get_class($parent) . ' passed to setEntityParent!');
            }
        }
        if(is_object($child)){
            if($child instanceof \Entity\Entity){
                $child = $child->getId();
            }else{
                throw new NodeException('Invalid object type ' . get_class($child) . ' passed to setEntityParent!');
            }
        }
        $this->getSaver()->setEntityParent($child, $parent);
    }
    
    /**
     * Updates the given entity with the provided data.
     * The $merge parameter represents whether the provided data should replace the existing data or be merged into it. If this is set to true all values that are already arrays will have the new data provided appended to the end, and any multi-type attributes will be run through array_merge with the new data taking precedence where keys are the same.
     * Alternatively, $merge can be provided as an associative array with the key being the attribute code, and the value being a boolean representing whether to merge or replace data. When specified individually this allows turning single values into array values. All non-specified keys default to false (i.e. replace only).
     * 
     * @param int $nodeId
     * @param \Entity\Entity $entity
     * @param array $data
     * @param boolean|array $merge
     * @throws MagelinkException
     */
    public function updateEntity($nodeId, \Entity\Entity $entity, $data, $merge = FALSE)
    {
        $this->verifyNodeId($nodeId);
        $allowedAttributes = $this->getServiceLocator()->get('nodeService')
            ->getSubscribedAttributeCodes($nodeId, $entity->getType(), TRUE);

        foreach ($data as $key=>$value) {
            if (strlen(trim($key)) == 0) {
                unset($data[$key]);
                continue;
            }
            if (!in_array($key, $allowedAttributes)) {
                throw new NodeException('Invalid attribute ('.$key.') specified for update.');
            }
        }
        $preData = $data;

        $transformedData = $this->getServiceLocator()->get('routerService')
            ->processTransforms($entity, $data, $nodeId, \Entity\Update::TYPE_UPDATE);
        foreach ($transformedData as $key=>$value) {
            if (is_array($merge) && array_key_exists($key, $merge)) {
                $merge[$key] = FALSE;
            }
            $data[$key] = $value;
        }
        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG,
                'update_tf',
                'updateEntity - transform gave '.count($transformedData).' updates for - '.$entity->getId(),
                array('tfdata'=>$transformedData, 'predata'=>$preData),
                array('entity'=>$entity, 'node'=>$nodeId)
            );

        $attributes = $this->getSaver()->saveEntity($entity, $data, $merge);

        if(!count($attributes)){
            $this->getServiceLocator()->get('logService')->log(
                \Log\Service\LogService::LEVEL_WARN,
                'update_same',
                'updateEntity - All data was the same - '.$nodeId.' - '.$entity->getId(),
                array('data'=>$data),
                array('entity'=>$entity, 'node'=>$nodeId)
            );
            $success = FALSE;
        }else{
            $changedData = array();
            foreach($attributes as $att){
                $changedData[$att] = $data[$att];
            }

            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_INFO,
                    'update',
                    'updateEntity - Keys updated - '.$nodeId.' - '.$entity->getId(),
                    array('updated'=>$attributes, 'keys'=>array_keys($data), 'tfkeys'=>array_keys($transformedData)),
                    array('entity'=>$entity, 'node'=>$nodeId)
                );
            $this->getServiceLocator()->get('routerService')
                ->distributeUpdate($entity, $changedData, $nodeId, \Entity\Update::TYPE_UPDATE);

            $success = TRUE;
        }

        return $success;
    }

    /**
     * Perform a silent update of data to an entity - nothing is logged nor processed. SHOULD NOT BE USED WITHOUT EXTREME REASON.
     *
     * @param \Entity\Entity $entity
     * @param array $data
     * @param array|bool merge
     * @return array
     */
    public function silentUpdateEntity(\Entity\Entity $entity, $data, $merge=false){
        return $this->getSaver()->saveEntity($entity, $data, $merge);
    }

    /**
     * Updates the given Entity's Unique ID to the provided value.
     * Not automatically distributed.
     *
     * @param int $nodeId
     * @param \Entity\Entity $entity
     * @param string $new_unique_id
     */
    public function updateEntityUnique($nodeId, \Entity\Entity $entity, $new_unique_id)
    {
        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_INFO,
                'update_unique',
                'updateEntityUnique - setting ID '.$new_unique_id.' for ' . $entity->getId(),
                array('new_id'=>$new_unique_id),
                array('entity'=>$entity, 'node'=>$nodeId)
            );
        $this->getSaver()->setEntityUnique($entity->getId(), $new_unique_id);
        $this->getSaver()->touchEntity($entity);
    }
    
    /**
     * Deletes the given Entity from the system.
     * Only works if this node is the only one linked to the Entity, or if no nodes are linked to the Entity. If there are remaining links an Exception will be thrown.
     * 
     * @param int $nodeId
     * @param \Entity\Entity $entity
     * @throws MagelinkException If other nodes are linked to this entity, or if invalid data is passed.
     */
    public function deleteEntity ($nodeId, \Entity\Entity $entity)
    {
        $this->verifyNodeId($nodeId);

        $res = $this->getTableGateway('entity_identifier')->select(array(
            'entity_id'=>$entity->getId(),
        ));
        foreach($res as $row){
            if($row['node_id'] == $nodeId){
                continue; // Don't care about this node
            }
            throw new NodeException('Cannot delete entity ' . $entity->getId() . ' - still linked to node ' . $row['node_id']);
        }

        $this->getServiceLocator()->get('routerService')
            ->processTransforms($entity, $entity->getAllSetData(), $nodeId, \Entity\Update::TYPE_DELETE);
        $this->getServiceLocator()->get('routerService')
            ->distributeUpdate($entity, array(), $nodeId, \Entity\Update::TYPE_DELETE);
        $this->unlinkEntity($nodeId, $entity);

        $this->getSaver()->deleteEntity($entity);

    }
    
    /**
     * Dispatch an action on a provided entity.
     *
     * @param int $nodeId
     * @param \Entity\Entity $entity
     * @param string $action_type
     * @param array $actionData
     * @return bool indicator, if the action was successful
     */
    public function dispatchAction($nodeId, \Entity\Entity $entity, $actionType, $actionData)
    {
        $this->verifyNodeId($nodeId);

        return $this->getServiceLocator()->get('routerService')
            ->distributeAction($entity, $nodeId, $actionType, $actionData);
    }

    /**
     * Load all assigned Entity Comments for a given Entity
     * @param \Entity\Entity $entity
     * @return \Entity\Comment[]
     */
    public function loadEntityComments(\Entity\Entity $entity)
    {
        $entity_comments = $this->getTableGateway('entity_comment')
            ->select(array('entity_id'=>$entity->getId()));

        $comments = array();
        foreach ($entity_comments as $row) {
            $comments[] = new \Entity\Comment($entity, (array) $row);
        }

        return $comments;
    }

    /**
     * Loads a specfic entity comments for a given entity, defined prefix and order
     * @param \Entity\Entity $entity
     * @param $beginsWith
     * @param bool $getFirst
     * @return \Entity\Comment
     */
    protected function loadSpecficEntityComment(\Entity\Entity $entity, $beginsWith, $getFirst = TRUE)
    {
        $where = "entity_id = ".$entity->getId()." AND LOWER(body) LIKE '".$beginsWith."%'"
            ." ORDER BY comment_id ".($getFirst ? 'ASC' : 'DESC')." LIMIT 1;";
        $entity_comments = $this->getTableGateway('entity_comment')
            ->select($where);

        $comment = '';
        foreach ($entity_comments as $entityComment) {
            $entityComment = new \Entity\Comment($entity, (array) $entityComment);
            if (is_object($entityComment)) {
                $comment = trim(substr($entityComment->getBody(), strlen($beginsWith)));
            }
            break;
        }

        return $comment;
    }

    /**
     * Load first customer comments for a given entity
     * @param \Entity\Entity $entity
     * @return \Entity\Comment
     */
    public function loadEntityCustomerComment(\Entity\Entity $entity)
    {
        $customerComment = $this->loadSpecficEntityComment($entity, Comment::CUSTOMER_COMMENT_PREFIX);
        return $customerComment;
    }

    /**
     * Load last admin entity comments for a given entity
     * @param \Entity\Entity $entity
     * @return \Entity\Comment
     */
    public function loadEntityAdminComment(\Entity\Entity $entity)
    {
        $adminComment = $this->loadSpecficEntityComment($entity, Comment::ADMIN_COMMENT_PREFIX, FALSE);

        return $adminComment;
    }

    /**
     * Create a new Entity Comment
     *
     * @param \Entity\Entity $entity The Entity to attach the new comment to
     * @param string $source A description of where this comment came from (user name, automated process name, etc)
     * @param string $title The comment title
     * @param string $body The comment body
     * @param string $referenceId The entity-specific reference ID to compare this comment (optional)
     * @param bool $customerVisible Whether this comment should be visible to the customer (optional, default false)
     * @param int|bool $nodeId The node ID of the creating node
     * @throws MagelinkException If we fail to create the comment
     * @return \Entity\Comment
     */
    public function createEntityComment(\Entity\Entity $entity, $source, $title, $body,
        $referenceId = '', $customerVisible = FALSE, $nodeId = FALSE)
    {
        $row = array(
            'entity_id'=>$entity->getId(),
            'reference_id'=>$referenceId,
            'timestamp'=>date('Y-m-d H:i:s'),
            'source'=>$source,
            'title'=>$title,
            'body'=>$body,
            'customer_visible'=>($customerVisible ? 1 : 0),
        );

        $res = $this->getTableGateway('entity_comment')->insert($row);
        $row['comment_id'] = $this->getAdapter()->getDriver()->getLastGeneratedValue();

        if(!$res || !$row['comment_id']){
            throw new MagelinkException('Error creating entity comment for ' . $entity->getId());
        }

        if ($nodeId) {
            $this->dispatchAction(
                $nodeId,
                $entity, 'comment',
                array(
                    'source'=>$source,
                    'title'=>$title,
                    'body'=>$body,
                    'customer_visible'=>$customerVisible,
                    'timestamp'=>date('Y-m-d H:i:s'),
                    'comment_id'=>$row['comment_id']
                )
            );
        }

        return new \Entity\Comment($entity, $row);
    }

    /**
     * @param string $method
     * @param string $amount
     * @param string $ccType
     * @return array
     */
    public function convertPaymentData($method, $amount, $ccType = '')
    {
        if (is_numeric($method) && !is_numeric($amount)) {
            list($method, $amount) = array($amount, $method);
        }

        $methodCcType = $method.($ccType ? '{{'.$ccType.'}}' : '');
        $payments = array($methodCcType => $amount);

        return $payments;
    }

    protected function getMethodCcType($extendMethod)
    {
        $findCcTypeRegEx = '#{{([^}]+)}}#ism';
        preg_match($findCcTypeRegEx, $extendMethod, $ccType);

        if ($ccType) {
            $ccType = $ccType[1];
            $method = str_replace('{{'.$ccType.'}}', '', $extendMethod);
        }else{
            $method = $extendMethod;
            $ccType = '';
        }

        return array('method' => $method, 'ccType' => $ccType);
    }

    /**
     * @param \Entity\Entity $order
     * @param string $dataType
     * @return array $paymentData
     */
    protected  function getPaymentData(array $paymentMethod, $dataType)
    {
        if (is_string($dataType)) {
            $paymentData = array();
            foreach ($paymentMethod as $extendMethod => $amount) {
                extract($this->getMethodCcType($extendMethod));
                if (in_array($dataType, array('method', 'ccType', 'amount'))) {
                    $paymentData[$extendMethod] = $$dataType;
                }
            }
        }

        return $paymentData;
    }

    /**
     * @param \Entity\Entity $order
     * @return array $ccTypes
     */
    public function getCcTypes(array $paymentMethod)
    {
        return $this->getPaymentData($paymentMethod, 'ccType');
    }

    /**
     * @param \Entity\Entity $order
     * @return array $methods
     */
    public function getPaymentMethods($paymentMethod)
    {
        if (is_array($paymentMethod)) {
           $methods = $this->getPaymentData($paymentMethod, 'method');
        }else{
            $methods = array();
        }

        return $methods;
    }

    /**
     * @param \Entity\Entity $order
     * @return array
     */
    public function getPaymentAmounts(array $paymentMethod)
    {
        return $this->getPaymentData($paymentMethod, 'amount');
    }

    /**
     * Parse a MLQL query (for debugging)
     * @param string $mlql The MLQL to be parsed (see separate MLQL docs)
     * @return array
     */
    public function parseQuery($mlql){
        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUG, 'parsemlql', 'parseQuery: ' . $mlql, array('query'=>$mlql));
        return $this->getQuerier()->parseQuery($mlql);
    }

    /**
     * Execute a MLQL query and return all rows as associative arrays
     * @param string $mlql The MLQL to be executed (see separate MLQL docs)
     * @throws MagelinkException If the MLQL is invalid or contains a syntax error
     * @return array
     */
    public function executeQuery($mlql)
    {
        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG, 'execmlql', 'executeQuery: '.$mlql, array('query'=>$mlql));
        try{
            $resp = $this->getQuerier()->executeQuery($mlql);
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_DEBUG,
                    'execmlql_r',
                    'Result: '.var_export($resp, true),
                    array('ret'=>$resp)
                );
            return $resp;
        }catch(\Exception $e){
            throw new MagelinkException('Error executing MLQL: ' . $mlql, 0, $e);
        }
    }

    /**
     * Executes a SQL query and returns all rows as associative arrays
     * Implemented for fast sql execution for time critical actions
     *
     * @param string $sql The SQL to be executed (see separate MLQL docs)
     * @throws MagelinkException If the MLQL is invalid or contains a syntax error
     * @return array
     */
    public function executeSqlQuery($sql)
    {
        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG, 'execsql', 'executeQuery: '.$sql, array('query'=>$sql));
        try{
            $response = $this->getQuerier()->executeSqlQuery($sql);
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_DEBUG,
                    'execsql_r',
                    'Result: '.var_export($response, TRUE),
                    array('return'=>$response)
                );
        }catch(\Exception $exception){
            throw new MagelinkException('Error executing SQL: '.$sql, 0, $exception);
            $response = NULL;
        }

        return $response;
    }

    /**
     * Execute a MLQL query and return the first column as an array
     * @param string $mlql The MLQL to be executed (see separate MLQL docs)
     * @throws MagelinkException If the MLQL is invalid or contains a syntax error
     * @return array
     */
    public function executeQueryColumn($mlql){
        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUG, 'execmlql_col', 'executeQueryColumn: ' . $mlql, array('query'=>$mlql));
        try{
            $data = $this->getQuerier()->executeQuery($mlql);
        }catch(\Exception $e){
            throw new MagelinkException('Error executing MLQL: ' . $mlql, 0, $e);
        }
        $ret = array();
        foreach($data as $row){
            $ret[] = array_shift($row);
        }
        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUG, 'execmlql_col_r', 'Result: ' . var_export($ret, true), array('ret'=>$ret));
        return $ret;
    }

    /**
     * Execute a MLQL query and return an associative array using the result columns k and v
     * @param string $mlql The MLQL to be executed (see separate MLQL docs)
     * @throws MagelinkException If the MLQL is invalid or contains a syntax error
     * @return array
     */
    public function executeQueryAssoc($mlql)
    {
        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG,
                'execmlql_assoc',
                'executeQueryAssoc: '.$mlql,
                array('query'=>$mlql)
            );
        try{
            $data = $this->getQuerier()->executeQuery($mlql);
        }catch(\Exception $exception){
            throw new MagelinkException('Error executing MLQL: '.$mlql, 0, $exception);
        }

        $returnData = array();
        foreach ($data as $row) {
            if (array_key_exists('k', $row) && array_key_exists('v', $row)) {
                $returnData[$row['k']] = $row['v'];
            }else{
                $message = 'Error during the data assignment. Row does not have k and/or v  as keys: '
                    .var_export(array_keys($row), TRUE).'.';
                $this->getServiceLocator()->get('logService')
                    ->log(\Log\Service\LogService::LEVEL_ERROR,
                        'execmlql_assoc_r',
                        $message,
                        array('results so far'=>$returnData)
                    );
                break;
            }
        }

        if (count($returnData)) {
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_DEBUG,
                    'execmlql_assoc_r',
                    'Result: '.var_export($returnData, TRUE),
                    array('result'=>$returnData)
                );
        }

        return $returnData;
    }

    /**
     * Execute a MLQL query and return the first column of the first row
     * @param string $mlql The MLQL to be executed (see separate MLQL docs)
     * @throws MagelinkException If the MLQL is invalid or contains a syntax error
     * @return mixed|null
     */
    public function executeQueryScalar($mlql){
        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUG, 'execmlql_sca', 'executeQueryScalar: ' . $mlql, array('query'=>$mlql));
        try{
            $resp = $this->getQuerier()->executeQueryScalar($mlql);
            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUG, 'execmlql_sca_r', 'Result: ' . var_export($resp, true), array('ret'=>$resp));
            return $resp;
        }catch(\Exception $e){
            throw new MagelinkException('Error executing MLQL: ' . $mlql, 0, $e);
        }
    }

    /**
     * Execute a MLQL query and return an array of entities - the query must return a column "entity_id".
     *
     * If any of the entities cannot be loaded, that array entry will be null.
     * The query may optionally return a column "key" which will be used as the array key.
     *
     * @param int $nodeId The ID of the node executing this query
     * @param string $mlql The MLQL to be executed (see separate MLQL docs)
     * @throws MagelinkException If the MLQL is invalid or contains a syntax error
     * @return \Entity\Entity[]
     */
    public function executeQueryEntities($nodeId, $mlql)
    {
        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG,
                'execmlql_col',
                'executeQueryColumn: '.$mlql,
                array('query'=>$mlql)
            );

        try{
            $data = $this->getQuerier()->executeQuery($mlql);
        }catch(\Exception $e){
            throw new MagelinkException('Error executing MLQL: ' . $mlql, 0, $e);
        }
        $ret = array();
        $ids = array();
        foreach($data as $row){
            $id = intval($row['entity_id']);
            $ids[] = $id;
            $ent = $this->loadEntityId($nodeId, $id);
            if(isset($row['key'])){
                $ret[$row['key']] = $ent;
            }else{
                $ret[] = $ent;
            }
        }
        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUG,
                'execmlql_col_r',
                'Result: '.var_export($ids, true),
                array('ids'=>$ids)
            );

        return $ret;
    }

    /**
     * Verify that given node ID is valid (and transform as needed)
     * 
     * @param int $nodeId The node ID to process (by-reference)
     * @throws MagelinkException If the passed node ID is invalid
     * @return int The processed node ID
     */
    protected function verifyNodeId(&$node)
    {
        $nodeId = $node;
        if ($nodeId === 0) {
            // Bypass
        }else{
            if ($node instanceof \Node\Entity\Node) {
                $nodeId = $node = $node->getId();
            }elseif ($node instanceof \Node\AbstractNode) {
                $nodeId = $node = $node->getNodeId();
            }

            if ($nodeId <= 0 || !is_int($nodeId)) {
                throw new \Magelink\Exception\NodeException('Invalid node ID passed to EntityService');
                $nodeId = NULL;
            }
        }

        return $nodeId;
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
        $entityTypePassed = $entityTypeId = $entityType;
        if (is_string($entityType)) {
            $entityTypeId = $entityType = $this->getServiceLocator()->get('entityConfigService')
                ->parseEntityType($entityType);
        }

        if ($entityTypeId <= 0 || !is_int($entityTypeId)) {
            $message = 'Invalid entity type passed to EntityService - '.$entityTypePassed.' - '.$entityType;
            throw new \Magelink\Exception\NodeException($message);
            $entityTypeId = NULL;
        }

        return $entityTypeId;
    }

    /**
     * @param int|string $entityType
     * @return bool $hasFlatTable|string $entityType
     * @throws NodeException
     */
    protected function hasFlatTable($entityType)
    {
        try{
            $entityTypeId = $this->verifyEntityType($entityType);
            if ($entityTypeId) {
                $flatEntityTypes = $this->getServiceLocator()->get('entityConfigService')->getFlatEntityTypeCodes();
                $flatEntityTypeIds = array_flip($flatEntityTypes);
                $hasFlatTable = in_array($entityTypeId, $flatEntityTypeIds);
            }
        }catch (\Exception $exception) {
            $hasFlatTable = FALSE;
            throw new $exception;
        }

        return $hasFlatTable ? $flatEntityTypes[$entityTypeId] : $hasFlatTable;
    }

    /**
     * Get flat table column name from entity type and attribute
     * @param string $entityType
     * @param string $code
     * @return bool|string $flatTableColumn
     */
    public function getFlatTableColumn($entityType, $eavCode)
    {
        if (is_int($entityType)) {
            $entityTypeId = $this->verifyEntityType($entityType);
            if ($entityTypeId) {
                $entityTypes = $this->getServiceLocator()->get('entityConfigService')->getEntityTypeCodes();
                $entityType = isset($entityTypes[$entityTypeId]) ? $entityTypes[$entityTypeId] : FALSE;
            }
        }

        if (is_string($entityType) && trim($entityType)) {
            $flatTableColumn = $entityType.'_'.strtolower($eavCode);
        }else{
            $flatTableColumn = FALSE;
        }

        return $flatTableColumn;
    }

    /**
     * Get entity type-attribute code array from flat table column
     * @param $flatColumn
     * @return array
     */
    public function getEntityEavDetails($flatColumn)
    {
        $entityType = strtok($flatColumn, '_');
        $attributeCode = strtok('');

        if (!trim($entityType) || !trim($attributeCode)) {
            $entityType = $attributeCode = '';
        }

        return array($entityType=>$attributeCode);
    }

    /**
     * Return the database adapter to be used to communicate with Entity storage.
     * @return \Zend\Db\Adapter\Adapter
     */
    protected function getAdapter()
    {
        return $this->getServiceLocator()->get('zend_db');
    }

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
        if (isset($this->_tgCache[$table])) {
            return $this->_tgCache[$table];
        }
        $this->_tgCache[$table] = new TableGateway($table, $this->getServiceLocator()->get('zend_db'));

        return $this->_tgCache[$table];
    }

}
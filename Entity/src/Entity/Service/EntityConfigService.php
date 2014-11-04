<?php
/**
 * Entity\Service
 *
 * @category Entity
 * @package Entity\Service
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Entity\Service;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Db\TableGateway\TableGateway;


class EntityConfigService implements ServiceLocatorAwareInterface
{
    /** @var ServiceLocatorInterface $_serviceLocator */
    protected $_serviceLocator;

    /** Cache for checkAttribute
     * @see EntityConfigService::checkAttribute()
     * @var array
     */
    protected $_checkAttributeCache = array();

    /** @var TableGateway[]  Cache of preloaded table gateways */
    protected $_tgCache = array();

    /** Local cache of entity type lookups, key is name and value is ID
     * @see EntityConfigService::parseEntityType()
     * @var array
     */
    protected $_entityTypeCache = array();

    /** @var array $_attributeCache  Cache for parseAttribute */
    protected $_attributeCache = array();

    /** @var array $_attributeRevCache  Cache for getAttribute */
    protected $_attributeRevCache = array();

    /** @var array $entityAttributeTypes */
    protected static $entityAttributeTypes = array(
        'varchar',
        'int',
        'multi',
        'decimal',
        'text',
        'fkey',
        'datetime',
        'entity'
    );

    /**
     * Get static entity fields (uppercase)
     * @return array
     */
    public function getStaticFields()
    {
        return array(
            'ENTITY_ID',
            'UNIQUE_ID',
            'STORE_ID',
            'PARENT_ID',
            'UPDATED_AT'
        );
    }

    /**
     * Set up a new attribute within the system.
     * Will return gracefully if the attribute already exists with matching data, otherwise will throw an exception.
     * @param string $code
     * @param string $name
     * @param boolean $metadata
     * @param string $type
     * @param int|string $entityType
     * @param string $comment
     * @throws \Magelink\Exception\MagelinkException If an error occurs during creation
     * @return int New attribute ID
     */
    public function createAttribute($code, $name, $metadata, $type, $entityType, $comment = NULL)
    {
        $entityType = $this->parseEntityType($entityType);
        if (!$entityType) {
            throw new \Magelink\Exception\MagelinkException('Invalid entity type for new attribute '.$code);
        }

        $code = strtolower($code);
        if($this->checkAttribute($entityType, $code)){
            throw new \Magelink\Exception\MagelinkException('Attribute already exists '.$code);
        }

        if ($type == 'bool' || $type == 'boolean') {
            $type = 'int';
        }

        if (!in_array($type, self::entityAttributeTypes)) {
            throw new \Magelink\Exception\MagelinkException('Invalid attribute type for '.$code.' - '.$type);
        }

        $result = $this->getTableGateway('entity_attribute')->insert(array(
            'entity_type_id'=>$entityType,
            'type'=>$type,
            'metadata'=>($metadata ? 1 : 0),
            'code'=>$code,
            'name'=>$name,
            'comment'=>$comment
        ));

        if (!$result) {
            throw new \Magelink\Exception\MagelinkException('Unknown error creating attribute '.$code);
        }

        return $this->getAdapter()->getDriver()->getLastGeneratedValue();
    }

    /**
     * Set up a new entity type within the system. Will return gracefully if the entity type already exists with matching data, otherwise will throw an exception.
     * @param string $name
     * @param string $humanName
     * @param boolean $internal
     * @param boolean|string $flatTableFields (comma-separated list)
     * @throws \Magelink\Exception\MagelinkException If the entity type already exists
     */
    public function createEntityType($name, $humanName, $internal = FALSE, $flatTableFields = FALSE)
    {
        $name = strtolower($name);

        if ($this->parseEntityType($name)) {
            throw new \Magelink\Exception\MagelinkException('Conflicting entity type name - '.$name);
        }

        $internal = ($internal ? 1 : 0);
        $flatTableFields = (trim($flatTableFields) ? $flatTableFields : '');

        $result = $this->getTableGateway('entity_type')->insert(array(
            'name'=>$name,
            'name_human'=>$humanName,
            'internal'=>$internal,
            'flat_table_fields'=>$flatTableFields
        ));

        if (!$result) {
            throw new \Magelink\Exception\MagelinkException('Unknown error creating entity type '.$name);
        }

        if (isset($this->_entityTypeCache[$name])) {
            unset($this->_entityTypeCache[$name]);
        }
    }

    /**
     * Removes an attribute from the system. Only works if no node is assigned to this attribute.
     * In almost all cases you want to use NodeService->unsubscribeAttribute instead of this.
     * Relies on foreign keys to remove actual data and related associations.
     * @param string $code
     * @throws \Magelink\Exception\MagelinkException
     */
    public function destroyAttribute($code)
    {
        $code = strtolower($code);

        // Ensure that attribute exists
        $searchRes = $this->getTableGateway('entity_attribute')->select(array('code'=>$code));
        if(!$searchRes || !count($searchRes)){
            throw new \Magelink\Exception\MagelinkException('Attribute '.$code.' does not exist so cannot be destroyed!');
        }

        // Fetch attribute ID
        $att_id = false;
        foreach($searchRes as $row){
            $att_id = $row['attribute_id'];
        }
        if($att_id == false){
            throw new \Magelink\Exception\MagelinkException('Error fetching attribute ID for '.$code);
        }

        // Ensure no attributes are subscribed
        $links = $this->getTableGateway('node_attribute')->select(array('attribute_id'=>$att_id));
        if($links && count($links)){
            throw new \Magelink\Exception\MagelinkException('Attribute '.$code.' cannot be destroyed as it is still linked to nodes.');
        }

        // Delete attribute - FKs should clean up
        $this->getTableGateway('entity_attribute')->delete(array('attribute_id'=>$att_id));

    }

    /**
     * Returns true if an attribute by the provided code exists. Includes extensive caching so this can be called regularly with little overhead.
     * @param int $entityTypeId
     * @param string $code
     * @return boolean
     */
    public function checkAttribute ( $entityTypeId, $code ) {
        if(!is_int($entityTypeId)){
            $entityTypeId = $this->parseEntityType($entityTypeId);
        }
        if(!isset($this->_checkAttributeCache[$entityTypeId])){
            $this->_checkAttributeCache[$entityTypeId] = array();
        }
        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA, 'ca', 'checkAttribute - '.$entityTypeId.' - '.$code, array('code'=>$code, 'type'=>$entityTypeId));
        $code = strtolower($code);
        if(isset($this->_checkAttributeCache[$entityTypeId][$code])){
            return $this->_checkAttributeCache[$entityTypeId][$code];
        }

        $result = $this->getTableGateway('entity_attribute')->select(array('code'=>$code, 'entity_type_id'=>$entityTypeId));

        $result = ($result && count($result));

        $this->_checkAttributeCache[$entityTypeId][$code] = $result;
        return $result;
    }

    /**
     * Return all attributes assigned to the provided entity type.
     * @param int|string $entityType
     * @return string[]
     */
    public function getAttributesCode($entityTypeOrId)
    {
        $entityTypeId = $this->parseEntityType($entityTypeOrId);

        $dbRows = $this->getTableGateway('entity_attribute')
            ->select(array('entity_type_id'=>$entityTypeId));

        $attributes = array();
        foreach ($dbRows as $row) {
            $attributes[$row['attribute_id']] = $row['code'];
        }

        return $attributes;
    }

    /**
     * Return all entity types data
     * @return array
     */
    protected function getEntityTypesData()
    {
        $dbRows = $this->getTableGateway('entity_type')
            ->select();

        $entityTypes = array();
        foreach ($dbRows as $row) {
            $entityTypes[$row['entity_type_id']] = $row;
        }

        return $entityTypes;
    }

    /**
     * Return all entity type code
     * @return array
     */
    public function getEntityTypeCodes()
    {
        $entityTypes = array();
        foreach ($this->getEntityTypesData() as $entityTypeId=>$typeData) {
            $entityTypes[$entityTypeId] = $typeData['name'];
        }

        return $entityTypes;
    }

    /**
     * Return all entity types with flat data
     * @return array $entityTypes
     */
    public function getFlatEntityTypeCodes()
    {
        $entityTypes = array();
        foreach ($this->getEntityTypesData() as $entityTypeId=>$typeData) {
            if ($typeData['flat_table_fields']) {
                $entityTypes[$entityTypeId] = $typeData['name'];
            }
        }

        return $entityTypes;
    }

    /**
     * Return all entity types with flat types to be updated
     * @return array $entityTypes
     */
    public function getEntityTypeFlatUpdateCodes()
    {
        $entityTypes = array();
        foreach ($this->getEntityTypesData() as $entityTypeId=>$typeData) {
            if ($typeData['flat_types_to_update']) {
                $entityTypes[$entityTypeId] = explode(',', $typeData['flat_types_to_update']);
            }
        }

        return $entityTypes;
    }

    /**
     * Return all entity flat data fields
     * @return array $flatFields
     */
    public function getFlatEntityTypeFields($entityType = NULL)
    {
        $flatFields = array();
        foreach ($this->getEntityTypesData() as $entityTypeId=>$typeData) {
            if ($typeData['flat_table_fields']) {
                $flatFields[$entityTypeId] = explode(',', $typeData['flat_table_fields']);
            }
        }

        if ($entityType) {
            $entityTypeId = $this->parseEntityType($entityType);
            if (isset($flatFields[$entityTypeId])) {
                $flatFields = $flatFields[$entityTypeId];
            }else{
                $flatFields = array();
            }
        }

        return $flatFields;
    }

    /**
     * Turn a string or object based entity type into the ID
     * @param string $entityType
     * @throws \Magelink\Exception\MagelinkException
     * @return int
     */
    public function parseEntityType($entityType)
    {
        //$this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA, 'pet', 'parseEntityType - '.$entityType, array('entity_type'=>$entityType));
        if(is_object($entityType)){
            if($entityType instanceof \Entity\Entity){
                $entityType = $entityType->getType();
            }else{
                throw new \Magelink\Exception\MagelinkException('Unknown entity type type');
            }
        }
        if(is_numeric($entityType)){
            $entityType = intval($entityType);
        }
        if(is_int($entityType)){
            //$this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA, 'pet_dbg', 'ET was already int - '.$entityType, array('entity_type'=>$entityType));
            return $entityType;
        }
        if(is_string($entityType)){
            $entityType = strtolower($entityType);
            if(isset($this->_entityTypeCache[$entityType])){
                //$this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA, 'pet_dbg', 'ET was cached - '.$entityType.' - '.$this->_entityTypeCache[$entityType], array('entity_type'=>$entityType));
                return $this->_entityTypeCache[$entityType];
            }
            $result = $this->getTableGateway('entity_type')->select(array('name'=>$entityType));
            if(!$result || !count($result)){
                $this->getServiceLocator()->get('logService')
                    ->log(\Log\Service\LogService::LEVEL_ERROR,
                        'pet_dbg',
                        'Could not find in DB - '.$entityType,
                        array('entity_type'=>$entityType)
                    );
                $this->_entityTypeCache[$entityType] = false;
                return false;
            }
            foreach($result as $row){
                //$this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA, 'pet_dbg', 'ET in DB - '.$entityType.' - '.$row['entity_type_id'], array('entity_type'=>$entityType, 'row'=>$row));
                $this->_entityTypeCache[$entityType] = intval($row['entity_type_id']);
                return intval($row['entity_type_id']);
            }
            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR, 'pet_dbg', 'DB iterate failed weirdly - '.$entityType, array('entity_type'=>$entityType));
            $this->_entityTypeCache[$entityType] = false;
            return false;
        }
        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR, 'pet_dbg', 'Unknown type of input - '.$entityType, array('entity_type'=>$entityType));
        return false;
    }

    /**
     * Get the attribute ID for a code/type.
     * @param string $attribute_code
     * @param int $entityType
     * @return array|null
     */
    public function parseAttribute($attribute_code, $entityType)
    {
        $entityType = $this->parseEntityType($entityType);
        if(isset($this->_attributeCache[$entityType])){
            if(isset($this->_attributeCache[$entityType][$attribute_code])){
                return $this->_attributeCache[$entityType][$attribute_code];
            }
        }else{
            $this->_attributeCache[$entityType] = array();
        }

        $res = $this->getTableGateway('entity_attribute')->select(array(
            'entity_type_id'=>$entityType,
            'code'=>$attribute_code,
        ));

        foreach($res as $row){
            $this->_attributeCache[$entityType][$attribute_code] = $row['attribute_id'];
            return $row['attribute_id'];
        }

        $this->_attributeCache[$entityType][$attribute_code] = null;
        return null;
    }

    /**
     * Get an array of attribute data by ID
     * @param $attribute_id
     * @return array|null
     */
    public function getAttribute($attributeId)
    {
        if (!isset($this->_attributeRevCache[$attributeId])) {
            $this->_attributeRevCache[$attributeId] = NULL;

            $dbRows = $this->getTableGateway('entity_attribute')
                ->select(array('attribute_id'=>$attributeId,));

            foreach ($dbRows as $row) {
                $this->_attributeRevCache[$attributeId] = $row;
                break;
            }
        }

        return $this->_attributeRevCache[$attributeId];
    }

    /**
     * Turn an integer entity type into the string variant
     * @param int $entityType
     * @return string|false
     */
    public function parseEntityTypeReverse($entityType)
    {
        if (is_int($entityType)) {
            $entityTypeId = $entityType;
        }else{
            $entityTypeId = $this->parseEntityType($entityType);
        }
        foreach($this->_entityTypeCache as $code=>$id){
            if($id == $entityType){
                return $code;
            }
        }

        $result = $this->getTableGateway('entity_type')
            ->select(array('entity_type_id'=>$entityTypeId));
        $return = FALSE;
        if ($result) {
            foreach ($result as $row) {
                $return = $row['name'];
                break;
            }
        }

        return $return;
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
        
}
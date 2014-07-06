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

/**
 * The EntityConfigService is responsible for managing attributes and entity types, as well as providing name->id translation services for other services & modules.
 *
 * @package Entity\Service
 */
class EntityConfigService implements ServiceLocatorAwareInterface {

    /**
     * Set up a new attribute within the system. Will return gracefully if the attribute already exists with matching data, otherwise will throw an exception.
     * @param string $code
     * @param string $name
     * @param boolean $metadata
     * @param string $type
     * @param int|string $entity_type
     * @param string $comment
     * @throws \Magelink\Exception\MagelinkException If an error occurs during creation
     * @return int New attribute ID
     */
    public function createAttribute ( $code, $name, $metadata, $type, $entity_type, $comment = null ) {
        $entity_type = $this->parseEntityType($entity_type);
        if(!$entity_type){
            throw new \Magelink\Exception\MagelinkException('Invalid entity type for new attribute ' . $code);
        }

        $code = strtolower($code);
        if($this->checkAttribute($entity_type, $code)){
            throw new \Magelink\Exception\MagelinkException('Attribute already exists ' . $code);
        }

        if($type == 'bool' || $type == 'boolean'){
            $type = 'int';
        }

        if(!in_array($type, array(
            'varchar',
            'int',
            'multi',
            'decimal',
            'text',
            'fkey',
            'datetime',
            'entity'
        ))){
            throw new \Magelink\Exception\MagelinkException('Invalid attribute type for ' . $code . ' - ' . $type);
        }

        $res = $this->getTableGateway('entity_attribute')->insert(array(
            'entity_type_id'=>$entity_type,
            'type'=>$type,
            'metadata'=>($metadata ? 1 : 0),
            'code'=>$code,
            'name'=>$name,
            'comment'=>$comment
        ));

        if(!$res){
            throw new \Magelink\Exception\MagelinkException('Unknown error creating attribute ' . $code);
        }

        return $this->getAdapter()->getDriver()->getLastGeneratedValue();

    }

    /**
     * Set up a new entity type within the system. Will return gracefully if the entity type already exists with matching data, otherwise will throw an exception.
     * @param string $name
     * @param string $human_name
     * @param boolean $internal
     * @throws \Magelink\Exception\MagelinkException If the entity type already exists
     */
    public function createEntityType ( $name, $human_name, $internal = false ) {
        $name = strtolower($name);

        if($this->parseEntityType($name)){
            throw new \Magelink\Exception\MagelinkException('Conflicting entity type name - ' . $name);
        }

        $res = $this->getTableGateway('entity_type')->insert(array('name'=>$name, 'name_human'=>$human_name, 'internal'=>($internal ? 1 : 0)));

        if(!$res){
            throw new \Magelink\Exception\MagelinkException('Unknown error creating entity type ' . $name);
        }

        if(isset($this->_entityTypeCache[$name])){
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
    public function destroyAttribute ( $code ) {
        $code = strtolower($code);

        // Ensure that attribute exists
        $searchRes = $this->getTableGateway('entity_attribute')->select(array('code'=>$code));
        if(!$searchRes || !count($searchRes)){
            throw new \Magelink\Exception\MagelinkException('Attribute ' . $code . ' does not exist so cannot be destroyed!');
        }

        // Fetch attribute ID
        $att_id = false;
        foreach($searchRes as $row){
            $att_id = $row['attribute_id'];
        }
        if($att_id == false){
            throw new \Magelink\Exception\MagelinkException('Error fetching attribute ID for ' . $code);
        }

        // Ensure no attributes are subscribed
        $links = $this->getTableGateway('node_attribute')->select(array('attribute_id'=>$att_id));
        if($links && count($links)){
            throw new \Magelink\Exception\MagelinkException('Attribute ' . $code . ' cannot be destroyed as it is still linked to nodes.');
        }

        // Delete attribute - FKs should clean up
        $this->getTableGateway('entity_attribute')->delete(array('attribute_id'=>$att_id));

    }

    /**
     * Cache for checkAttribute
     * @see EntityConfigService::checkAttribute()
     * @var array
     */
    protected $_checkAttributeCache = array();

    /**
     * Returns true if an attribute by the provided code exists. Includes extensive caching so this can be called regularly with little overhead.
     * @param int $entity_type_id
     * @param string $code
     * @return boolean
     */
    public function checkAttribute ( $entity_type_id, $code ) {
        if(!is_int($entity_type_id)){
            $entity_type_id = $this->parseEntityType($entity_type_id);
        }
        if(!isset($this->_checkAttributeCache[$entity_type_id])){
            $this->_checkAttributeCache[$entity_type_id] = array();
        }
        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA, 'ca', 'checkAttribute - ' . $entity_type_id . ' - ' . $code, array('code'=>$code, 'type'=>$entity_type_id));
        $code = strtolower($code);
        if(isset($this->_checkAttributeCache[$entity_type_id][$code])){
            return $this->_checkAttributeCache[$entity_type_id][$code];
        }

        $result = $this->getTableGateway('entity_attribute')->select(array('code'=>$code, 'entity_type_id'=>$entity_type_id));

        $result = ($result && count($result));

        $this->_checkAttributeCache[$entity_type_id][$code] = $result;
        return $result;
    }

    /**
     * Return all attributes assigned to the provided entity type.
     * @param int|string $entity_type
     * @return string[]
     */
    public function getAttributes($entityTypeOrId)
    {
        $entityTypeId = $this->parseEntityType($entityTypeOrId);

        $dbRows = $this->getTableGateway('entity_attribute')
            ->select(array('entity_type_id'=>$entityTypeId,));

        $attributes = array();
        foreach ($dbRows as $row) {
            $attributes[$row['attribute_id']] = $this->_attributeRevCache[$row['attribute_id']] =  $row['code'];
        }

        return $attributes;
    }

    /**
     * Local cache of entity type lookups, key is name and value is ID
     * @see EntityConfigService::parseEntityType()
     * @var array
     */
    protected $_entityTypeCache = array();

    /**
     * Turn a string or object based entity type into the ID
     * @param string $entity_type
     * @throws \Magelink\Exception\MagelinkException
     * @return int
     */
    public function parseEntityType($entity_type)
    {
        //$this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA, 'pet', 'parseEntityType - ' . $entity_type, array('entity_type'=>$entity_type));
        if(is_object($entity_type)){
            if($entity_type instanceof \Entity\Entity){
                $entity_type = $entity_type->getType();
            }else{
                throw new \Magelink\Exception\MagelinkException('Unknown entity type type');
            }
        }
        if(is_numeric($entity_type)){
            $entity_type = intval($entity_type);
        }
        if(is_int($entity_type)){
            //$this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA, 'pet_dbg', 'ET was already int - ' . $entity_type, array('entity_type'=>$entity_type));
            return $entity_type;
        }
        if(is_string($entity_type)){
            $entity_type = strtolower($entity_type);
            if(isset($this->_entityTypeCache[$entity_type])){
                //$this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA, 'pet_dbg', 'ET was cached - ' . $entity_type . ' - ' . $this->_entityTypeCache[$entity_type], array('entity_type'=>$entity_type));
                return $this->_entityTypeCache[$entity_type];
            }
            $result = $this->getTableGateway('entity_type')->select(array('name'=>$entity_type));
            if(!$result || !count($result)){
                $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR, 'pet_dbg', 'Could not find in DB - ' . $entity_type, array('entity_type'=>$entity_type));
                $this->_entityTypeCache[$entity_type] = false;
                return false;
            }
            foreach($result as $row){
                //$this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA, 'pet_dbg', 'ET in DB - ' . $entity_type . ' - ' . $row['entity_type_id'], array('entity_type'=>$entity_type, 'row'=>$row));
                $this->_entityTypeCache[$entity_type] = intval($row['entity_type_id']);
                return intval($row['entity_type_id']);
            }
            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR, 'pet_dbg', 'DB iterate failed weirdly - ' . $entity_type, array('entity_type'=>$entity_type));
            $this->_entityTypeCache[$entity_type] = false;
            return false;
        }
        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR, 'pet_dbg', 'Unknown type of input - ' . $entity_type, array('entity_type'=>$entity_type));
        return false;
    }

    /**
     * Cache for parseAttribute
     * @var array
     */
    protected $_attributeCache = array();

    /**
     * Get the attribute ID for a code/type.
     * @param string $attribute_code
     * @param int $entity_type
     * @return array|null
     */
    public function parseAttribute($attribute_code, $entity_type)
    {
        $entity_type = $this->parseEntityType($entity_type);
        if(isset($this->_attributeCache[$entity_type])){
            if(isset($this->_attributeCache[$entity_type][$attribute_code])){
                return $this->_attributeCache[$entity_type][$attribute_code];
            }
        }else{
            $this->_attributeCache[$entity_type] = array();
        }

        $res = $this->getTableGateway('entity_attribute')->select(array(
            'entity_type_id'=>$entity_type,
            'code'=>$attribute_code,
        ));

        foreach($res as $row){
            $this->_attributeCache[$entity_type][$attribute_code] = $row['attribute_id'];
            return $row['attribute_id'];
        }

        $this->_attributeCache[$entity_type][$attribute_code] = null;
        return null;
    }

    /**
     * Cache for getAttribute
     * @var array
     */
    protected $_attributeRevCache = array();

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
     * @param int $entity_type
     * @return string|false
     */
    public function parseEntityTypeReverse ( $entity_type ){
        if(!is_int($entity_type)){
            $entity_type = $this->parseEntityType($entity_type);
        }
        foreach($this->_entityTypeCache as $code=>$id){
            if($id == $entity_type){
                return $code;
            }
        }

        $result = $this->getTableGateway('entity_type')->select(array('entity_type_id'=>$entity_type));
        if(!$result){
            return false;
        }
        foreach($result as $row){
            return $row['name'];
        }
        return false;
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
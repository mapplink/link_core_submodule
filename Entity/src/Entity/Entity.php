<?php
/**
 * Represents an instance of a Magelink Entity.
 * @category Entity
 * @package Entity
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Entity;

use Magelink\Exception\MagelinkException;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


class Entity implements ServiceLocatorAwareInterface
{
    /** Static entity attributes */
    protected $_id;
    protected $_type;
    protected $_store_id;
    protected $_unique_id;
    protected $_updated_at;
    protected $_parent_id;
    
    /** @var array An array of all attribute data arrays that are applicable to this Entity
     *     - note will likely only contain those for the Node it was loaded for
     */
    protected $_attributes = array();

    /** @var array An array of each attributes fetch data, indexed by attribute code */
    protected $_attributesFetchData = array();

    /** @var array Map where key is attribute code and value is attribute ID */
    protected $_attributesMap = array();

    /** @var int The node that this entity was loaded for */
    protected $_loadedFromNode = false;
    
    /** @var array All loaded data for this Entity */
    protected $_data = array();

    /** @var array All extended data for this Entity (fkey data, etc) */
    protected $_extendedData = array();

    /** @var \Entity\Entity[] */
    protected $_resolveCache = array();


    /**
     * Creates a new Entity instance based on the provided row of data from the entity table.
     * @param array $row
     * @param array $attributes
     * @param int $loadedFromNode
     * @param array $extendedAttributes
     */
    public function __construct($row, $attributes, $loadedFromNode, $extendedAttributes = array())
    {
        $this->_id = $row['entity_id'];
        $this->_type = $row['type_id'];
        $this->_store_id = $row['store_id'];
        $this->_unique_id = $row['unique_id'];
        $this->_updated_at = $row['updated_at'];
        $this->_parent_id = $row['parent_id'];

        $this->_attributes = $attributes;
        foreach($attributes as $att){
            $this->_attributesMap[$att['code']] = $att['attribute_id'];
        }

        foreach ($extendedAttributes as $k=>$v) {
            $matches = array();
            if (preg_match('/([a-zA-Z_-]*)\.([a-zA-Z_-]*)/', $k, $matches)) {
                if (!isset($this->_extendedData[$matches[1]])) {
                    $this->_extendedData[$matches[1]] = array();
                }
                $this->_extendedData[$matches[1]][$matches[2]] =
                    $row['a_fkey_'.strtolower($matches[1]).'_'.strtolower($matches[2]).'_v'];
            }else{
                // TODO load as regular data?
            }

        }

        $this->_loadedFromNode = $loadedFromNode;
    }

    /**
     * Add new attributes to the internal map - used for enhanceEntity primarily. Should not be used by end-user code.
     * @param $attData
     */
    public function addAttribute($attData){
        if(isset($this->_attributesMap[$attData['code']])){
            return; // Already added
        }
        $this->_attributes[$attData['attribute_id']] = $attData;
        $this->_attributesMap[$attData['code']] = $attData['attribute_id'];
        $this->_attributesFetchData[$attData['code']] =
            $attData['fetch_data'] != null ? unserialize($attData['fetch_data']) : array();
    }

    /**
     * Populate the Entity from raw DB data
     * @param array $data Raw data - key is attribute ID value is data
     * @throws \Magelink\Exception\MagelinkException If values are passed that are not already loaded as attributes
     */
    public function populateRaw($data){
        foreach($data as $attribute_id=>$data){
            if(!isset($this->_attributes[$attribute_id])){
                throw new MagelinkException('Invalid attribute data loaded ' . $attribute_id);
            }
            $this->_data[$attribute_id] = $data;
        }
    }

    /**
     * Returns entity id
     * @return int
     */
    public function getId() {
        return $this->_id;
    }

    /**
     * Returns entity id (alias of getId())
     * @return int
     */
    public function getEntityId() {
        return $this->getId();
    }

    /**
     * Return the entity type for this entity.
     * @return int
     */
    public function getType(){
        return $this->_type;
    }

    /**
     * Return the entity type "name" directly.
     * @return string
     */
    public function getTypeStr(){
        return $this->getServiceLocator()->get('entityConfigService')->parseEntityTypeReverse($this->_type);
    }

    /**
     * Return the store ID assigned to this entity (or 0 if global)
     * @return int
     */
    public function getStoreId() {
        return $this->_store_id;
    }

    /**
     * Return the Unique ID assigned to this entity
     * @return string
     */
    public function getUniqueId() {
        return $this->_unique_id;
    }

    /**
     * Return the last updated date of this Entity (accurate to when it was loaded/reloaded)
     * @return string A date-time string
     */
    public function getUpdatedAt() {
        return $this->_updated_at;
    }

    /**
     * Return the parent Entity ID of this Entity, or null if none specified.
     * @return int|null
     */
    public function getParentId() {
        return $this->_parent_id;
    }

    /**
     * Gets a value from this Entity.
     *
     * @param string $key The attribute code to retrieve the value for
     * @param mixed|null $default The value to return if nothing is set (the value is null)
     * @return string|array|null The value, or null/default if none specified
     * @throws \Magelink\Exception\MagelinkException If the attribute was not loaded for this Entity
     */
    public function getData($key, $default=null)
    {
        if (!isset($this->_attributesMap[$key])) {
            if (is_object($key)) {
                $key = 'Object '.get_class($key);
                if (method_exists($key, 'getId')) {
                    $key = 'Object '.get_class($key).' '.$key->getId();
                }else{
                    $key = 'Object '.get_class($key);
                }
            }

            $message = 'Invalid attribute specified for getData ('.$this->getTypeStr().', by '.$this->getLoadedNodeId()
                .') - ' . $key.'. Check attribute existence and subscription.';
            throw new MagelinkException($message);
        }

        if (!isset($this->_data[$this->_attributesMap[$key]])) {
            return $default;
        }

        return $this->_data[$this->_attributesMap[$key]];
    }


    /**
     * Fetch all set data as an associative array
     * @return array
     */
    public function getAllSetData()
    {
        $ret = array();
        $invMap = array_flip($this->_attributesMap);
        foreach($this->_data as $k=>$v){
            $ret[$invMap[$k]] = $v;
        }
        return $ret;
    }

    public function hasAttribute($key){
        return isset($this->_attributesMap[$key]);
    }

    public function getAttributes(){
        return $this->_attributes;
    }

    /**
     * Same as getArrayCopy except returns only the attributes specified and in the order in which they were specified.
     *
     * @param array $attributes A list of attribute codes
     * @return array
     */
    public function getArrayCopySorted($attributes){
        $ret = array();
        foreach($attributes as $code){
            $ret[$code] = $this->getData($code);
        }
        return $ret;
    }

    /**
     * Get a array for attributes mapping value (Mainly for Zend/Form/Form)
     * @return array
     */
    public function getArrayCopy()
    {
        $results = array();
        foreach ($this->_attributes as $value) {
            $results[$value['code']] = $this->getData($value['code']);
        }

        return $results;
    }

    /**
     * Get a array for attributes mapping value (Mainly for Zend/Form/Form)
     * @return array
     */
    public function getFullArrayCopy()
    {
        $results = $this->getArrayCopy();

        $staticFields = $this->getServiceLocator()->get('entityConfigService')->getStaticFields();
        foreach ($staticFields as $staticField) {
            $method = 'get'.str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($staticField))));
            $results[$staticField] = $this->$method();
        }

        return $results;
    }

    /**
     * Return the node ID that initially loaded this Entity. May be 0 in rare cases.
     * @return int
     */
    public function getLoadedNodeId(){
        return $this->_loadedFromNode;
    }

    /**
     * Forcible changes the loaded node ID, for situations where it is not known upon creation
     * @param $node_id
     */
    public function setLoadedNodeId($node_id){
        $this->_loadedFromNode = $node_id;
    }

    /**
     * Resolve a foreign-key relationship. Specified attribute must be Entity type.
     * 
     * @param string $attribute_code
     * @param int|string $entityType
     * @return Entity
     */
    public function resolve($attribute_code, $entityType = NULL)
    {
        /** @var \Entity\Service\EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');

        if (!array_key_exists($attribute_code, $this->_resolveCache)) {
            $id = $this->getData($attribute_code, FALSE);
            if (!$id) {
                $this->_resolveCache[$attribute_code] = NULL;
            }else{
                $this->_resolveCache[$attribute_code] = $entityService->loadEntityId($this->_loadedFromNode, $id);
            }
        }

        return $this->_resolveCache[$attribute_code];
    }

    protected $_parentCache = false;

    /**
     * Get parent
     * @return \Entity\Entity
     */
    public function getParent()
    {
        if($this->_parentCache !== false){
            return $this->_parentCache;
        }
        return ($this->_parentCache = $this->getServiceLocator()->get('entityService')
            ->loadEntityId($this->_loadedFromNode, $this->getParentId()));
    }

    protected $_childrenCache = array();

    /**
     * Loads all children of the given type for this Entity.
     *
     * @param string $entityType
     * @return \Entity\Entity[]
     */
    public function getChildren($entityType)
    {
        if (array_key_exists($entityType, $this->_childrenCache)) {
            return $this->_childrenCache[$entityType];
        }
        return ($this->_childrenCache[$entityType] = $this->getServiceLocator()->get('entityService')
            ->loadChildren($this->_loadedFromNode, $this, $entityType));
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

    /**
     * @return \Entity\Service\EntityService
     */
    protected function getEavService()
    {
        return $this->getServiceLocator()->get('entityService');
    }
    
}
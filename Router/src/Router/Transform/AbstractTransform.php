<?php

namespace Router\Transform;

use \Entity\Entity;
use \Entity\Service\EntityService;
use \Entity\Service\EntityConfigService;
use \Node\Service\NodeService;
use \Router\Entity\RouterTransform;
use \Router\Filter\AbstractFilter;
use \Zend\ServiceManager\ServiceLocatorAwareInterface;
use \Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractTransform implements ServiceLocatorAwareInterface
{

    /** @var ServiceLocatorInterface The service locator */
    protected $_serviceLocator;
    /** @var  \Node\Service\NodeService */
    protected $_nodeService;
    /** @var  \Entity\Service\EntityService */
    protected $_entityService;
    /** @var  \Entity\Service\EntityConfigService */
    protected $_entityConfigService;

    /** @var  \Entity\Entity */
    protected $_entity;
    /** @var  \Router\Entity\RouterTransform */
    protected $_transformEntity;

    /** @var array The update data  */
    protected $_updateData = array();
    /** @var array The entity data, accounting for new changes */
    protected $_newData = array();


    /**
     * @param \Entity\Entity $entity
     * @param int $source_node_id
     * @param \Router\Entity\RouterTransform $transform
     * @param array $updated_data The newly updated data
     * @return boolean Whether this transform is eligible to run
     */
    public function init(Entity $entity, $sourceNodeId, RouterTransform $transform, array $updateData)
    {
        $this->_nodeService = $this->getServiceLocator()->get('nodeService');
        $this->_entityService = $this->getServiceLocator()->get('entityService');
        $this->_entityConfigService = $this->getServiceLocator()->get('entityConfigService');
        $this->_transformEntity = $transform;
        $this->_entity = $entity;

        $this->_newData = $this->_updateData = $updateData;
        foreach ($entity->getAllSetData() as $code=>$value) {
            if (!array_key_exists($code, $this->_newData)) {
                $this->_newData[$code] = $value;
            }
        }

        $this->_transformEntity->loadSimpleData();

        $attributes = $this->_entityConfigService->getAttributesCode($this->_entity->getType());
        $this->_entityService->enhanceEntity($sourceNodeId, $this->_entity, $attributes);

        return $this->_init();
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
     * Return data from the entity before the change is applied
     * @param string $key The key to return
     * @return mixed The value
     */
    protected function getCurrentData($key)
    {
        return $this->_entity->getData($key, NULL);
    }

    /**
     * Return the raw update data, if not set NULL
     * @param string $key The key to return
     * @return mixed The value or the default value
     */
    protected function getUpdateData($key)
    {
        if (isset($this->_updateData[$key])){
            $updateData = $this->_updateData[$key];
        }else{
            $updateData = NULL;
        }

        return $updateData;
    }

    /**
     * Return a piece of data from the Entity, accounting for new changes
     * @param string $key The key to return
     * @param mixed|null $default The default value, if the key is not set or is null
     * @return mixed The value or the default value
     */
    protected function getNewData($key, $default = NULL)
    {
        if (isset($this->_newData[$key])) {
            $newData = $this->_newData[$key];
        }else{
            $newData = $default;
        }

        return $newData;
    }

    /**
     * Return config data, from the entity's simpledata storage.
     * @param string|null $key
     * @return array|null
     */
    protected function getConfig($key = NULL)
    {
        return $this->_transformEntity->getSimpleData($key);
    }

    /**
     * Get attribute data for the source attribute
     * @return array|null
     */
    protected function getSourceAttribute()
    {
       return $this->_entityConfigService->getAttribute($this->_transformEntity->getSrcAttribute());
    }

    /**
     * Get attribute data for the destination attribute
     * @return array|null
     */
    protected function getDestAttribute()
    {
        return $this->_entityConfigService->getAttribute($this->_transformEntity->getDestAttribute());
    }

    /**
     * Perform any initialization/setup actions, and check any prerequisites.
     * @return boolean Whether this transform is eligible to run
     */
    protected abstract function _init();

    /**
     * Apply the transform on any necessary data
     * @return array New data changes to be merged into the update.
     */
    public abstract function apply();

    /**
     * Return the database adapter to be used to communicate with Entity storage.
     * @return \Zend\Db\Adapter\Adapter
     */
    protected function getAdapter()
    {
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
    protected function getTableGateway($table)
    {
        if(isset($this->_tgCache[$table])){
            return $this->_tgCache[$table];
        }
        $this->_tgCache[$table] = new TableGateway($table, $this->getServiceLocator()->get('zend_db'));
        return $this->_tgCache[$table];
    }

}
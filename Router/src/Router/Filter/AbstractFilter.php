<?php

namespace Router\Filter;

use Magelink\Exception\MagelinkException;
use Entity\Service\EntityService;
use Entity\Entity;

/**
 * A Filter is an object that decides whether a particular edge or transform should apply to a specific entity.
 *
 * @package Router\Filter
 */
abstract class AbstractFilter implements ServiceLocatorAwareInterface {

    /** @var  \Node\Service\NodeService */
    protected $_nodeService;
    /** @var  \Entity\Service\EntityService */
    protected $_entityService;
    /** @var  \Entity\Service\EntityConfigService */
    protected $_entityConfigService;

    /**
     * @var Entity
     */
    protected $_entity;

    /**
     * @var int One of the \Entity\Update TYPE_ constants.
     */
    protected $_updateType;

    /**
     * @var \Router\Entity\RouterFilter
     */
    protected $_filterEntity;

    /**
     * @param Entity $entity
     * @param int $updateType One of the \Entity\Update TYPE_ constants.
     */
    public function init(Entity $entity, \Router\Entity\RouterFilter $filterEntity, $updateType){
        $this->_nodeService = $this->getServiceLocator()->get('nodeService');
        $this->_entityService = $this->getServiceLocator()->get('entityService');
        $this->_entityConfigService = $this->getServiceLocator()->get('entityConfigService');

        $this->_entity = $entity;
        $this->_filterEntity = $filterEntity;
        $this->_updateType = $updateType;

        $this->_filterEntity->loadSimpleData();

        $attributes = $this->_entityConfigService->getAttributes($this->_entity->getType());
        $this->_entityService->enhanceEntity(false, $this->_entity, $attributes);
    }

    /**
     * Return config data, from the entity's simpledata storage.
     * @param string|null $key
     * @return array|null
     */
    protected function getConfig($key=null){
        return $this->_filterEntity->getSimpleData($key);
    }

    /**
     * Run this filter for an edge.
     * If not supported, must return true.
     *
     * @param $sourceNodeId The source of the current update being passed through
     * @param $targetNodeId The target of the current update being passed through (this filter is run once for each target)
     * @param array $attributes A list of changed attributes, key=>value with new values. Old values / other data is in the provided Entity
     * @return boolean Whether this edge should be processed. Return true to continue normally, false to block the update from propagating.
     */
    public abstract function checkEdge($sourceNodeId, $targetNodeId, $attributes=array());

    /**
     * Run this filter for a transform.
     * If not supported, must return true.
     *
     * @param int $sourceAttribute The source attribute of the transform in question
     * @param int $destAttribute The destination attribute of the transform in question
     * @param array $attributes A list of changed attributes, key=>value with new values. Old values / other data is in the provided Entity
     * @return boolean Whether this transform should be processed. Return true to continue normally, false to block the transform from processing.
     */
    public abstract function checkTransform($sourceAttribute, $destAttribute, $attributes=array());


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
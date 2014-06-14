<?php

/**
 *
 */

namespace Node;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractGateway implements ServiceLocatorAwareInterface {

    /**
     * Initialize the gateway and perform any setup actions required.
     * @param AbstractNode $node
     * @param Entity\Node $nodeEntity
     * @param string $entity_type
     * @return boolean
     */
    public abstract function init(AbstractNode $node, Entity\Node $nodeEntity, $entity_type);

    /**
     * Retrieve and action all updated records (either from polling, pushed data, or other sources).
     */
    public abstract function retrieve();

    /**
     * Write out all the updates to the given entity.
     * @param \Entity\Entity $entity
     * @param string[] $attributes
     * @param int $type
     */
    public abstract function writeUpdates(\Entity\Entity $entity, $attributes, $type = \Entity\Update::TYPE_UPDATE);

    /**
     * Write out the given action.
     * @param \Entity\Action $action
     * @return bool Whether to mark the action as complete
     */
    public abstract function writeAction(\Entity\Action $action);



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
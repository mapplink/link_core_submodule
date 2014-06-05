<?php

namespace Router\Transform;

use \Zend\ServiceManager\ServiceLocatorAwareInterface;
use \Zend\ServiceManager\ServiceLocatorInterface;

class TransformFactory implements ServiceLocatorAwareInterface {

    /**
     * Return a new Transform instance
     * @param \Router\Entity\RouterTransform $entity
     * @return \Router\Transform\AbstractTransform
     */
    public function getTransform(\Router\Entity\RouterTransform $entity) {

        $code = $entity->getTransformType();

        return $this->getServiceLocator()->get('transform_' . strtolower($code));

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
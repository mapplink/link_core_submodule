<?php
/**
 * @category Router
 * @package Router\Transform
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */


namespace Router\Transform;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class TransformFactory implements ServiceLocatorAwareInterface
{

    /**
     * Return a new Transform instance
     * @param \Router\Entity\RouterTransform $entity
     * @return \Router\Transform\AbstractTransform|null
     */
    public function getTransform(\Router\Entity\RouterTransform $entity) {

        $code = $entity->getTransformType();

        try{
            return $this->getServiceLocator()->get('transform_' . strtolower($code));
        }catch(\Zend\ServiceManager\Exception\ServiceNotFoundException $snfe){
            return null;
        }

    }

    /**
     * @var ServiceLocatorInterface The service locator
     */
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
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->_serviceLocator;
    }

}

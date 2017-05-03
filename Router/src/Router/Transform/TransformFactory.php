<?php
/**
 * @package Router\Transform
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Router\Transform;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class TransformFactory implements ServiceLocatorAwareInterface
{

    /** @var ServiceLocatorInterface The service locator */
    protected $_serviceLocator;


    /**
     * Return a new Transform instance
     * @param \Router\Entity\RouterTransform $entity
     * @return \Router\Transform\AbstractTransform|null
     */
    public function getTransform(\Router\Entity\RouterTransform $entity)
    {
        $code = $entity->getTransformType();

        try{
            return $this->getServiceLocator()->get('transform_' . strtolower($code));
        }catch(\Zend\ServiceManager\Exception\ServiceNotFoundException $snfe){
            return null;
        }
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

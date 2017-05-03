<?php
/**
 * @package Router\Filter
 * @author Matt Johnston
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Router\Filter;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


class FilterFactory implements ServiceLocatorAwareInterface
{

    /**
     * Return a new Filter instance
     * @param \Router\Entity\RouterFilter $entity
     * @return \Router\Filter\AbstractFilter
     */
    public function getFilter(\Router\Entity\RouterFilter $entity) {

        $code = $entity->getTypeId();
        $class = $entity->getClass();

        if($code && strlen($code)){
            return $this->getServiceLocator()->get('filter_' . strtolower($code));
        }else if($class && class_exists($class)){
            $ret = new $class;
            if($ret instanceof ServiceLocatorAwareInterface){
                $ret->setServiceLocator($this->getServiceLocator());
            }
        }else{
            return null;
        }

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
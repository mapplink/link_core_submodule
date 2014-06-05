<?php

/*
 * Copyright (c) 2014 Lero9 Limited
 * All Rights Reserved
 * This software is subject to our terms of trade and any applicable licensing agreements.
 */

namespace Magelink\Service;

use \Zend\ServiceManager\ServiceLocatorInterface;
use \Zend\ServiceManager\ServiceLocatorAwareInterface;

/**
 * Provides system configuration services for Magelink modules.
 *
 * @author Matt Johnston <matt@lero9.co.nz>
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 */

class ConfigService implements ServiceLocatorAwareInterface {

    protected $configCache = array();

    /**
     * Returns the config value with the specified key.
     *
     * @param string $key
     * @param mixed $default The value to be returned if none found - default is null.
     * @return mixed The loaded or default value.
     */
    public function getValue($key, $default=null){
        if(isset($this->configCache[$key])){
            return $this->configCache[$key];
        }

        $value = $this->_getValue($key);

        if($value === null){
            $value = $default;
        }

        $this->configCache[$key] = $value;
        return $value;
    }

    protected function _getValue($key)
    {
        return $this->getConfigRepository()
            ->getValueByKey($key);
    }

    /**
     * Updates a config value in the database.
     *
     * @param string $key
     * @param mixed $value
     * @return boolean Whether the value was successfully updated.
     */
    public function setValue($key, $value){
        $result = $this->_setValue($key, $value);
        $this->configCache[$key] = $value;

        return $result;
    }

    protected function _setValue($key, $value)
    {
        return $this->getConfigRepository()
            ->setValueByKey($key, $value);
    }

    /**
     * Returns an associative array of all config values whose keys begin with a specified prefix.
     * No facility for defaults is provided, non-existing keys will not be returned.
     *
     * @param string $prefix
     * @return array
     */
    public function getValuesPrefix($prefix)
    {
        return $this->getConfigRepository()
            ->getValueByKeyPrefix($prefix);
    }

    protected $_serviceLocator;

    public function setServiceLocator(ServiceLocatorInterface $serviceLocator) {
        $this->_serviceLocator = $serviceLocator;
    }

    public function getServiceLocator() {
        return $this->_serviceLocator;
    }

    /**
     * Get Doctrine repository
     * @return mixed
     */
    protected function getConfigRepository()
    {
        return $this->getEntityManager()
            ->getRepository('Magelink\Entity\Config');
    }

    /**
     * Get Doctrine EntityManager
     * @return object
     */
    protected function getEntityManager()
    {
        return $this->getServiceLocator()
            ->get('Doctrine\ORM\EntityManager');
    }

}
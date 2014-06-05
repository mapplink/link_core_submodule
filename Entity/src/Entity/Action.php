<?php

/* 
 * Copyright (c) 2014 Lero9 Limited
 * All Rights Reserved
 * This software is subject to our terms of trade and any applicable licensing agreements.
 */

namespace Entity;

use Magelink\Exception\MagelinkException;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Represents an instance of a Magelink Entity Action.
 */
class Action  {

    protected $_id;
    protected $_entity;
    protected $_type;
    protected $_data = array();

    public function init($id, Entity $entity, $type, $data){
        $this->_id = $id;
        $this->_entity = $entity;
        $this->_type = $type;
        $this->_data = $data;
    }

    /**
     * @return int
     */
    public function getId(){
        return $this->_id;
    }

    /**
     * @return Entity
     */
    public function getEntity(){
        return $this->_entity;
    }

    /**
     * @return string
     */
    public function getType(){
        return $this->_type;
    }

    public function getData($key=null){
        if($key === null){
            return $this->_data;
        }else if(isset($this->_data[$key])){
            return $this->_data[$key];
        }else{
            return null;
        }
    }

    public function hasData($key){
        return isset($this->_data[$key]);
    }
    
}
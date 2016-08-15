<?php
/**
 * Represents an instance of a Magelink Entity Action.
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


class Action
{

    /** @var int $this->_id */
    protected $_id;
    /** @var Entity $this->_entity */
    protected $_entity;
    /** @var string $this->_type */
    protected $_type;
    /** @var array $this->_data */
    protected $_data = array();


    /**
     * @param int $id
     * @param Entity $entity
     * @param string $type
     * @param array $data
     */
    public function init($id, Entity $entity, $type, $data)
    {
        $this->_id = $id;
        $this->_entity = $entity;
        $this->_type = $type;
        $this->_data = $data;
    }

    /**
     * @return int $this->_id
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @return Entity $this->_entity
     */
    public function getEntity()
    {
        return $this->_entity;
    }

    /**
     * @return string $this->_type
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * @param mixed $key
     * @return array|mixed|NULL
     */
    public function getData($key = NULL)
    {
        if (is_null($key)) {
            $data = $this->_data;
        }elseif (isset($this->_data[$key])) {
            $data = $this->_data[$key];
        }else{
            $data = NULL;
        }

        return $data;
    }

    /**
     * @param mixed $key
     * @return bool $hasData
     */
    public function hasData($key)
    {
        return isset($this->_data[$key]);
    }

}

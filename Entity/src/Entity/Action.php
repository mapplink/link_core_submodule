<?php
/**
 * Represents an instance of a Magelink Entity Action.
 * @package Entity
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Entity;

use Magelink\Exception\MagelinkException;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


class Action
{

    /** @var int $this->id */
    protected $id;
    /** @var Entity $this->entity */
    protected $entity;
    /** @var string $this->type */
    protected $type;
    /** @var array $this->data */
    protected $data = array();


    /**
     * @param int $id
     * @param Entity $entity
     * @param string $type
     * @param array $data
     */
    public function init($id, Entity $entity, $type, $data)
    {
        $this->id = $id;
        $this->entity = $entity;
        $this->type = $type;
        $this->data = $data;
    }

    /**
     * @return int $this->id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Entity $this->entity
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @return string $this->type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $key
     * @return array|mixed|NULL
     */
    public function getData($key = NULL)
    {
        if (is_null($key)) {
            $data = $this->data;
        }elseif (isset($this->data[$key])) {
            $data = $this->data[$key];
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
        return isset($this->data[$key]);
    }

}

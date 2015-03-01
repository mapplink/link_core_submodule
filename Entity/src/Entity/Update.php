<?php
/**
 * Represents an instance of a Magelink Entity Update.
 *
 * @category Entity
 * @package Entity
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Entity;

use Entity\Entity;
use Magelink\Exception\MagelinkException;


class Update
{
    const TYPE_CREATE = 0;
    const TYPE_UPDATE = 1;
    const TYPE_DELETE = 2;
    const TYPE_ACTION = 9;

    /** @var int $_logId */
    protected $_logId;
    /** @var Entity $_entity */
    protected $_entity;
    /** @var string $_type */
    protected $_type;
    /** @var int $_timestamp */
    protected $_timestamp;
    /** @var int $ */
    protected $_sourceNode;
    protected $_affectedNodes;
    protected $_affectedAttributes;

    public function init($logId, Entity $entity, $type, $timestamp, $sourceNode, $affectedNodes, $affectedAttributes)
    {
        if (!is_array($affectedNodes)) {
            $affectedNodes = explode(',', $affectedNodes);
        }
        if (!is_array($affectedAttributes)) {
            $affectedAttributes = explode(',', $affectedAttributes);
        }

        $this->_logId = $logId;
        $this->_entity = $entity;
        $this->_type = $type;
        $this->_timestamp = $timestamp;
        $this->_sourceNode = $sourceNode;
        $this->_affectedNodes = $affectedNodes;
        $this->_affectedAttributes = $affectedAttributes;
    }

    /**
     * @return int
     */
    public function getLogId()
    {
        return $this->_logId;
    }

    /**
     * @return Entity
     */
    public function getEntity()
    {
        return $this->_entity;
    }

    public function getType(){
        return $this->_type;
    }

    public function getTimestamp(){
        return $this->_timestamp;
    }

    public function getSourceNode(){
        return $this->_sourceNode;
    }

    public function getNodesSimple(){
        return $this->_affectedNodes;
    }

    /**
     * Returns a simple array of attribute codes changed in this update
     * @return string[]
     */
    public function getAttributesSimple(){
        return $this->_affectedAttributes;
    }
    
}
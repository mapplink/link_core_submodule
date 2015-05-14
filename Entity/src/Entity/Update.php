<?php
/**
 * Represents an instance of a Magelink Entity Update.
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

    /** @var int $logId */
    protected $logId;

    /** @var Entity $entity */
    protected $entity;

    /** @var string $type */
    protected $type;

    /** @var int $timestamp */
    protected $timestamp;

    /** @var int $sourceNode */
    protected $sourceNode;

    /** @var array $affectedNodes */
    protected $affectedNodes;

    /** @var array $affectedAttributes */
    protected $affectedAttributes;


    /**
     * @param int $logId
     * @param \Entity\Entity $entity
     * @param string $type
     * @param string $timestamp
     * @param int $sourceNode
     * @param array|string $affectedNodes
     * @param array|string $affectedAttributes
     */
    public function init($logId, Entity $entity, $type, $timestamp, $sourceNode, $affectedNodes, $affectedAttributes)
    {
        if (!is_array($affectedNodes)) {
            $affectedNodes = explode(',', $affectedNodes);
        }
        if (!is_array($affectedAttributes)) {
            $affectedAttributes = explode(',', $affectedAttributes);
        }

        $this->logId = $logId;
        $this->entity = $entity;
        $this->type = $type;
        $this->timestamp = $timestamp;
        $this->sourceNode = $sourceNode;
        $this->affectedNodes = $affectedNodes;
        $this->affectedAttributes = $affectedAttributes;
    }

    /**
     * @return int $this->logId
     */
    public function getLogId()
    {
        return $this->logId;
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
     * @return int $this->timestamp
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @return int $this->sourceNode
     */
    public function getSourceNode()
    {
        return $this->sourceNode;
    }

    /**
     * @return int[] $this->affectedNodes
     */
    public function getNodesSimple()
    {
        return $this->affectedNodes;
    }

    /**
     * @return string[]
     */
    public function getAttributesSimple()
    {
        return $this->affectedAttributes;
    }
    
}
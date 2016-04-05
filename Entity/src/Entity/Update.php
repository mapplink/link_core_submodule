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

    /** @var int $this->update_id */
    protected $update_id;
    /** @var int $log_id */
    protected $log_id;
    /** @var Entity $entity */
    protected $entity;
    /** @var string $type */
    protected $type;
    /** @var int $timestamp */
    protected $timestamp;

    /** @var int $source_node */
    protected $source_node;
    /** @var array $affected_nodes */
    protected $affected_nodes;
    /** @var array $affected_attributes */
    protected $affected_attributes;


    /**
     * @param \Entity\Entity $entity
     * @param array $data
     */
    public function init(Entity $entity, array $data)
    {
        $this->entity = $entity;
        $dataKeys = array(
            'update_id',
            'log_id',
            'type',
            'timestamp',
            'source_node',
            'affected_nodes',
            'affected_attributes'
        );
        foreach ($dataKeys as $key) {
            if (!array_key_exists($key, $data)) {
                $message = 'Could not find value of '.$key.' for update '.$data['update_id'].'.'
                    .' Data contains: '.implode(',', array_keys($data)).' vs expected: '.implode(',', $dataKeys).'.';
                throw new MagelinkException($message);
                break;
            }else{
                if (strpos($key, 'affected_') === 0 && !is_array($data[$key])) {
                    $data[$key] = explode(',', $data[$key]);
                }

                $this->$key = $data[$key];
            }
        }
    }

    /**
     * @return int $this->update_id
     */
    public function getUpdateId()
    {
        return $this->update_id;
    }

    /**
     * @return int $this->log_id
     */
    public function getLogId()
    {
        return $this->log_id;
    }

    /**
     * @return Entity $this->entity
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @return Entity $this->entity->getId()
     */
    public function getEntityId()
    {
        return $this->entity->getId();
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
     * @return int $this->source_node
     */
    public function getSourceNode()
    {
        return $this->source_node;
    }

    /**
     * @return int[] $this->affectedNodes
     */
    public function getNodesSimple()
    {
        return $this->affected_nodes;
    }

    /**
     * @return string[]
     */
    public function getAttributesSimple()
    {
        return $this->affected_attributes;
    }

}

<?php
/**
 * @package Entity\Entity
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Entity\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * EntityActionStatus
 *
 * @ORM\Table(name="entity_action_status", indexes={@ORM\Index(name="event_id_idx", columns={"action_id"}), @ORM\Index(name="node_id_idx", columns={"node_id"})})
 * @ORM\Entity(repositoryClass="Entity\Repository\EntityActionStatusRepository")
 */
class EntityActionStatus extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="action_id", type="bigint", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $actionId;

    /**
     * @var integer
     *
     * @ORM\Column(name="node_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $nodeId;

    /**
     * @var boolean
     *
     * @ORM\Column(name="status", type="boolean", nullable=false)
     */
    private $status = '0';



    /**
     * Set actionId
     *
     * @param integer $actionId
     * @return EntityActionStatus
     */
    public function setActionId($actionId)
    {
        $this->actionId = $actionId;

        return $this;
    }

    /**
     * Get actionId
     *
     * @return integer 
     */
    public function getActionId()
    {
        return $this->actionId;
    }

    /**
     * Set nodeId
     *
     * @param integer $nodeId
     * @return EntityActionStatus
     */
    public function setNodeId($nodeId)
    {
        $this->nodeId = $nodeId;

        return $this;
    }

    /**
     * Get nodeId
     *
     * @return integer 
     */
    public function getNodeId()
    {
        return $this->nodeId;
    }

    /**
     * Set status
     *
     * @param boolean $status
     * @return EntityActionStatus
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status
     *
     * @return boolean 
     */
    public function getStatus()
    {
        return $this->status;
    }
}

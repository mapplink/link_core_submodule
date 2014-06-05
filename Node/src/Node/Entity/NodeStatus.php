<?php

namespace Node\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * NodeStatus
 *
 * @ORM\Table(name="node_status", uniqueConstraints={@ORM\UniqueConstraint(name="selector", columns={"node_id", "entity_type_id", "action"})}, indexes={@ORM\Index(name="selector_action", columns={"node_id", "action", "entity_type_id"}), @ORM\Index(name="entity_type_id_idx", columns={"entity_type_id"}), @ORM\Index(name="node_idx", columns={"node_id"}), @ORM\Index(name="lookup_node_type", columns={"node_id", "entity_type_id", "action", "timestamp"}), @ORM\Index(name="lookup_node_action", columns={"node_id", "action", "timestamp"})})
 * @ORM\Entity(repositoryClass="Node\Repository\NodeStatusRepository")
 */
class NodeStatus extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="entity_type_id", type="integer", nullable=false)
     *
     */
    private $entityTypeId;

    /**
     * @var string
     *
     * @ORM\Column(name="action", type="string", length=50, nullable=false)
     */
    private $action;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="timestamp", type="datetime", nullable=false)
     */
    private $timestamp = 'CURRENT_TIMESTAMP';

   

    /**
     * @var \Node\Entity\Node
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\OneToOne(targetEntity="Node\Entity\Node")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="node_id", referencedColumnName="node_id")
     * })
     */
    private $node;



    /**
     * Set id
     *
     * @param integer $id
     * @return NodeStatus
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get entity type ID
     *
     * @return int
     */
    public function getEntityId(){
        return $this->entityTypeId;
    }

    public function setEntityTypeId($entityTypeId){
        $this->entityTypeId = $entityTypeId;

        return $this;
    }

    /**
     * Set action
     *
     * @param string $action
     * @return NodeStatus
     */
    public function setAction($action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Get action
     *
     * @return string 
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Set timestamp
     *
     * @param \DateTime $timestamp
     * @return NodeStatus
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Get timestamp
     *
     * @return \DateTime 
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Set node
     *
     * @param \Node\Entity\Node $node
     * @return NodeStatus
     */
    public function setNode(\Node\Entity\Node $node)
    {
        $this->node = $node;

        return $this;
    }

    /**
     * Get node
     *
     * @return \Node\Entity\Node 
     */
    public function getNode()
    {
        return $this->node;
    }
}

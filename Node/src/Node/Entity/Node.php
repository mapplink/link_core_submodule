<?php

namespace Node\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Node
 *
 * @ORM\Table(name="node", indexes={@ORM\Index(name="type_idx", columns={"type"})})
 * @ORM\Entity(repositoryClass="Node\Repository\NodeRepository")
 */
class Node extends \Magelink\Entity\DatadBaseEntity
{
    protected $_simpleDataKey = 'getNodeId';

    /**
     * @var integer
     *
     * @ORM\Column(name="node_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $nodeId;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=45, nullable=false)
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    private $name;


    /**
     * Constructor
     */
    public function __construct()
    {

    }

    /**
     * Get id
     *
     * @see $this->getNodeId()
     */
    public function getId()
    {
        return $this->getNodeId();
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
     * Set type
     *
     * @param string $type
     * @return Node
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string 
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Node
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

}

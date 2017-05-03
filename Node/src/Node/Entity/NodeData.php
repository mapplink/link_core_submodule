<?php
/**
 * @package Node\Entity
 * @author Sean Yao
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Node\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * NodeData
 *
 * @ORM\Table(name="node_data", indexes={@ORM\Index(name="node_id_idx", columns={"parent_id"})})
 * @ORM\Entity(repositoryClass="Node\Repository\NodeDataRepository")
 */
class NodeData extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var string
     *
     * @ORM\Column(name="key", type="string", length=45, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $key;

    /**
     * @var string
     *
     * @ORM\Column(name="value", type="string", length=254, nullable=false)
     */
    private $value;

    /**
     * @var \Node\Entity\Node
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\OneToOne(targetEntity="Node\Entity\Node")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="parent_id", referencedColumnName="node_id")
     * })
     */
    private $parent;



    /**
     * Set key
     *
     * @param string $key
     * @return NodeData
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Get key
     *
     * @return string 
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Set value
     *
     * @param string $value
     * @return NodeData
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value
     *
     * @return string 
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set parent
     *
     * @param \Node\Entity\Node $parent
     * @return NodeData
     */
    public function setParent(\Node\Entity\Node $parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return \Node\Entity\Node 
     */
    public function getParent()
    {
        return $this->parent;
    }

}

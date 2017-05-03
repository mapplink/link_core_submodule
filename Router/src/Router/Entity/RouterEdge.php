<?php
/**
 * @package Router\Entity
 * @author Sean Yao
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Router\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * RouterEdge
 *
 * @ORM\Table(name="router_edge", indexes={@ORM\Index(name="node_from_idx", columns={"node_from"}), @ORM\Index(name="node_to_idx", columns={"node_to"}), @ORM\Index(name="entity_type_id_idx", columns={"entity_type_id"}), @ORM\Index(name="lookup_create", columns={"node_from", "entity_type_id", "enable_create"}), @ORM\Index(name="lookup_update", columns={"node_from", "entity_type_id", "enable_update"}), @ORM\Index(name="lookup_delete", columns={"node_from", "entity_type_id", "enable_delete"}), @ORM\Index(name="lookup_event", columns={"node_from", "entity_type_id", "enable_action"})})
 * @ORM\Entity(repositoryClass="Router\Repository\RouterEdgeRepository")
 */
class RouterEdge extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="edge_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $edgeId;

    /**
     * @var boolean
     *
     * @ORM\Column(name="enable_create", type="boolean", nullable=false)
     */
    private $enableCreate = '0';

    /**
     * @var boolean
     *
     * @ORM\Column(name="enable_update", type="boolean", nullable=false)
     */
    private $enableUpdate = '0';

    /**
     * @var boolean
     *
     * @ORM\Column(name="enable_delete", type="boolean", nullable=false)
     */
    private $enableDelete = '0';

    /**
     * @var boolean
     *
     * @ORM\Column(name="enable_action", type="boolean", nullable=false)
     */
    private $enableAction = '0';

    /**
     * @var integer
     *
     * @ORM\Column(name="entity_type_id", type="integer", nullable=false)
     */
    private $entityTypeId;

    /**
     * @var \Node\Entity\Node
     *
     * @ORM\ManyToOne(targetEntity="Node\Entity\Node")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="node_from", referencedColumnName="node_id")
     * })
     */
    private $nodeFrom;

    /**
     * @var \Node\Entity\Node
     *
     * @ORM\ManyToOne(targetEntity="Node\Entity\Node")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="node_to", referencedColumnName="node_id")
     * })
     */
    private $nodeTo;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="Router\Entity\RouterFilter", inversedBy="edge")
     * @ORM\JoinTable(name="router_edge_filter",
     *   joinColumns={
     *     @ORM\JoinColumn(name="edge_id", referencedColumnName="edge_id")
     *   },
     *   inverseJoinColumns={
     *     @ORM\JoinColumn(name="filter_id", referencedColumnName="filter_id")
     *   }
     * )
     */
    private $filter;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->filter = new \Doctrine\Common\Collections\ArrayCollection();
    }


    /**
     * Get edgeId
     *
     * @return integer 
     */
    public function getEdgeId()
    {
        return $this->edgeId;
    }

    /**
     * Set enableCreate
     *
     * @param boolean $enableCreate
     * @return RouterEdge
     */
    public function setEnableCreate($enableCreate)
    {
        $this->enableCreate = $enableCreate;

        return $this;
    }

    /**
     * Get enableCreate
     *
     * @return boolean 
     */
    public function getEnableCreate()
    {
        return $this->enableCreate;
    }

    /**
     * Set enableUpdate
     *
     * @param boolean $enableUpdate
     * @return RouterEdge
     */
    public function setEnableUpdate($enableUpdate)
    {
        $this->enableUpdate = $enableUpdate;

        return $this;
    }

    /**
     * Get enableUpdate
     *
     * @return boolean 
     */
    public function getEnableUpdate()
    {
        return $this->enableUpdate;
    }

    /**
     * Set enableDelete
     *
     * @param boolean $enableDelete
     * @return RouterEdge
     */
    public function setEnableDelete($enableDelete)
    {
        $this->enableDelete = $enableDelete;

        return $this;
    }

    /**
     * Get enableDelete
     *
     * @return boolean 
     */
    public function getEnableDelete()
    {
        return $this->enableDelete;
    }

    /**
     * Set enableAction
     *
     * @param boolean $enableAction
     * @return RouterEdge
     */
    public function setEnableAction($enableAction)
    {
        $this->enableAction = $enableAction;

        return $this;
    }

    /**
     * Get enableAction
     *
     * @return boolean 
     */
    public function getEnableAction()
    {
        return $this->enableAction;
    }


    /**
     * Set nodeFrom
     *
     * @param \Node\Entity\Node $nodeFrom
     * @return RouterEdge
     */
    public function setNodeFrom(\Node\Entity\Node $nodeFrom = null)
    {
        $this->nodeFrom = $nodeFrom;

        return $this;
    }

    /**
     * Get nodeFrom
     *
     * @return \Node\Entity\Node 
     */
    public function getNodeFrom()
    {
        return $this->nodeFrom;
    }

    /**
     * Set nodeTo
     *
     * @param \Node\Entity\Node $nodeTo
     * @return RouterEdge
     */
    public function setNodeTo(\Node\Entity\Node $nodeTo = null)
    {
        $this->nodeTo = $nodeTo;

        return $this;
    }

    /**
     * Get nodeTo
     *
     * @return \Node\Entity\Node 
     */
    public function getNodeTo()
    {
        return $this->nodeTo;
    }

    /**
     * Add filter
     *
     * @param \Router\Entity\RouterFilter $filter
     * @return RouterEdge
     */
    public function addFilter(\Router\Entity\RouterFilter $filter)
    {
        $this->filter[] = $filter;

        return $this;
    }

    /**
     * Remove filter
     *
     * @param \Router\Entity\RouterFilter $filter
     */
    public function removeFilter(\Router\Entity\RouterFilter $filter)
    {
        $this->filter->removeElement($filter);
    }

    /**
     * Get filter
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * Set entityTypeId
     *
     * @param integer $entityTypeId
     * @return RouterEdge
     */
    public function setEntityTypeId($entityTypeId)
    {
        $this->entityTypeId = $entityTypeId;

        return $this;
    }

    /**
     * Get entityTypeId
     *
     * @return integer 
     */
    public function getEntityTypeId()
    {
        return $this->entityTypeId;
    }

}

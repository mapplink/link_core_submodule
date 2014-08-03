<?php

namespace Router\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * RouterFilter
 *
 * @ORM\Table(name="router_filter", indexes={@ORM\Index(name="parent_id_idx", columns={"parent_id"})})
 * @ORM\Entity(repositoryClass="Router\Repository\RouterFilterRepository")
 */
class RouterFilter extends \Magelink\Entity\DataDBaseEntity
{
    protected $_simpleDataKey = 'getFilterId';
    /**
     * @var integer
     *
     * @ORM\Column(name="filter_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $filterId;

    /**
     * @var string
     *
     * @ORM\Column(name="type_id", type="string", length=45, nullable=false)
     */
    private $typeId;

    /**
     * @var string
     *
     * @ORM\Column(name="class", type="string", length=254, nullable=true)
     */
    private $class;

    /**
     * @var \Router\Entity\RouterFilter
     *
     * @ORM\ManyToOne(targetEntity="Router\Entity\RouterFilter")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="parent_id", referencedColumnName="filter_id")
     * })
     */
    private $parent;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="Router\Entity\RouterEdge", mappedBy="filter")
     */
    private $edge;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->edge = new \Doctrine\Common\Collections\ArrayCollection();
    }


    /**
     * Get filterId
     *
     * @return integer 
     */
    public function getFilterId()
    {
        return $this->filterId;
    }

    /**
     * Set typeId
     *
     * @param string $typeId
     * @return RouterFilter
     */
    public function setTypeId($typeId)
    {
        $this->typeId = $typeId;

        return $this;
    }

    /**
     * Get typeId
     *
     * @return string 
     */
    public function getTypeId()
    {
        return $this->typeId;
    }

    /**
     * Set class
     *
     * @param string $class
     * @return RouterFilter
     */
    public function setClass($class)
    {
        $this->class = $class;

        return $this;
    }

    /**
     * Get class
     *
     * @return string 
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Set parent
     *
     * @param \Router\Entity\RouterFilter $parent
     * @return RouterFilter
     */
    public function setParent(\Router\Entity\RouterFilter $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return \Router\Entity\RouterFilter 
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Add edge
     *
     * @param \Router\Entity\RouterEdge $edge
     * @return RouterFilter
     */
    public function addEdge(\Router\Entity\RouterEdge $edge)
    {
        $this->edge[] = $edge;

        return $this;
    }

    /**
     * Remove edge
     *
     * @param \Router\Entity\RouterEdge $edge
     */
    public function removeEdge(\Router\Entity\RouterEdge $edge)
    {
        $this->edge->removeElement($edge);
    }

    /**
     * Get edge
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getEdge()
    {
        return $this->edge;
    }
}

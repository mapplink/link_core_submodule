<?php

namespace Router\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * RouterFilterData
 *
 * @ORM\Table(name="router_filter_data", indexes={@ORM\Index(name="filter_id_idx", columns={"parent_id"})})
 * @ORM\Entity(repositoryClass="Router\Repository\RouterFilterDataRepository")
 */
class RouterFilterData extends \Magelink\Entity\DoctrineBaseEntity
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
     * @var \Router\Entity\RouterFilter
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\OneToOne(targetEntity="Router\Entity\RouterFilter")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="parent_id", referencedColumnName="filter_id")
     * })
     */
    private $parent;



    /**
     * Set key
     *
     * @param string $key
     * @return RouterFilterData
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
     * @return RouterFilterData
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
     * @param \Router\Entity\RouterFilter $parent
     * @return RouterFilterData
     */
    public function setParent(\Router\Entity\RouterFilter $parent)
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
}

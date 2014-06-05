<?php

namespace Router\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * RouterTransformData
 *
 * @ORM\Table(name="router_transform_data", indexes={@ORM\Index(name="transform_id_idx", columns={"parent_id"})})
 * @ORM\Entity(repositoryClass="Router\Repository\RouterTransformDataRepository")
 */
class RouterTransformData extends \Magelink\Entity\DoctrineBaseEntity
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
     * @var \Router\Entity\RouterTransform
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\OneToOne(targetEntity="Router\Entity\RouterTransform")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="parent_id", referencedColumnName="transform_id")
     * })
     */
    private $parent;



    /**
     * Set key
     *
     * @param string $key
     * @return RouterTransformData
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
     * @return RouterTransformData
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
     * @param \Router\Entity\RouterTransform $parent
     * @return RouterTransformData
     */
    public function setParent(\Router\Entity\RouterTransform $parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return \Router\Entity\RouterTransform 
     */
    public function getParent()
    {
        return $this->parent;
    }
}

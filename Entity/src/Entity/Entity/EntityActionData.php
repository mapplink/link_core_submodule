<?php

namespace Entity\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * EntityActionData
 *
 * @ORM\Table(name="entity_action_data", indexes={@ORM\Index(name="parent_id_idx", columns={"parent_id"})})
 * @ORM\Entity(repositoryClass="Entity\Repository\EntityActionDataRepository")
 */
class EntityActionData extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="parent_id", type="bigint", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $parentId;

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
     * Set parentId
     *
     * @param integer $parentId
     * @return EntityActionData
     */
    public function setParentId($parentId)
    {
        $this->parentId = $parentId;

        return $this;
    }

    /**
     * Get parentId
     *
     * @return integer 
     */
    public function getParentId()
    {
        return $this->parentId;
    }

    /**
     * Set key
     *
     * @param string $key
     * @return EntityActionData
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
     * @return EntityActionData
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
}

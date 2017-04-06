<?php
/**
 * @category Router
 * @package Router\Entity
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Router\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * RouterTransform
 * @ORM\Table(name="router_transform", indexes={@ORM\Index(name="entity_type_id_idx", columns={"entity_type_id"}), @ORM\Index(name="src_attribute_idx", columns={"src_attribute"}), @ORM\Index(name="dest_attribute_idx", columns={"dest_attribute"})})
 * @ORM\Entity(repositoryClass="Router\Repository\RouterTransformRepository")
 */
class RouterTransform extends \Magelink\Entity\DataDBaseEntity
{
    protected $_simpleDataKey = 'getTransformId';
    /**
     * @var integer
     * @ORM\Column(name="transform_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $transformId;

    /**
     * @var integer
     * @ORM\Column(name="transform_type", type="string", length=45, nullable=false)
     */
    private $transformType;

    /**
     * @var boolean
     * @ORM\Column(name="enable_create", type="boolean", nullable=false)
     */
    private $enableCreate = '0';

    /**
     * @var boolean
     * @ORM\Column(name="enable_update", type="boolean", nullable=false)
     */
    private $enableUpdate = '0';

    /**
     * @var boolean
     * @ORM\Column(name="enable_delete", type="boolean", nullable=false)
     */
    private $enableDelete = '0';

    /**
     * @var integer
     * @ORM\Column(name="dest_attribute", type="integer", nullable=false)
     */
    private $destAttribute;

    /**
     * @var integer
     * @ORM\Column(name="entity_type_id", type="integer", nullable=false)
     */
    private $entityTypeId;

    /**
     * @var integer
     * @ORM\Column(name="src_attribute", type="integer", nullable=false)
     */
    private $srcAttribute;


    /**
     * Get transformId
     * @return integer
     */
    public function getTransformId()
    {
        return $this->transformId;
    }

    /**
     * Set transformType
     * @param string $transformType
     * @return RouterTransform
     */
    public function setTransformType($transformType)
    {
        $this->transformType = $transformType;

        return $this;
    }

    /**
     * Get transformType
     * @return string
     */
    public function getTransformType()
    {
        return $this->transformType;
    }

    /**
     * Set enableCreate
     *
     * @param boolean $enableCreate
     * @return RouterTransform
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
     * @return RouterTransform
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
     * @return RouterTransform
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
     * Set destAttribute
     *
     * @param integer $destAttribute
     * @return RouterTransform
     */
    public function setDestAttribute($destAttribute)
    {
        $this->destAttribute = $destAttribute;

        return $this;
    }

    /**
     * Get destAttribute
     *
     * @return integer
     */
    public function getDestAttribute()
    {
        return $this->destAttribute;
    }

    /**
     * Set entityTypeId
     *
     * @param integer $entityTypeId
     * @return RouterTransform
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

    /**
     * Set srcAttribute
     *
     * @param integer $srcAttribute
     * @return RouterTransform
     */
    public function setSrcAttribute($srcAttribute)
    {
        $this->srcAttribute = $srcAttribute;

        return $this;
    }

    /**
     * Get srcAttribute
     *
     * @return integer
     */
    public function getSrcAttribute()
    {
        return $this->srcAttribute;
    }
}

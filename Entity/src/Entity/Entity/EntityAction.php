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
 * EntityAction
 *
 * @ORM\Table(name="entity_action", indexes={@ORM\Index(name="entity_id_idx", columns={"entity_id"}), @ORM\Index(name="type", columns={"action_type"}), @ORM\Index(name="entity_type", columns={"entity_id", "action_type"})})
 * @ORM\Entity(repositoryClass="Entity\Repository\EntityActionRepository")
 */
class EntityAction extends \Magelink\Entity\DataDBaseEntity
{
    protected $_simpleDataKey = 'getId';
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="bigint", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="entity_id", type="bigint", nullable=false)
     */
    private $entityId;

    /**
     * @var string
     *
     * @ORM\Column(name="action_type", type="string", length=45, nullable=false)
     */
    private $actionType;



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
     * Set entityId
     *
     * @param integer $entityId
     * @return EntityAction
     */
    public function setEntityId($entityId)
    {
        $this->entityId = $entityId;

        return $this;
    }

    /**
     * Get entityId
     *
     * @return integer 
     */
    public function getEntityId()
    {
        return $this->entityId;
    }

    /**
     * Set actionType
     *
     * @param string $actionType
     * @return EntityAction
     */
    public function setActionType($actionType)
    {
        $this->actionType = $actionType;

        return $this;
    }

    /**
     * Get actionType
     *
     * @return string 
     */
    public function getActionType()
    {
        return $this->actionType;
    }
}

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
 * RouterStatType
 *
 * @ORM\Table(name="router_stat_type")
 * @ORM\Entity(repositoryClass="Router\Repository\RouterStatTypeRepository")
 */
class RouterStatType extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="count", type="bigint", nullable=false)
     */
    private $count = '0';

    /**
     * @var integer
     *
     * @ORM\Id
     * @ORM\Column(name="entity_type_id", type="integer", nullable=false)
     */
    private $entityTypeId;



    /**
     * Set count
     *
     * @param integer $count
     * @return RouterStatType
     */
    public function setCount($count)
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Get count
     *
     * @return integer 
     */
    public function getCount()
    {
        return $this->count;
    }


    /**
     * Set entityTypeId
     *
     * @param integer $entityTypeId
     * @return RouterStatType
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

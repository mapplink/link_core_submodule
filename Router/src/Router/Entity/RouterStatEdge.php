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
 * RouterStatEdge
 *
 * @ORM\Table(name="router_stat_edge")
 * @ORM\Entity(repositoryClass="Router\Repository\RouterStatEdgeRepository")
 */
class RouterStatEdge extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="count", type="bigint", nullable=false)
     */
    private $count = '0';

    /**
     * @var \Router\Entity\RouterEdge
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\OneToOne(targetEntity="Router\Entity\RouterEdge")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="edge_id", referencedColumnName="edge_id")
     * })
     */
    private $edge;



    /**
     * Set count
     *
     * @param integer $count
     * @return RouterStatEdge
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
     * Set edge
     *
     * @param \Router\Entity\RouterEdge $edge
     * @return RouterStatEdge
     */
    public function setEdge(\Router\Entity\RouterEdge $edge)
    {
        $this->edge = $edge;

        return $this;
    }

    /**
     * Get edge
     *
     * @return \Router\Entity\RouterEdge 
     */
    public function getEdge()
    {
        return $this->edge;
    }
}

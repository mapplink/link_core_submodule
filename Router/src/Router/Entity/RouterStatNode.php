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
 * RouterStatNode
 *
 * @ORM\Table(name="router_stat_node")
 * @ORM\Entity(repositoryClass="Router\Repository\RouterStatNodeRepository")
 */
class RouterStatNode extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="count_to", type="bigint", nullable=false)
     */
    private $countTo = '0';

    /**
     * @var integer
     *
     * @ORM\Column(name="count_from", type="bigint", nullable=false)
     */
    private $countFrom = '0';

    /**
     * @var \Node\Entity\Node
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\OneToOne(targetEntity="Node\Entity\Node")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="node_id", referencedColumnName="node_id")
     * })
     */
    private $node;



    /**
     * Set countTo
     *
     * @param integer $countTo
     * @return RouterStatNode
     */
    public function setCountTo($countTo)
    {
        $this->countTo = $countTo;

        return $this;
    }

    /**
     * Get countTo
     *
     * @return integer 
     */
    public function getCountTo()
    {
        return $this->countTo;
    }

    /**
     * Set countFrom
     *
     * @param integer $countFrom
     * @return RouterStatNode
     */
    public function setCountFrom($countFrom)
    {
        $this->countFrom = $countFrom;

        return $this;
    }

    /**
     * Get countFrom
     *
     * @return integer 
     */
    public function getCountFrom()
    {
        return $this->countFrom;
    }

    /**
     * Set node
     *
     * @param \Node\Entity\Node $node
     * @return RouterStatNode
     */
    public function setNode(\Node\Entity\Node $node)
    {
        $this->node = $node;

        return $this;
    }

    /**
     * Get node
     *
     * @return \Node\Entity\Node 
     */
    public function getNode()
    {
        return $this->node;
    }

}

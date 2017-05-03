<?php
/**
 * @package Report\Entity
 * @author Sean Yao
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Report\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Report
 *
 * @ORM\Table(name="report", indexes={@ORM\Index(name="type_id_r_idx", columns={"type_id"}), @ORM\Index(name="creator_id_r_idx", columns={"creator_id"})})
 * @ORM\Entity(repositoryClass="Report\Repository\ReportRepository")
 */
class Report extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="report_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $reportId;

    /**
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=254, nullable=false)
     */
    private $label;

    /**
     * @var \Magelink\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="Magelink\Entity\User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="creator_id", referencedColumnName="user_id")
     * })
     */
    private $creator;

    /**
     * @var \Report\Entity\ReportType
     *
     * @ORM\ManyToOne(targetEntity="Report\Entity\ReportType")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="type_id", referencedColumnName="type_id")
     * })
     */
    private $type;


    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="Magelink\Entity\User", inversedBy="report")
     * @ORM\JoinTable(name="report_favorite",
     *   joinColumns={
     *     @ORM\JoinColumn(name="report_id", referencedColumnName="report_id")
     *   },
     *   inverseJoinColumns={
     *     @ORM\JoinColumn(name="user_id", referencedColumnName="user_id")
     *   }
     * )
     */
    private $user;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->user = new \Doctrine\Common\Collections\ArrayCollection();
    }


    /**
     * Get reportId
     *
     * @return integer 
     */
    public function getReportId()
    {
        return $this->reportId;
    }

    /**
     * Set label
     *
     * @param string $label
     * @return Report
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Get label
     *
     * @return string 
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Set creator
     *
     * @param \Magelink\Entity\User $creator
     * @return Report
     */
    public function setCreator(\Magelink\Entity\User $creator = null)
    {
        $this->creator = $creator;

        return $this;
    }

    /**
     * Get creator
     *
     * @return \Magelink\Entity\User 
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * Set type
     *
     * @param \Report\Entity\ReportType $type
     * @return Report
     */
    public function setType(\Report\Entity\ReportType $type = null)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return \Report\Entity\ReportType 
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Add user
     *
     * @param \Magelink\Entity\User $user
     * @return Report
     */
    public function addUser(\Magelink\Entity\User $user)
    {
        $this->user[] = $user;

        return $this;
    }

    /**
     * Remove user
     *
     * @param \Magelink\Entity\User $user
     */
    public function removeUser(\Magelink\Entity\User $user)
    {
        $this->user->removeElement($user);
    }

    /**
     * Get user
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getUser()
    {
        return $this->user;
    }

}

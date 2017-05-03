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
 * ReportData
 *
 * @ORM\Table(name="report_data", indexes={@ORM\Index(name="report_id_idx", columns={"parent_id"})})
 * @ORM\Entity(repositoryClass="Report\Repository\ReportDataRepository")
 */
class ReportData extends \Magelink\Entity\DoctrineBaseEntity
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
     * @ORM\Column(name="value", type="string", length=254, nullable=true)
     */
    private $value;

    /**
     * @var \Report\Entity\Report
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\OneToOne(targetEntity="Report\Entity\Report")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="parent_id", referencedColumnName="report_id")
     * })
     */
    private $parent;



    /**
     * Set key
     *
     * @param string $key
     * @return ReportData
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
     * @return ReportData
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
     * @param \Report\Entity\Report $parent
     * @return ReportData
     */
    public function setParent(\Report\Entity\Report $parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return \Report\Entity\Report 
     */
    public function getParent()
    {
        return $this->parent;
    }

}

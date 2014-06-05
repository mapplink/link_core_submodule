<?php

namespace Report\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ReportAttribute
 *
 * @ORM\Table(name="report_attribute", uniqueConstraints={@ORM\UniqueConstraint(name="report_attribute", columns={"report_id", "attribute_id"})}, indexes={@ORM\Index(name="report_id_idx", columns={"report_id"}), @ORM\Index(name="attribute_id_idx", columns={"attribute_id"})})
 * @ORM\Entity(repositoryClass="Report\Repository\ReportAttributeRepository")
 */
class ReportAttribute extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="report_attribute_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $reportAttributeId;

    /**
     * @var boolean
     *
     * @ORM\Column(name="position", type="boolean", nullable=false)
     */
    private $position = '0';

    /**
     * @var boolean
     *
     * @ORM\Column(name="grouped", type="boolean", nullable=false)
     */
    private $grouped = '0';

    /**
     * @var boolean
     *
     * @ORM\Column(name="sorted", type="boolean", nullable=false)
     */
    private $sorted = '0';

    /**
     * @var boolean
     *
     * @ORM\Column(name="aggregated", type="boolean", nullable=false)
     */
    private $aggregated = '0';

    /**
     * @var boolean
     *
     * @ORM\Column(name="delta_part", type="boolean", nullable=false)
     */
    private $deltaPart = '0';

    /**
     * @var boolean
     *
     * @ORM\Column(name="timeseries_key", type="boolean", nullable=false)
     */
    private $timeseriesKey = '0';

    /**
     * @var string
     *
     * @ORM\Column(name="filter_from", type="string", length=254, nullable=true)
     */
    private $filterFrom;

    /**
     * @var string
     *
     * @ORM\Column(name="filter_to", type="string", length=254, nullable=true)
     */
    private $filterTo;

    /**
     * @var boolean
     *
     * @ORM\Column(name="filter_delta", type="boolean", nullable=false)
     */
    private $filterDelta = '0';

    /**
     * @var \Report\Entity\Report
     *
     * @ORM\ManyToOne(targetEntity="Report\Entity\Report")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="report_id", referencedColumnName="report_id")
     * })
     */
    private $report;



    /**
     * Get reportAttributeId
     *
     * @return integer 
     */
    public function getReportAttributeId()
    {
        return $this->reportAttributeId;
    }

    /**
     * Set position
     *
     * @param boolean $position
     * @return ReportAttribute
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Get position
     *
     * @return boolean 
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set grouped
     *
     * @param boolean $grouped
     * @return ReportAttribute
     */
    public function setGrouped($grouped)
    {
        $this->grouped = $grouped;

        return $this;
    }

    /**
     * Get grouped
     *
     * @return boolean 
     */
    public function getGrouped()
    {
        return $this->grouped;
    }

    /**
     * Set sorted
     *
     * @param boolean $sorted
     * @return ReportAttribute
     */
    public function setSorted($sorted)
    {
        $this->sorted = $sorted;

        return $this;
    }

    /**
     * Get sorted
     *
     * @return boolean 
     */
    public function getSorted()
    {
        return $this->sorted;
    }

    /**
     * Set aggregated
     *
     * @param boolean $aggregated
     * @return ReportAttribute
     */
    public function setAggregated($aggregated)
    {
        $this->aggregated = $aggregated;

        return $this;
    }

    /**
     * Get aggregated
     *
     * @return boolean 
     */
    public function getAggregated()
    {
        return $this->aggregated;
    }

    /**
     * Set deltaPart
     *
     * @param boolean $deltaPart
     * @return ReportAttribute
     */
    public function setDeltaPart($deltaPart)
    {
        $this->deltaPart = $deltaPart;

        return $this;
    }

    /**
     * Get deltaPart
     *
     * @return boolean 
     */
    public function getDeltaPart()
    {
        return $this->deltaPart;
    }

    /**
     * Set timeseriesKey
     *
     * @param boolean $timeseriesKey
     * @return ReportAttribute
     */
    public function setTimeseriesKey($timeseriesKey)
    {
        $this->timeseriesKey = $timeseriesKey;

        return $this;
    }

    /**
     * Get timeseriesKey
     *
     * @return boolean 
     */
    public function getTimeseriesKey()
    {
        return $this->timeseriesKey;
    }

    /**
     * Set filterFrom
     *
     * @param string $filterFrom
     * @return ReportAttribute
     */
    public function setFilterFrom($filterFrom)
    {
        $this->filterFrom = $filterFrom;

        return $this;
    }

    /**
     * Get filterFrom
     *
     * @return string 
     */
    public function getFilterFrom()
    {
        return $this->filterFrom;
    }

    /**
     * Set filterTo
     *
     * @param string $filterTo
     * @return ReportAttribute
     */
    public function setFilterTo($filterTo)
    {
        $this->filterTo = $filterTo;

        return $this;
    }

    /**
     * Get filterTo
     *
     * @return string 
     */
    public function getFilterTo()
    {
        return $this->filterTo;
    }

    /**
     * Set filterDelta
     *
     * @param boolean $filterDelta
     * @return ReportAttribute
     */
    public function setFilterDelta($filterDelta)
    {
        $this->filterDelta = $filterDelta;

        return $this;
    }

    /**
     * Get filterDelta
     *
     * @return boolean 
     */
    public function getFilterDelta()
    {
        return $this->filterDelta;
    }

    /**
     * Set report
     *
     * @param \Report\Entity\Report $report
     * @return ReportAttribute
     */
    public function setReport(\Report\Entity\Report $report = null)
    {
        $this->report = $report;

        return $this;
    }

    /**
     * Get report
     *
     * @return \Report\Entity\Report 
     */
    public function getReport()
    {
        return $this->report;
    }
}

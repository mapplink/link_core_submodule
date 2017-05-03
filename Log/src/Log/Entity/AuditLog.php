<?php
/**
 * @package Log\Entity
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Log\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * AuditLog
 *
 * @ORM\Table(name="audit_log", indexes={@ORM\Index(name="user_id_idx", columns={"user_id"})})
 * @ORM\Entity(repositoryClass="Log\Repository\AuditLogRepository")
 */
class AuditLog extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="log_id", type="bigint", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $logId;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="timestamp", type="datetime", nullable=false)
     */
    private $timestamp = 'CURRENT_TIMESTAMP';

    /**
     * @var string
     *
     * @ORM\Column(name="summary", type="string", length=254, nullable=false)
     */
    private $summary;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=45, nullable=false)
     */
    private $code;

    /**
     * @var string
     *
     * @ORM\Column(name="data", type="text", nullable=false)
     */
    private $data;

    /**
     * @var \Magelink\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="\Magelink\Entity\User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="user_id", referencedColumnName="user_id")
     * })
     */
    private $user;



    public function getId()
    {
        return $this->getLogId();
    }

    /**
     * Get logId
     *
     * @return integer 
     */
    public function getLogId()
    {
        return $this->logId;
    }

    /**
     * Set timestamp
     *
     * @param \DateTime $timestamp
     * @return AuditLog
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Get timestamp
     *
     * @return \DateTime 
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Set summary
     *
     * @param string $summary
     * @return AuditLog
     */
    public function setSummary($summary)
    {
        $this->summary = $summary;

        return $this;
    }

    /**
     * Get summary
     *
     * @return string 
     */
    public function getSummary()
    {
        return $this->summary;
    }

    /**
     * Set code
     *
     * @param string $code
     * @return AuditLog
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code
     *
     * @return string 
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set data
     *
     * @param string $data
     * @return AuditLog
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get data
     *
     * @return string 
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set user
     *
     * @param \Magelink\Entity\User $user
     * @return AuditLog
     */
    public function setUser(\Magelink\Entity\User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \Magelink\Entity\User 
     */
    public function getUser()
    {
        return $this->user;
    }
}

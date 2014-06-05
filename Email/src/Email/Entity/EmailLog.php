<?php

namespace Email\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * EmailLog
 *
 * @ORM\Table(name="email_log", indexes={@ORM\Index(name="email_id_idx", columns={"email_id"})})
 * @ORM\Entity(repositoryClass="Email\Repository\EmailLogRepository")
 */
class EmailLog extends \Magelink\Entity\DoctrineBaseEntity
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
     * @var boolean
     *
     * @ORM\Column(name="success", type="boolean", nullable=false)
     */
    private $success;

    /**
     * @var string
     *
     * @ORM\Column(name="message", type="string", length=254, nullable=false)
     */
    private $message;

    /**
     * @var \Email\Entity\Email
     *
     * @ORM\ManyToOne(targetEntity="Email\Entity\Email")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="email_id", referencedColumnName="email_id")
     * })
     */
    private $email;


    /**
     * 
     * @see this->getLogId()
     */
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
     * @return EmailLog
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
     * Set success
     *
     * @param boolean $success
     * @return EmailLog
     */
    public function setSuccess($success)
    {
        $this->success = $success;

        return $this;
    }

    /**
     * Get success
     *
     * @return boolean 
     */
    public function getSuccess()
    {
        return $this->success;
    }

    /**
     * Set message
     *
     * @param string $message
     * @return EmailLog
     */
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Get message
     *
     * @return string 
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set email
     *
     * @param \Email\Entity\Email $email
     * @return EmailLog
     */
    public function setEmail(\Email\Entity\Email $email = null)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return \Email\Entity\Email 
     */
    public function getEmail()
    {
        return $this->email;
    }
}

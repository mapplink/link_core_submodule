<?php

namespace Email\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Email
 *
 * @ORM\Table(name="email", indexes={@ORM\Index(name="template_id_idx", columns={"template_id"}), @ORM\Index(name="event_id_idx", columns={"action_id"})})
 * @ORM\Entity(repositoryClass="Email\Repository\EmailRepository")
 */
class Email extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="email_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $emailId;

    /**
     * @var string
     *
     * @ORM\Column(name="to_name", type="string", length=254, nullable=false)
     */
    private $toName;

    /**
     * @var string
     *
     * @ORM\Column(name="to_email", type="string", length=254, nullable=false)
     */
    private $toEmail;

    /**
     * @var boolean
     *
     * @ORM\Column(name="status", type="boolean", nullable=false)
     */
    private $status = '0';

    /**
     * @var integer
     *
     * @ORM\Column(name="action_id", type="bigint", nullable=true)
     */
    private $actionId;

    /**
     * @var \Email\Entity\EmailTemplate
     *
     * @ORM\ManyToOne(targetEntity="Email\Entity\EmailTemplate")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="template_id", referencedColumnName="template_id")
     * })
     */
    private $template;



    /**
     * Get emailId
     *
     * @return integer 
     */
    public function getEmailId()
    {
        return $this->emailId;
    }

    /**
     * Set toName
     *
     * @param string $toName
     * @return Email
     */
    public function setToName($toName)
    {
        $this->toName = $toName;

        return $this;
    }

    /**
     * Get toName
     *
     * @return string 
     */
    public function getToName()
    {
        return $this->toName;
    }

    /**
     * Set toEmail
     *
     * @param string $toEmail
     * @return Email
     */
    public function setToEmail($toEmail)
    {
        $this->toEmail = $toEmail;

        return $this;
    }

    /**
     * Get toEmail
     *
     * @return string 
     */
    public function getToEmail()
    {
        return $this->toEmail;
    }

    /**
     * Set status
     *
     * @param boolean $status
     * @return Email
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status
     *
     * @return boolean 
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set actionId
     *
     * @param integer $actionId
     * @return Email
     */
    public function setActionId($actionId)
    {
        $this->actionId = $actionId;

        return $this;
    }

    /**
     * Get actionId
     *
     * @return integer 
     */
    public function getActionId()
    {
        return $this->actionId;
    }

    /**
     * Set template
     *
     * @param \Email\Entity\EmailTemplate $template
     * @return Email
     */
    public function setTemplate(\Email\Entity\EmailTemplate $template = null)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * Get template
     *
     * @return \Email\Entity\EmailTemplate 
     */
    public function getTemplate()
    {
        return $this->template;
    }
}

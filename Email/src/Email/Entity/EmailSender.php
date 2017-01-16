<?php
/**
 * @category Email
 * @package Entity
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2016 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Email\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * EmailSender
 * @ORM\Table(name="email_sender", indexes={@ORM\Index(name="store_id_idx", columns={"store_id"})})
 * @ORM\Entity(repositoryClass="Email\Repository\EmailSenderRepository")
 */
class EmailSender extends \Magelink\Entity\DoctrineBaseEntity
{

    /** @var integer
     * @ORM\Column(name="sender_id", type="smallint", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY") */
    private $senderId;
    /** @var string
     * @ORM\Column(name="store_id", type="string", length=254, nullable=false) */
    private $storeId;
    /** @var string
     * @ORM\Column(name="code", type="string", length=254, nullable=false) */
    private $code;
    /** @var string
     * @ORM\Column(name="sender_name", type="string", length=254, nullable=false) */
    private $senderName;
    /** @var string
     * @ORM\Column(name="sender_email", type="string", length=254, nullable=false) */
    private $senderEmail;


    /**
     * @return integer $this->senderId
     */
    public function getSenderId()
    {
        return $this->senderId;
    }

    /**
     * @see $this->getSenderId()
     */
    public function getId()
    {
        return $this->getSenderId();
    }

    /**
     * @param string $storeId
     * @return EmailSender
     */
    public function setStoreId($storeId)
    {
        $this->storeId = $storeId;
        return $this;
    }

    /**
     * @return string
     */
    public function getStoreId()
    {
        return $this->storeId;
    }

    /**
     * @param string $code
     * @return EmailTemplate
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param string $senderName
     * @return EmailTemplate
     */
    public function setSenderName($senderName)
    {
        $this->senderName = $senderName;
        return $this;
    }

    /**
     * @return string
     */
    public function getSenderName()
    {
        return $this->senderName;
    }

    /**
     * @param string $senderEmail
     * @return EmailTemplate
     */
    public function setSenderEmail($senderEmail)
    {
        $this->senderEmail = $senderEmail;
        return $this;
    }

    /**
     * @return string
     */
    public function getSenderEmail()
    {
        return $this->senderEmail;
    }

}

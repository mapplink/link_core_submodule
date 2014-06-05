<?php

namespace Email\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * EmailTemplate
 *
 * @ORM\Table(name="email_template", indexes={@ORM\Index(name="code_idx", columns={"code"})})
 * @ORM\Entity(repositoryClass="Email\Repository\EmailTemplateRepository")
 */
class EmailTemplate extends \Magelink\Entity\DoctrineBaseEntity
{

    // Mime Types
    const 
        MIME_TYPE_TEXT = 'text',
        MIME_TYPE_HTML = 'html'
    ;

    /**
     * @var integer
     *
     * @ORM\Column(name="template_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $templateId;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=45, nullable=false)
     */
    private $code;

    /**
     * @var string
     *
     * @ORM\Column(name="human_name", type="string", length=254, nullable=false)
     */
    private $humanName;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=254, nullable=false)
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="body", type="text", nullable=false)
     */
    private $body;

    /**
     * @var string
     *
     * @ORM\Column(name="sender_name", type="string", length=254, nullable=false)
     */
    private $senderName;

    /**
     * @var string
     *
     * @ORM\Column(name="sender_email", type="string", length=254, nullable=false)
     */
    private $senderEmail;

    /**
     * @var string
     *
     * @ORM\Column(name="mime_type", type="string", length=25, nullable=false)
     */
    private $mimeType;

    /**
     * @var \Email\Entity\EmailTemplateSection
     *
     * @ORM\ManyToOne(targetEntity="Email\Entity\EmailTemplateSection")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="email_template_section_id", referencedColumnName="id")
     * })
     */
    private $emailTemplateSection;

    /**
     * @var ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="Email\Entity\EmailTemplateParam", mappedBy="emailTemplate")
     */
    private $params;



    /**
     * Constructor
     */
    public function __construct()
    {
        $this->params = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * @return \Email\Entity\EmailTemplateParam[]
     */
    public function getParams() { 
        
        return $this->params; 
    }

    /**
     * Get all mime types
     * @return array
     */
    public static function getAllMimeTypes()
    {
        return array(
            self::MIME_TYPE_TEXT,
            self::MIME_TYPE_HTML,
        );
    }

    /**
     * @see $this->getTemplateId()
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->getTemplateId();
    }

    /**
     * Get templateId
     *
     * @return integer 
     */
    public function getTemplateId()
    {
        return $this->templateId;
    }

    /**
     * Set code
     *
     * @param string $code
     * @return EmailTemplate
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
     * Set humanName
     *
     * @param string $humanName
     * @return EmailTemplate
     */
    public function setHumanName($humanName)
    {
        $this->humanName = $humanName;

        return $this;
    }

    /**
     * Get humanName
     *
     * @return string 
     */
    public function getHumanName()
    {
        return $this->humanName;
    }

    /**
     * Set title
     *
     * @param string $title
     * @return EmailTemplate
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string 
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set body
     *
     * @param string $body
     * @return EmailTemplate
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Get body
     *
     * @return string 
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Set senderName
     *
     * @param string $senderName
     * @return EmailTemplate
     */
    public function setSenderName($senderName)
    {
        $this->senderName = $senderName;

        return $this;
    }

    /**
     * Get senderName
     *
     * @return string 
     */
    public function getSenderName()
    {
        return $this->senderName;
    }

    /**
     * Set senderEmail
     *
     * @param string $senderEmail
     * @return EmailTemplate
     */
    public function setSenderEmail($senderEmail)
    {
        $this->senderEmail = $senderEmail;

        return $this;
    }

    /**
     * Get senderEmail
     *
     * @return string 
     */
    public function getSenderEmail()
    {
        return $this->senderEmail;
    }

     /**
     * Set emailTemplateSection
     *
     * @param string $emailTemplateSection
     * @return EmailTemplate
     */
    public function setEmailTemplateSection($emailTemplateSection)
    {
        $this->emailTemplateSection = $emailTemplateSection;

        return $this;
    }

    /**
     * Get emailTemplateSection
     *
     * @return EmailTemplateSection
     */
    public function getEmailTemplateSection()
    {
        return $this->emailTemplateSection;
    }

     /**
     * Set mimeType
     *
     * @param string $mimeType
     * @return EmailTemplate
     */
    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    /**
     * Get mimeType
     *
     * @return string
     */
    public function getMimeType()
    {
        return $this->mimeType;
    }

    /**
     * Get the real mime type used for email header
     * @return [type] [description]
     */
    public function getMimeTypeForEmail()
    {
        switch ($this->mimeType) {
            case self::MIME_TYPE_HTML:
                return 'text/html';
            default:
                return 'text/plain';
        }
    }

    /**
     * Check if it is a HTML email
     * @return boolean
     */
    public function isHTML()
    {
        return $this->mimeType == self::MIME_TYPE_HTML;
    }
}

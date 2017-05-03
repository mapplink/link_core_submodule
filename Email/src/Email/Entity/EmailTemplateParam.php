<?php
/*
 * @package Email\Entity
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Email\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * EmailTemplateParam
 *
 * @ORM\Table(name="email_template_param")
 * @ORM\Entity(repositoryClass="Email\Repository\EmailTemplateParamRepository")
 */
class EmailTemplateParam extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="`key`", type="string", length=45, nullable=false)
     */
    private $key;

    /**
     * @var \Email\Entity\EmailTemplate
     *
     * @ORM\ManyToOne(targetEntity="Email\Entity\EmailTemplate", inversedBy="params")
     * @ORM\JoinColumn(name="email_template_id", referencedColumnName="template_id")
     */
    private $emailTemplate;


    /* get Id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /* set Id
     *
     * @return EmailTemplateSection
     */
    public function setId($id)
    {
        $this->id = $id;

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
     * Set key
     *
     * @return EmailTemplateParam
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

     /**
     * Get template
     *
     * @return string
     */
    public function getEmailTemplate()
    {
        return $this->emailTemplate;
    }

    /**
     * Set template
     *
     * @return EmailTemplateParam
     */
    public function setEmailTemplate($emailTemplate)
    {
        $this->emailTemplate = $emailTemplate;

        return $this;
    }

    /**
     * Get template name
     */
    public function getTemplateName()
    {
        if ($template = $this->getEmailTemplate()) {
            return $template->getHumanName();
        }
    }

}
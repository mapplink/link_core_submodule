<?php
/**
 * Email\Mail
 *
 * @category    Email
 * @package     Email\Service
 * @author      Sean Yao <sean@lero9.com>
 * @copyright   Copyright (c) 2014 LERO9 Ltd.
 * @license     Commercial - All Rights Reserved
 */

namespace Email\Mail;

/**
 * Mailer abstract class
 */
abstract class AbstractDatabaseTemplateMailer extends BaseMailer
{

    /**
     * @var \Email\Entity\EmailTemplate
     */
    protected $template = null;

    protected
        $subjectParams   = array(),
        $templateParams  = array()
    ;

    /**
     * Get EmailTemplate Repository
     */
    protected function getEmailTemplateRepo()
    {
        return $this->getRepo('\Email\Entity\EmailTemplate');
    }

    /**
     * Get Doctrine repository
     * @param  string $EntityNmae 
     * @return mixed
     */
    protected function getRepo($entityNmae)
    {
        return $this->getEntityManager()
            ->getRepository($entityNmae);
    }

    /**
     * Get Doctrine EntityManager
     * @return object
     */
    protected function getEntityManager()
    {
        return $this->getServiceLocator()
            ->get('Doctrine\ORM\EntityManager');
    } 

    /**
     * Get template entity by sectionid and code
     * @param  integer $sectionId
     * @param  string  $code     
     * @return \Email\Entity\EmailTemplate
     */
    protected function getTemplate($sectionId, $code = null)
    {
        return $this->getEmailTemplateRepo()->getTemplate($sectionId, $code);
    }

    /**
     * Get templatess by sectionid
     * @param  integer $sectionId  
     * @return array
     */
    protected function getTemplatesBySection($sectionId)
    {
        return $this->getEmailTemplateRepo()->getTemplatesBySection($sectionId);
    }

    /**
     * Set up template
     */
    protected function setupTemplate(){}

    /**
     * Init before sending
     */
    protected function init()
    {   
        $this->setupTemplate();
        $this->setBodyParams();
        $this->getMessage()->setFrom($this->template->getSenderEmail(), $this->template->getSenderName());
        $this->loadSubject();
        $this->loadBody();
    }

    /**
     * Load email subject
     */
    protected function loadSubject()
    {   
        $subject = $this->template->getTitle();
        $subject = self::applyParams($subject, $this->subjectParams);
        $this->setTitle($subject);
    }

    /**
     * Load email body
     */
    protected function loadBody()
    {   
        $body = $this->template->getBody();
        $body = self::applyParams($body, $this->templateParams);
        $this->setBody($body, $this->template->getMimeTypeForEmail());
    }

    /**
     * Apply params to content
     * @param string $content
     * @param array $params
     * @return string
     */
    protected static function applyParams($content, array $params) 
    {
        foreach ($params as $search => $replace) {
            $content = str_replace('{{' . $search . '}}', $replace, $content);
        }

        return $content;
    }
}
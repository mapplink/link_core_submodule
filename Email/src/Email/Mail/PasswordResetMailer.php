<?php
/*
 * @package Email\Mail
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Email\Mail;


class PasswordResetMailer extends AbstractFileTemplateMailer
{
    /** @var string $this->url  Url to password reset */
    protected $url;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->getMessage()->setSubject('Password Reset');
    }

    /**
     * set Url
     * @param string $url
     */
    public function setResetUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Get Url
     * @return string
     */
    public function getResetUrl()
    {
        return $this->url;
    }

    /**
     * Send email
     */
    public function send()
    {
        $this->setBody($this->renderTemplate(array(
            'url' => $this->getResetUrl(), 
            'userDisplayName' => $this->getUserRecipient()->getDisplayName()
        )));
        parent::send();
    }
    
}
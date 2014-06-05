<?php

namespace Email\Mail;

class PasswordResetMailer extends AbstractFileTemplateMailer
{
    protected 
        $url // Url to password reset 
    ;

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
<?php
/*
 * @package Email\Mail
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Email\Mail;

use Zend\Mail\Message;
use Zend\Mail\Transport\Sendmail;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;


abstract class BaseMailer implements ServiceLocatorAwareInterface
{
    /** @var \Zend\Mail\Message $message */
    protected $message;
    /** @var string */
    protected $userRecipient;
    /**
     * All recipients of email
     * @var array
     */
    protected $allRecipients;
    /** @var \Zend\Mail\Transport\Sendmail $transport */
    protected $transport;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->message = new Message();
        $this->message->setEncoding("UTF-8");
        $this->transport = new Sendmail();
    }

    abstract protected function init();

    /**
     * Send email
     */
    public function send()
    {
        $this->init();
        foreach ($this->getAllRecipients() as $email=>$name) {
            $this->addTo($email, $name);
        }

        return $this->transport->send($this->message);
    }

     /**
     * Get email message object
     * @return \Zend\Mail\Message
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set email message object
     * @return \Email\Mail\BaseMailer
     */
    public function setBody($body,  $type = 'text/plain')
    {

        $mimePart = new MimePart($body);
        $mimePart->type = $type;
        $mimeMessage = new MimeMessage();
        $mimeMessage->setParts(array($mimePart));

        $this->getMessage()->setBody($mimeMessage);

        return $this;
    }

    public function setTitle($title)
    {
        $this->getMessage()->setSubject($title);

        return $this;
    }

    /**
     * Add recipient
     * @param array $recipient
     * @return $this
     */
    public function addTo($recipient)
    {
        $this->getMessage()->addTo($recipient);

        return $this;
    }

    /**
     * Set Recipients
     * @param array $recipients
     * @return $this
     */
    public function setAllRecipients(array $recipients)
    {
        $this->allRecipients = $recipients;
        return $this;
    }

    /**
     * Get Recipients
     * @return array
     */
    public function getAllRecipients()
    {
        $recipients = $this->allRecipients;
        if ($recipient = $this->getUserRecipient()) {
            $recipients[$recipient->getEmail()] = $recipient->getDisplayName();
        }

        return $recipients;
    }

    /**
     * Set User
     * @param  \Magelink\Entity\User
     * @return $this
     */
    public function setUserRecipient(\Magelink\Entity\User $user)
    {
        $this->userRecipient = $user;
        return $this;
    }

    /**
     * Get User
     * @return \Magelink\Entity\User
     */
    public function getUserRecipient()
    {
        return $this->userRecipient;
    }

    /**
     * @var ServiceLocatorInterface
     */
    protected $_serviceLocator;

     /**
     * Set service locator
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->_serviceLocator = $serviceLocator;
    }

    /**
     * Get service locator
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->_serviceLocator;
    }

}

<?php
/**
 * Email\Mail
 *
 * @category    Email
 * @package     Email\Service
 * @author      Seo Yao
 * @copyright   Copyright (c) 2014 LERO9 Ltd.
 * @license     Commercial - All Rights Reserved
 */

namespace Email\Mail;

/**
 * Mailer Factory
 */
class MailerFactory
{   
    protected $config; //Site config

    /**
     * Constructor
     */
    public function __construct($serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    /**
     * Load mailer
     * @param  string $name 
     * @param  array  $args
     * @return BaseMailer
     */
    public function loadMailer($name, $args = array())
    {
        $classRelection = new \ReflectionClass(__NAMESPACE__ . '\\' . $name . 'Mailer');

        $mailer = $classRelection->newInstanceArgs($args);
        $mailer->setServiceLocator($this->serviceLocator);

        return $mailer;
    }
}
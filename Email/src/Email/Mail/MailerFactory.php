<?php
/*
 * @package Email\Mail
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
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
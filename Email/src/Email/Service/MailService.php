<?php
/**
 * Email\Service
 *
 * @category    Email
 * @package     Email\Service
 * @author      Sean Yao <sean@lero9.com>
 * @copyright   Copyright (c) 2014 LERO9 Ltd.
 * @license     Commercial - All Rights Reserved
 */


namespace Email\Service;

use Zend\ServiceManager\FactoryInterface;
use Email\Mail\MailerFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

class MailService implements FactoryInterface
{   
    /**
     * Load MailerFactory as a service 
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return new MailerFactory($serviceLocator);
    }
}
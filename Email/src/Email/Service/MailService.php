<?php
/**
 * Email\Service
 * @category Email
 * @package Email\Service
 * @author Seo Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Email\Service;

use Zend\ServiceManager\FactoryInterface;
use Email\Mail\MailerFactory;
use Zend\ServiceManager\ServiceLocatorInterface;


class MailService implements FactoryInterface
{   
    /**
     * Load MailerFactory as a service 
     * @param ServiceLocatorInterface $serviceLocator
     * @return MailerFactory|mixed
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return new MailerFactory($serviceLocator);
    }

}
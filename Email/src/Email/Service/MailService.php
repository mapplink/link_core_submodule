<?php
/**
 * @package Email\Service
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
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

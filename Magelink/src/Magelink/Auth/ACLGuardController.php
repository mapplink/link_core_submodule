<?php
/**
 * @package Magelink\Auth
 * @author Sean Yao
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Magelink\Auth;

use BjyAuthorize\Guard\Controller as BaseGuardController;
use Zend\Mvc\MvcEvent;
use Zend\Console\Request as ConsoleRequest;

class ACLGuardController extends BaseGuardController
{  

    /**
    * @see parent::onDispatch()
    * @param  MvcEvent $event
    * @return 
    */
    public function onDispatch(MvcEvent $event)
    {
        if (!($event->getRequest() instanceof ConsoleRequest) || php_sapi_name() != 'cli') {
            parent::onDispatch($event);
        }
    }

}
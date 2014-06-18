<?php
namespace Magelink\Auth;

use BjyAuthorize\Guard\Route as BaseGuardRoute;
use Zend\Mvc\MvcEvent;
use Zend\Console\Request as ConsoleRequest;


class ACLGuardRoute extends BaseGuardRoute
{  
    /**
    * @see parent::onRoute()
    * @param  MvcEvent $event
    * @return 
    */
    public function onRoute(MvcEvent $event)
    {
        if (($event->getRequest() instanceof ConsoleRequest) && (php_sapi_name() == 'cli')) {
            return NULL;
        }else{
            return parent::onRoute($event);
        }
    }
}
<?php


namespace Magelink\Auth;

use BjyAuthorize\Guard\Controller as BaseGuardController;
use Zend\Mvc\MvcEvent;
use Zend\Console\Request as ConsoleRequest;

class ACLGuardController extends BaseGuardController
{  
    /**
    * @see pareng::onDispatch()
    * @param  MvcEvent $event
    * @return 
    */
    public function onDispatch(MvcEvent $event)
    {
        if (
            ($event->getRequest() instanceof ConsoleRequest)
            && (php_sapi_name() == 'cli')
        ) {
            return ;
        }

        return parent::onDispatch($event);
    }
}
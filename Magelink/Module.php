<?php
namespace Magelink;

use Application\AbstractModule;


class Module extends AbstractModule
{

    /**
     * @param $event
     * @return void
     */
    public function onBootstrap($event)
    {
        $serviceManager = $event->getApplication()->getServiceManager();
        $dbAdapter = $serviceManager->get('zend_db');

        // Register a render event
        $app = $event->getParam('application');
        $app->getEventManager()->attach('render', array($this, 'setLayoutTitle'));
    }

    /**
     * @param  \Zend\Mvc\MvcEvent $event The MvcEvent instance
     * @return void
     */
    public function setLayoutTitle($event)
    {
        $matches    = $event->getRouteMatch();
        if($matches){
            $action     = $matches->getParam('action');
            $controller = $matches->getParam('controller');


            $viewHelperManager = $event->getApplication()->getServiceManager()->get('viewHelperManager');
            $headTitleHelper   = $viewHelperManager->get('headTitle');

            $headTitleHelper->setSeparator(' - ');
            $headTitleHelper->append('HOPS');
            $controllerParts = explode('\\',  $controller);
            $headTitleHelper->append(end($controllerParts));
            $headTitleHelper->append($action);
        }
    }

}

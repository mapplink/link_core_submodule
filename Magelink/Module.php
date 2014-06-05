<?php
namespace Magelink;

class Module {

	public function getConfig(){
		return include __DIR__ . '/config/module.config.php';
	}

	public function getAutoloaderConfig(){
		return array(
			'Zend\Loader\StandardAutoloader' => array(
				'namespaces' => array(
					__NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__
				)
			)
		);
	}

	public function onBootstrap($e)
	{
	    $serviceManager = $e->getApplication()->getServiceManager();
	    $dbAdapter = $serviceManager->get('zend_db');

	    // Register a render event
        $app = $e->getParam('application');
        $app->getEventManager()->attach('render', array($this, 'setLayoutTitle'));
	}

	/**
     * @param  \Zend\Mvc\MvcEvent $e The MvcEvent instance
     * @return void
     */
    public function setLayoutTitle($e)
    {
  		$matches    = $e->getRouteMatch();
        if($matches){
            $action     = $matches->getParam('action');
            $controller = $matches->getParam('controller');


            $viewHelperManager = $e->getApplication()->getServiceManager()->get('viewHelperManager');
            $headTitleHelper   = $viewHelperManager->get('headTitle');


            $headTitleHelper->setSeparator(' - ');
            $headTitleHelper->append('HOPS');
            $controllerParts = explode('\\',  $controller);
            $headTitleHelper->append(end($controllerParts));
            $headTitleHelper->append($action);
        }
   
    }

}

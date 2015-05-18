<?php
/*
 * @category Application
 * @package Application
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Application;

use Zend\Console\Adapter\AdapterInterface;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\ModuleManager\Feature\ConsoleBannerProviderInterface;


class Module extends MagelinkModule implements ConsoleBannerProviderInterface
{

    /**
     * @param MvcEvent $event
     */
    public function onBootstrap(MvcEvent $event)
    {
        $eventManager = $event->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
    }

    /**
     * Returns a string containing a banner text, that describes the module and/or the application.
     * The banner is shown in the console window, when the user supplies invalid command-line parameters or invokes
     * the application with no parameters.
     *
     * The method is called with active Zend\Console\Adapter\AdapterInterface that can be used to directly access Console and send
     * output.
     *
     * @param AdapterInterface $console
     * @return string|NULL $printOut
     */
    public function getConsoleBanner(AdapterInterface $console)
    {
        return "===------------------------------------------------===\n"
        ."    Welcome to the Magelink Command Line Tool v3.0    \n"
        ."===------------------------------------------------===\n\n";
    }

}


class MagelinkModule
{

    /** @var \ReflectionClass|NULL $_reflection */
    protected $_reflection = NULL;

    /** @var string|NULL $_namespace */
    protected $_namespace = NULL;

    /** @var string|NULL $_filename  */
    protected $_filename = NULL;

    /** @var string|NULL $_directoryname  */
    protected $_directoryname = NULL;


    /**
     * Constructor sets reflection class as class property
     */
    public function __construct()
    {
        $this->_reflection = new \ReflectionClass($this);
    }

    /**
     * @return string $namespace
     */
    protected function getNamespace()
    {
        if (is_null($this->_namespace)) {
            $this->_namespace = $this->_reflection->getNamespaceName();
        }

        return $this->_namespace;
    }

    /**
     * @return string $filename
     */
    protected function getFilename()
    {
        if (is_null($this->_filename)) {
            $this->_filename = $this->_reflection->getFileName();
        }

        return $this->_filename;
    }

    /**
     * @return string $dir
     */
    protected function getDirectory()
    {
        if (is_null($this->_directoryname)) {
            $this->_directoryname = dirname($this->getFilename());
        }

        return $this->_directoryname;
    }

    /**
     * @return array|mixed $moduleConfig
     */
    public function getConfig()
    {
        $moduleConfig = include $this->getDirectory().'/config/module.config.php';

        $localConfig = $this->getDirectory().'/config/local.module.config.php';
        if (file_exists($localConfig)) {
            $localConfig = include $localConfig;
            $moduleConfig = array_replace_recursive($moduleConfig, $localConfig);
        }

        return $moduleConfig;
    }

    /**
     * @return array $autoloadeConfig
     */
    public function getAutoloaderConfig()
    {
        $autoloderConfig = array(
            'Zend\Loader\StandardAutoloader'=>array(
                'namespaces'=>array(
                    $this->getNamespace()=>$this->getDirectory().'/src/'.$this->getNamespace()
                )
            )
        );

        return $autoloderConfig;
    }

}

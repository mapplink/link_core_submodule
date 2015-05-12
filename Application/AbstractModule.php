<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use Zend\Console\Adapter\AdapterInterface;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\ModuleManager\Feature\ConsoleBannerProviderInterface;


abstract class AbstractModule
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

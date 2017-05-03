<?php
/**
 * @package Web\Widget
 * @author Matt Johnston
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Web\Widget;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Db\TableGateway\TableGateway;
use Magelink\Exception\MagelinkException;

/**
 * Represents a basic homepage widget
 *
 * @package Web\Widget
 */
abstract class AbstractWidget implements ServiceLocatorAwareInterface {

    /**
     * The loaded data for this widget
     * @var mixed
     */
    protected $_data = false;

    protected function getData(){
        if($this->_data === false){
            throw new MagelinkException('Attempted to getData before loading widget!');
        }
        return $this->_data;
    }

    /**
     * Loads the data for this widget (basically, initialization)
     */
    public function load($options=array()){
        if($this->_data === false){
            $this->_data = $this->_load($options);
        }
    }

    /**
     * Should be overridden by child classes to implement data loading.
     * @return mixed The loaded data
     */
    protected abstract function _load($options=array());

    /**
     * Render this widget
     */
    public function render($code){
        return '<div class="magelink-widget magelink-widget-'.$code.'">'.$this->_render().'</div>'.PHP_EOL;
    }

    /**
     * Should be overridden by child classes to output HTML
     * @return string The generated HTML
     */
    protected abstract function _render();

    /**
     * Return the database adapter to be used to communicate with Entity storage.
     * @return \Zend\Db\Adapter\Adapter
     */
    protected function getAdapter(){
        return $this->getServiceLocator()->get('zend_db');
    }

    /**
     * Cache of preloaded table gateways
     * @var TableGateway[]
     */
    protected $_tgCache = array();

    /**
     * Returns a new TableGateway instance for the requested table
     * @param string $table
     * @return \Zend\Db\TableGateway\TableGateway
     */
    protected function getTableGateway($table){
        if(isset($this->_tgCache[$table])){
            return $this->_tgCache[$table];
        }
        $this->_tgCache[$table] = new TableGateway($table, $this->getServiceLocator()->get('zend_db'));
        return $this->_tgCache[$table];
    }

    protected $_serviceLocator;

    /**
     * Set service locator
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->_serviceLocator = $serviceLocator;
    }

    /**
     * Get service locator
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->_serviceLocator;
    }

}
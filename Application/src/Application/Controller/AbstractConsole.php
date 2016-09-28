<?php
/**
 * Manages assorted maintenance tasks
 * @category Application
 * @package Application\Controller
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Application\Controller;

use Magelink\Exception\MagelinkException;
use Zend\Console\Request as ConsoleRequest;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorAwareInterface;


abstract class AbstractConsole extends AbstractActionController implements ServiceLocatorAwareInterface
{

    /** @var array $_tasks */
    protected $_tasks = array();

    /** @var \Zend\Db\TableGateway\TableGateway[]  Cache of preloaded table gateways */
    protected $_tgCache = array();


    /**
     * @throws MagelinkException
     */
    public function indexAction()
    {
        throw new MagelinkException('Invalid Console action');
    }

    /**
     * @throws MagelinkException
     */
    public function runAction()
    {
        if (extension_loaded('newrelic')) {
            newrelic_background_job(true);
        }
        $request = $this->getRequest();

        // Make sure that we are running in a console and the user has not tricked our
        // application into running this action from a public web server.
        if (!$request instanceof ConsoleRequest){
            throw new \RuntimeException('You can only use this action from a console!');
        }

        if(count($this->_tasks) == 0){
            throw new MagelinkException('Controller not set up properly!');
        }

        $this->getServiceLocator()->get('zend_db');

        $task = $request->getParam('task');
        $id = $request->getParam('id');

        if(!in_array($task, $this->_tasks)){
            throw new MagelinkException('Invalid task name ' . $task);
        }

        $this->getLog()->log(\Log\Service\LogService::LEVEL_INFO, 'console_init', 'Console init - task ' . $task . ' and ID ' . $id, array('controller'=>get_class($this), 'task'=>$task, 'id'=>$id));

        $func = $task . 'Task';
        $this->$func($id);

        die();
    }

    /**
     * @return \Log\Service\LogService
     */
    protected function getLog()
    {
        return $this->getServiceLocator()->get('logService');
    }

    /**
     * @return \Entity\Service\EntityService
     */
    protected function getEntityService()
    {
        return $this->getServiceLocator()->get('entityService');
    }

    /**
     * @return \Entity\Service\EntityConfigService
     */
    protected function getEntityConfigService()
    {
        return $this->getServiceLocator()->get('entityConfigService');
    }

    /**
     * Return the database adapter to be used to communicate with Entity storage.
     * @return \Zend\Db\Adapter\Adapter
     */
    protected function getAdapter()
    {
        return $this->getServiceLocator()->get('zend_db');
    }

    /**
     * Returns a new TableGateway instance for the requested table
     * @param string $table
     * @return \Zend\Db\TableGateway\TableGateway
     */
    protected function getTableGateway($table)
    {
        if(isset($this->_tgCache[$table])){
            return $this->_tgCache[$table];
        }
        $this->_tgCache[$table] = new \Zend\Db\TableGateway\TableGateway($table, $this->getServiceLocator()->get('zend_db'));
        return $this->_tgCache[$table];
    }

}

<?php
/**
 * Log
 *
 * @category Log
 * @package Log\Cron
 * @author <alex@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Log\Cron;

use Application\CronRunnable;
use Log\Service\LogService;
use Zend\Db\Sql\Where;
use Zend\Db\TableGateway\TableGateway;
use Zend\ServiceManager\ServiceLocatorAwareInterface;

class LogClear extends CronRunnable
{
    protected $_tableGateway;

    const INTERVAL_DAYS = 10;

    /**
     * Performs any scheduled actions.
     */
    protected function _cronRun()
    {
        $fromDate = date('Y-m-d H:i:s', strtotime('-' . $this->_getIntervalDays() . ' days'));
        $where = new Where();
        $where->lessThan('timestamp', $fromDate);
        $tableGateway = $this->getTableGateway('log_entry');
        $sql = $tableGateway->getSql();
        $sqlDelete = $sql->delete()->where($where);
        $deletedRows = $tableGateway->deleteWith($sqlDelete);
        // Retrieve sql string
        $sqlString = $sql->getSqlStringForSqlObject($sqlDelete);

        // Log cron
        $message = 'Deleted ' . $deletedRows . ' rows from log_entry table to clear entries older than '.$fromDate;
        $this->getServiceLocator()->get('logService')
            ->log(LogService::LEVEL_INFO,
                'cron_logclear',
                $message,
                array('query' => $sqlString)
            );

    }

    /**
     * Returns a new TableGateway instance for the requested table
     * @param string $table
     * @return \Zend\Db\TableGateway\TableGateway
     */
    protected function getTableGateway($table)
    {
        if (isset($this->_tableGateway)) {
            return $this->_tableGateway;
        }
        $this->_tableGateway = new TableGateway($table, $this->getServiceLocator()->get('zend_db'));

        return $this->_tableGateway;
    }

    /**
     * Return interval days using config value if set greater than constant
     * @return int
     */
    protected function _getIntervalDays()
    {
        $intDays = $this->_getIntervalDaysConfig();
        if (is_numeric($intDays) && $intDays > self::INTERVAL_DAYS) {
            return $intDays;
        }
        return self::INTERVAL_DAYS;
    }

    /**
     * Get HOPS logclear_time config value
     * @return int|null
     */
    protected function _getIntervalDaysConfig()
    {
        $nodeType = 'HOPS';
        $nodeEntities = $this->getServiceLocator()->get('nodeService')
            ->getActiveNodesByType($nodeType);

        if ($nodeEntities || count($nodeEntities)) {
            $nodeEntity = array_shift($nodeEntities);
            $node = new \HOPS\Node();
            if ($node instanceof ServiceLocatorAwareInterface) {
                $node->setServiceLocator($this->getServiceLocator());
            }
            $node->init($nodeEntity);

            $config = $this->getServiceLocator()->get('Config');
            if (isset($config['node_types']['HOPS']['config']['logclear_time'])) {
                return $node->getConfig('logclear_time');
            }
        }

        return false;
    }

}

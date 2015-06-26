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
    protected $tableGateway;

    const MIN_INTERVAL_DAYS = 10;

    const MAX_ROWS_DELETE = 200000;

    /**
     * Performs any scheduled actions.
     */
    protected function _cronRun()
    {
        // Build where condition
        $maxLogId = $this->getMaxLogId();
        $fromDate = date('Y-m-d H:i:s', strtotime('-'.$this->getIntervalDays().' days'));
        $where = new Where();
        $where->lessThan('timestamp', $fromDate);
        if (!is_null($maxLogId)) {
            $where->and->lessThan('log_id',$maxLogId);
        }

        $tableGateway = $this->getTableGateway('log_entry');
        $sql = $tableGateway->getSql();
        $sqlDelete = $sql->delete()->where($where);
        $deletedRows = $tableGateway->deleteWith($sqlDelete);
        // Retrieve sql string
        $sqlString = $sql->getSqlStringForSqlObject($sqlDelete);

        // Log cron
        $message = 'Deleted ' . $deletedRows . ' rows from log_entry table older than ' .
            $fromDate . ' with log_id less than ' . $maxLogId;
        $this->getServiceLocator()->get('logService')
            ->log(LogService::LEVEL_INFO,
                'cron_logclear',
                $message,
                array('query'=>$sqlString)
            );
    }

    /**
     * Returns a new TableGateway instance for the requested table
     * @param string $table
     * @return \Zend\Db\TableGateway\TableGateway
     */
    protected function getTableGateway($table)
    {
        if (isset($this->tableGateway)) {
            return $this->tableGateway;
        }
        $this->tableGateway = new TableGateway($table, $this->getServiceLocator()->get('zend_db'));

        return $this->tableGateway;
    }

    /**
     * Return interval days using config value if set greater than constant
     * @return int
     */
    protected function getIntervalDays()
    {
        $intDays = $this->getIntervalDaysConfig();
        if (is_numeric($intDays) && $intDays > self::MIN_INTERVAL_DAYS) {
            return $intDays;
        }
        return self::MIN_INTERVAL_DAYS;
    }

    /**
     * Get HOPS logclear_time config value
     * @return int|null
     */
    protected function getIntervalDaysConfig()
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

    /**
     * Return maximum log_id record to use for delete method
     * @return int | null
     */
    protected function getMaxLogId()
    {
        $tableGateway = $this->getTableGateway('log_entry');
        $sql = $tableGateway->getSql();

        $sqlSelect = $sql->select()->columns(array('log_id'=>'log_id'))->order('log_id ASC')->limit(1);
        $result = $tableGateway->selectWith($sqlSelect);

        $logId = null;
        foreach($result as $row){
            // Only 1 row returned
            $logId = $row['log_id'];
        }

        return ($logId) ? $logId + self::MAX_ROWS_DELETE : null;
    }

}

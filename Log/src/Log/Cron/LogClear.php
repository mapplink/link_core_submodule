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

class LogClear extends CronRunnable
{
    protected $_tableGateway;

    /**
     * Performs any scheduled actions.
     */
    protected function _cronRun()
    {
        $intDate = '14 days';
        $fromDate = date('Y-m-d H:i:s', strtotime('-'.$intDate));
        $where = new Where();
        $where->lessThan('timestamp', $fromDate);
        $this->getTableGateway('log_entry')->delete($where);

        // Log cron
        $message = 'Cleared log_entry table for all entries older than '.$intDate;
        $this->getServiceLocator()->get('logService')
            ->log(LogService::LEVEL_DEBUGEXTRA,
                'cron_logclear',
                $message,
                array('where' => $where)
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

}

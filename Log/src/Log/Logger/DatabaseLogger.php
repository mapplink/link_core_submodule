<?php
/**
 * @category Log
 * @package Log\Logger
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Log\Logger;

use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;


class DatabaseLogger extends AbstractLogger {

    /** @var TableGateway */
    protected $_tableGateway = false;

    /** @var array $_allowedLevels */
    protected $_allowedLevels = array(
        LogService::LEVEL_INFO,
        LogService::LEVEL_WARN,
        LogService::LEVEL_ERROR
    );

    /**
     * Initialize the logger instance and verify if it is able to log messages.
     * @param array $config
     * @return bool $success Whether this logger is able to log messages (i.e. whether all dependencies are fulfilled)
     */
    function init($config = array())
    {
        try{
            $this->_tableGateway = new TableGateway('log_entry', $this->getServiceLocator()->get('zend_db'));
            $success = TRUE;
        }catch (\Exception $exception) {
            $success = FALSE;
        }

        return $success;
    }

    /**
     * @param string $level
     * @param bool $extendedDatabaseEnabled
     * @return bool $isLogLevel
     */
    public function isLogLevel($level, $extendedDatabaseEnabled)
    {
        $isLogLevel = ($extendedDatabaseEnabled || parent::isLogLevel($level));
        return $isLogLevel;
    }

    /**
     * Provides a log message to the logger. The logger instance SHOULD output it immediately, but may queue it if necessary.
     * @param string $level
     * @param string $code
     * @param string $message
     * @param array $data
     * @param array $extraData
     * @param array $lastStackFrame
     */
    function printLog($level, $code, $message, array $data, array $extraData, array $lastStackFrame)
    {
        if (!isset($lastStackFrame['class'])) {
            $className = $lastStackFrame['file'] . ':' . $lastStackFrame['line'];
            $module = '{unknown}';
        }else{
            $className = $lastStackFrame['class'];
            if (strpos($className, '\\') !== FALSE) {
                $module = substr($className, 0, strpos($className, '\\'));
                $className = str_replace($module.'\\', '', $className);
            }else{
                $module = '{raw}';
            }
        }

        $newRow = array(
            'timestamp'=>date('Y-m-d H:i:s'),
            'level'=>$level,
            'code'=>$code,
            'module'=>$module,
            'class'=>$className,
            'message'=>$message,
            'data'=>json_encode($data),
            'user_id'=>(isset($extraData['user']) ? $extraData['user'] : null),
            'node_id'=>(isset($extraData['node']) ? $extraData['node'] : null),
            'entity_id'=>(isset($extraData['entity']) ? $extraData['entity'] : null),
            'router_filter_id'=>(isset($extraData['router_filter']) ? $extraData['router_filter'] : null),
        );

        try{
            $success = $this->_tableGateway->insert($newRow);
        }catch(\Exception $e){
            $success = FALSE;
        }

        if (!$success) {
            if (php_sapi_name() == 'cli') {
                echo 'ERROR saving Log Data' . PHP_EOL;
                print_r($newRow);
                echo PHP_EOL;
            }
            unset($newRow['user_id']);
            unset($newRow['node_id']);
            unset($newRow['entity_id']);
            unset($newRow['router_filter_id']);

            try{
                $success = $this->_tableGateway->insert($newRow);
            }catch(\Exception $e){
                $success = FALSE;
            }

            if (!$success) {
                if (php_sapi_name() == 'cli') {
                    echo 'DOUBLE ERROR saving Log Data' . PHP_EOL;
                    print_r($newRow);
                    echo PHP_EOL;
                    throw new MagelinkException('DOUBLE ERROR saving Log Data');
                }
            }
        }

    }

    /**
     * Output any queued messages (if relevant).
     */
    function flushLog() {}

}
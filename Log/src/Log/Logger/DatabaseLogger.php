<?php
/**
 * @package Log\Logger
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Log\Logger;

use Application\Service\ApplicationConfigService;
use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;


class DatabaseLogger extends AbstractLogger
{

    const DATA_MAX_LENGTH = 10240;

    /** @var TableGateway $_tableGateway */
    protected $_tableGateway = FALSE;
    /** @var bool $_enableExtendedDatabase */
    protected $_extendedDatabaseLoggingEnabled = FALSE;
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
    public function init(array $config = array())
    {
        try{
            $this->_tableGateway = new TableGateway('log_entry', $this->getServiceLocator()->get('zend_db'));
            $success = TRUE;
        }catch (\Exception $exception) {
            $success = FALSE;
        }

        /** @var ApplicationConfigService $applicationConfigService */
        $applicationConfigService = $this->getServiceLocator()->get('applicationConfigService');
        $this->_extendedDatabaseLoggingEnabled = $applicationConfigService->isExtendedDatabaseLoggingEnabled();

        return $success;
    }

    /**
     * @param string $level
     * @return bool $isLogLevel
     */
    public function isLogLevel($level)
    {
        $isLogLevel = ($this->_extendedDatabaseLoggingEnabled || parent::isLogLevel($level));
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

        if (strlen($message) > 254) {
            $message = substr($message, 0, 250).' ...';
        }

        $jsonData = json_encode($data);
        if (strlen($jsonData) > self::DATA_MAX_LENGTH) {
            $dataLength = mb_strlen($jsonData);
            $dataStringLength = 0;
            foreach ($data as &$dataRow) {
                if (is_numeric($dataRow)) {
                    $dataRow = (double) $dataRow;
                }elseif (is_string($dataRow)) {
                    $dataStringLength += json_encode($dataRow);
                }
            }

            $ratio = self::DATA_MAX_LENGTH / $dataLength * $dataStringLength / $dataLength;

            $shortenedData = array();
            $shortenedLength = 0;
            foreach ($data as $key=>$value) {
                if (is_string($value)) {
                    $newLength = $ratio * strlen($value);
                    $newValue = mb_substr($value, 0, floor($newLength * 0.9))
                        ."\r\n ... \r\n".mb_substr($value, floor($newLength * -0.1));
                }else{
                    $newValue = $value;
                }

                $shortenedLength += mb_strlen(json_encode($newValue));
                if ($shortenedLength < self::DATA_MAX_LENGTH) {
                    $shortenedData[$key] = $newValue;
                }
            }

            $jsonData = json_encode($shortenedData);
        }

        if (strlen($jsonData) > self::DATA_MAX_LENGTH) {
            $jsonData = substr($jsonData, 0, self::DATA_MAX_LENGTH * 0.9)
                .' ... '.substr($jsonData, self::DATA_MAX_LENGTH * 0.1 - 5);
        }

        $newRow = array(
            'timestamp'=>date('Y-m-d H:i:s'),
            'level'=>$level,
            'code'=>$code,
            'module'=>$module,
            'class'=>$className,
            'message'=>$message,
            'data'=>$jsonData,
            'user_id'=>(isset($extraData['user']) ? $extraData['user'] : null),
            'node_id'=>(isset($extraData['node']) ? $extraData['node'] : null),
            'entity_id'=>(isset($extraData['entity']) ? $extraData['entity'] : null),
            'router_filter_id'=>(isset($extraData['router_filter']) ? $extraData['router_filter'] : null),
        );

        try{
            $success = $this->_tableGateway->insert($newRow);
        }catch(\Exception $exception) {
            $success = FALSE;
        }

        if (!$success) {
            if (php_sapi_name() == 'cli') {
                $newRow['exception'] = (isset($exception) ? $exception->getMessage() : '<no exception>');
                echo 'ERROR saving log data on the database', PHP_EOL;
                print_r($newRow);
                echo PHP_EOL;
            }

            unset($newRow['user_id']);
            unset($newRow['node_id']);
            unset($newRow['entity_id']);
            unset($newRow['router_filter_id']);
            unset($newRow['exception']);

            try{
                $success = $this->_tableGateway->insert($newRow);
                $errorMessage = 'No row inserted.';
            }catch(\Exception $repetitiveException) {
                $success = FALSE;
                $errorMessage = $repetitiveException->getMessage();
            }

            if (!$success && php_sapi_name() == 'cli') {
                $errorMessage = '[log_db_nosave] Insert problem: '.$errorMessage;
                echo $errorMessage, PHP_EOL;
                print_r($newRow).PHP_EOL;

                $newRow['data'] = strlen($newRow['data']).' characters';
                $errorMessage .= '; NewRow: '.json_encode($newRow);
                throw new MagelinkException($errorMessage);
            }
        }
    }

    /**
     * Output any queued messages (if relevant).
     */
    function flushLog() {}

}

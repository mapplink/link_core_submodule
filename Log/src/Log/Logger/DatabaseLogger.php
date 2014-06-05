<?php

namespace Log\Logger;

use Magelink\Exception\MagelinkException;
use \Zend\Db\TableGateway\TableGateway;
use \Zend\Db\Adapter\Adapter;

class DatabaseLogger extends AbstractLogger {

    /**
     * @var TableGateway
     */
    protected $_tableGateway = false;

    /**
     * Initialize the logger instance and verify if it is able to log messages.
     *
     * @param array $config
     * @return boolean Whether this logger is able to log messages (i.e. whether all dependencies are fulfilled)
     */
    function init($config=array()) {
        try{
            $this->_tableGateway = new TableGateway('log_entry', $this->getServiceLocator()->get('zend_db'));
        }catch(\Exception $e){
            return false;
        }
        return true;
    }

    /**
     * Provides a log message to the logger. The logger instance SHOULD output it immediately, but may queue it if necessary.
     *
     * @param $level
     * @param $code
     * @param $message
     * @param $data
     * @param $extraData
     * @param $lastStackFrame
     */
    function printLog($level, $code, $message, $data, $extraData, $lastStackFrame) {

        if(!isset($lastStackFrame['class'])){
            $className = $lastStackFrame['file'] . ':' . $lastStackFrame['line'];
            $module = '{unknown}';
        }else{
            $className = $lastStackFrame['class'];
            if(strpos($className, '\\') !== false){
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

        $success = false;
        try{
            $success = $this->_tableGateway->insert($newRow);
        }catch(\Exception $e){
            $success = false;
        }

        if(!$success){
            if(php_sapi_name() == 'cli'){
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
                $success = false;
            }
            if(!$success){
                // Well, we're not having any luck today. Hope that other methods can take over!
                if(php_sapi_name() == 'cli'){
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
    function flushLog() {
        // Unused
    }

}
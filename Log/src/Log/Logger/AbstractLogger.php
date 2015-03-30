<?php
/*
 * Class AbstractLogger represents a log output method.
 * @category Log
 * @package Log\Logger
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 *
 * This software is subject to our terms of trade and any applicable licensing agreements.
 */

namespace Log\Logger;

use Log\Service\LogService;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


abstract class AbstractLogger implements ServiceLocatorAwareInterface
{

    /** @var ServiceLocatorInterface $serviceLocator */
    protected $_serviceLocator;

    /** @var array $_allowedLevels  Contains all allowed levels of the logger */
    protected $_allowedLevels = array(
        LogService::LEVEL_DEBUGINTERNAL,
        LogService::LEVEL_DEBUGEXTRA,
        LogService::LEVEL_DEBUG,
        LogService::LEVEL_INFO,
        LogService::LEVEL_WARN,
        LogService::LEVEL_ERROR
    );

    /**
     * Set service locator
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->_serviceLocator = $serviceLocator;
    }

    /**
     * Get service locator
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->_serviceLocator;
    }

    /**
     * Initialize the logger instance and verify if it is able to log messages.
     * @param array $config
     * @return boolean Whether this logger is able to log messages (i.e. whether all dependencies are fulfilled)
     */
    abstract function init($config = array());

    /**
     * @param string $level
     * @return bool $isLogLevel
     */
    public function isLogLevel($level)
    {
        if (in_array($level, $this->_allowedLevels)) {
            $isLogLevel = TRUE;
        }else{
            $isLogLevel = FALSE;
        }

        return $isLogLevel;
    }

    /**
     * Provides a log message to the logger.
     * The logger instance SHOULD output it immediately but may queue it if necessary.
     * @param string $level
     * @param string $code
     * @param string $message
     * @param array $data
     * @param array $extraData
     * @param array $lastStackFrame
     */
    abstract function printLog($level, $code, $message, array $data, array $extraData, array $lastStackFrame);

    /**
     * @param mixed $input
     * @param bool $hideArray
     * @return string
     */
    protected function convertDataHuman($input, $hideArray = FALSE)
    {
        if (is_scalar($input)) {
            $convertedData = (string) $input;
        }elseif (is_object($input)) {
            $convertedData = 'Object<'.get_class($input).'>';
        }elseif (is_array($input)) {
            $maxValues = 7;
            $count = count($input);
            if ($count > $maxValues) {
                $input = array_slice($input, 0, $maxValues);
                $input[] = '...';
            }
            $convertedData = 'Array['.$count.']';

            if (!$hideArray) {
                $convertedData .= '(';
                $contentsSimple = array();
                foreach ($input as $key=>$value) {
                    if (is_int($key)) {
                        $contentsSimple[] = $this->convertDataHuman($value, TRUE);
                    }else{
                        $contentsSimple[] = $key.': '.$this->convertDataHuman($value, TRUE);
                    }
                }
                $convertedData .= implode(', ', $contentsSimple);
                $convertedData .= ')';
            }
        }elseif(is_null($input)) {
            $convertedData = 'NULL';
        }else{
            $convertedData = 'INV<' . gettype($input) . '>';
        }

        return $convertedData;
    }

    /**
     * Output any queued messages (if relevant).
     */
    abstract function flushLog();

}
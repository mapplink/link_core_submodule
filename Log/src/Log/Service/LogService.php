<?php
/*
 * Provides log services for Magelink modules
 * @category Log
 * @package Log\Service
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 *
 * This software is subject to our terms of trade and any applicable licensing agreements.
 */

namespace Log\Service;

use Application\Service\ApplicationConfigService;
use Log\Logger\AbstractLogger;
use Magelink\Exception\MagelinkException;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


class LogService implements ServiceLocatorAwareInterface
{
    // Critical errors or data inconsistencies
    const LEVEL_ERROR = 'error';
    // Potential issues or unusual circumstances
    const LEVEL_WARN = 'warn';
    // The minimum level useful to users, should always be logged
    const LEVEL_INFO = 'info';
    // Used for troubleshooting issues with data flow, etc. (useful for finding issues)
    const LEVEL_DEBUG = 'debug';
    // Used for extreme debugging messages (more details for of finding issues)
    const LEVEL_DEBUGEXTRA = 'debugx';
    // Used for extreme debugging messages (needed for internal framework development)
    const LEVEL_DEBUGINTERNAL = 'dbgint';

    /** @var AbstractLogger[] */
    protected $logger = array();

    /** @var bool $enableDebug */
    protected $enableDebug = FALSE;
    /** @var bool $enableDebugExtra */
    protected $enableDebugExtra = FALSE;
    /** @var bool $enableDebugInternal */
    protected $enableDebugInternal = FALSE;

    /** @var AbstractLogger[] */
    protected $levelsToBeLogged = array();

    /** @var ApplicationConfigService $this->applicationConfigService */
    protected $applicationConfigService;


    /** @var ServiceLocatorInterface $serviceLocator */
    protected $_serviceLocator;


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
     * Init class properties applicationConfigService, enableDebug, enableDebugExtra and enableDebugInternal
     */
    public function init()
    {
        if (!$this->applicationConfigService) {
            $this->applicationConfigService = $this->getServiceLocator()->get('applicationConfigService');

            $this->enableDebug = $this->applicationConfigService->isDebugLevelEnabled();
            $this->enableDebugExtra = $this->applicationConfigService->isDebugextraLevelEnabled();
            $this->enableDebugInternal = $this->applicationConfigService->isDebuginternalLevelEnabled();
        }
    }

    /**
     * @throws \Magelink\Exception\MagelinkException
     */
    protected function initLoggers()
    {
        if (!$this->logger) {
            $logger = $this->applicationConfigService->getConfigLoggerData();
            if (!is_array($logger) || !count($logger)) {
                $logger = array();
                throw new MagelinkException('No logger class specified!');
            }

            foreach ($logger as $name=>$loggerInfo) {
                if (!is_null($loggerInfo)) {
                    try{
                        $loggerObject = new $loggerInfo['class']();
                        if ($loggerObject instanceof ServiceLocatorAwareInterface) {
                            $loggerObject->setServiceLocator($this->getServiceLocator());
                        }
                    }catch (\Exception $exception ) {
                        throw new MagelinkException('Invalid logger information specified: '.serialize($loggerInfo));
                    }

                    if ($loggerObject instanceof AbstractLogger) {
                        if ($loggerObject->init($loggerInfo)) {
                            $this->logger[$name] = $loggerObject;
                        }
                    }else{
                        throw new MagelinkException('Invalid logger class specified: '.$loggerInfo['class']);
                    }
                }
            }
        }
    }

    /**
     * @param string $level
     * @return bool $this->levelsToBeLogged[$level]
     */
    protected function isLevelToBeLogged($level)
    {
        if (!array_key_exists($level, $this->levelsToBeLogged)) {
            $this->levelsToBeLogged[$level] = ($level != self::LEVEL_DEBUG || $this->enableDebug)
                && ($level != self::LEVEL_DEBUGEXTRA || $this->enableDebugExtra)
                && ($level != self::LEVEL_DEBUGINTERNAL || $this->enableDebugInternal);
        }

        return $this->levelsToBeLogged[$level];
    }

    /**
     * Enters a new log message, routing it to appropriate destinations (i.e. DB, files, email, etc).
     *   It will examine the stack to automatically populate the calling module and calling class.
     * @param string $logLevel
     * @param string $logCode
     * @param string $logMessage
     * @param array $logData
     * @param array $options An array of auxiliary data - supports the following keys:
     *     user: User ID to attach to the entry
     *     node: Node ID to attach to the entry
     *     entity: Entity ID to attach to the entry
     *     filter: Router Filter ID to attach to the entry
     *     user: User ID to attach to the entry
     *     exception: An exception class, that will be used to find the module & calling class
     *   All other keys will be silently ignored (to allow for backwards-compatibility)
     * @param bool $notifyClient
     * @return int ID of the new log entry
     */
    public function log($logLevel, $logCode, $logMessage, array $logData, array $options = array(),
        $notifyClient = FALSE)
    {
        $this->init();

        if ($this->isLevelToBeLogged($logLevel)) {
            $this->initLoggers();
            if (!isset($options['user']) && php_sapi_name() != 'cli') {
                /** @var \Zend\Authentication\AuthenticationService $authService */
                $authService = $this->getServiceLocator()->get('zfcuser_auth_service');

                if ($authService && $authService->getIdentity()) {
                    $options['user'] = $authService->getIdentity()->getId();
                }
            }

            $topTrace = NULL;
            if (isset($options['exception']) && $options['exception'] instanceof \Exception) {
                /** @var \Exception $$exception */
                $exception = $options['exception'];
                $backtraces = $exception->getTrace();
                $topTrace = array(
                    'file'=>$exception->getFile(),
                    'line'=>$exception->getLine(),
                );
            }else{
                $backtraces = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
                array_shift($backtraces);

                $topTrace = $backtraces[0];
                if (!array_key_exists('line', $topTrace)) {
                    $topTrace['line'] = 'void';
                }
            }

            // Parse backtrace for node/entity if not specified
            foreach ($backtraces as $backtrace) {
                if (isset($backtrace['object'])) {
                    $parsedBacktraceObject = $this->parseObject($backtrace['object']);
                    $options = array_merge($parsedBacktraceObject, $options);
                }
                if (isset($backtrace['args'])) {
                    foreach ($backtrace['args'] as $key=>$value) {
                        if (is_object($value)) {
                            $parsedValue = $this->parseObject($value);
                            $options = array_merge($parsedValue, $options);
                        }
                    }
                }
            }

            $optionTypeMap = array(
                'node'=>array('node', 'nodeid', 'node_id'),
                'entity'=>array('entity', 'entityid', 'entity_id')
            );

            foreach ($logData as $code=>$value) {
                $code = strtolower($code);
                foreach ($optionTypeMap as $type=>$codesArray) {
                    if (in_array($code, $codesArray)) {
                        if (is_object($value)) {
                            $options = array_merge($this->parseObject($value), $options);
                            break;
                        }else{
                            if (is_numeric($value) && !isset($options[$type])) {
                                $options[$type] = intval($value);
                                break;
                            }
                        }
                    }
                }
            }

            // @todo: Check if this is actually necessary : Enforce node ID
            foreach ($optionTypeMap as $type=>$codesArray) {
                if (isset($options[$type]) && is_object($options[$type])) {
                    $parsedObject = $this->parseObject($options[$type]);
                    if (isset($parsedObject[$type])) {
                        $options[$type] = $parsedObject[$type];
                    }else{
                        $options[$type] = 'Invalid '.$type.' option '.get_class($options[$type]);
                        unset($options[$type]);
                    }
                }
            }

            // @todo: Check if this is actually necessary : Enforce exception type
            if (isset($options['exception']) && !($options['exception'] instanceof \Exception)) {
                $options['note'] = 'Invalid exception option '.get_class($options['exception']);
                unset($options['exception']);
            }

            foreach ($this->logger as $name=>$logger) {
                $logger->setNotifyClient($notifyClient);
                if ($logger->isLogLevel($logLevel)) {
                    $logger->printLog($logLevel, $logCode, $logMessage, $logData, $options, $topTrace);
                }
            }
        }
    }

    /**
     * Parse a provided object to see if we can extract a node, entity, or exception from it
     * @todo : Parse users
     * @param $obj
     * @return array
     */
    protected function parseObject($object)
    {
        if ($object instanceof \Node\AbstractNode) {
            $parsedObject = array('node'=>$object->getNodeId());

        }elseif ($object instanceof \Node\Entity\Node) {
            $parsedObject = array('node'=>$object->getId());

        }elseif (method_exists($object, 'getNodeId') && is_callable(array($object, 'getNodeId'))) {
            $parsedObject = array('node'=>$object->getNodeId());

        }elseif ($object instanceof \Entity\Entity) {
            $parsedObject = array('entity'=>$object->getId());

        }elseif ($object instanceof \Entity\Update) {
            $parsedObject = array('entity'=>$object->getEntity()->getId());

        }elseif ($object instanceof \Entity\Action) {
            $parsedObject = array('entity'=>$object->getEntity()->getId());

        }elseif ($object instanceof \Entity\Entity\EntityAction) {
            $parsedObject = array('entity'=>$object->getEntityId());

        }elseif(method_exists($object, 'getEntityId') && is_callable(array($object, 'getEntityId'))) {
            $parsedObject = array('entity'=>$object->getEntityId());

        }elseif ($object instanceof \Exception) {
            $parsedObject = array('exception'=>$object);

        }else{
            $parsedObject = array();
        }

        return $parsedObject;
    }

    /**
     * @todo : Generates a human-readable version of the log message identified by the given ID.
     * @param int $logId
     */
    public function generateHuman($logId) {}

    /**
     * @todo : Returns whether or not the provided error code has a human readable version
     * @param string $code
     */
    public function hasHuman($code){}

}

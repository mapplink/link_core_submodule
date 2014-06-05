<?php

namespace Log\Service;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class LogService implements ServiceLocatorAwareInterface {

    /** Used for extreme debugging messages that should not be needed for anything other than internal framework development */
    const LEVEL_DEBUGEXTRA = 'debugex';
    /** Used for troubleshooting issues with data flow etc - not useful for users but can be important for finding issues */
    const LEVEL_DEBUG = 'debug';
    /** The minimum level useful to users, should always be logged */
    const LEVEL_INFO = 'info';
    /** Potential issues or unusual circumstances */
    const LEVEL_WARN = 'warn';
    /** Critical errors or data inconsistencies */
    const LEVEL_ERROR = 'error';

    protected $_enableDebugExtra = true;
    protected $_enableDebug = true;

    /**
     * @var \Log\Logger\AbstractLogger[]
     */
    protected $_loggers = false;

    protected function initLoggers(){
        $config = $this->getServiceLocator()->get('Config');
        if(!isset($config['system_log'])){
            $config = array();
        }else{
            $config = $config['system_log'];
        }
        if (!isset($config['enable_debug_extra']) || !$config['enable_debug_extra']) {
            $this->_enableDebugExtra = false;
        }
        if (!isset($config['enable_debug']) || !$config['enable_debug']) {
            $this->_enableDebugExtra = false;
        }


        // TODO read from config
        $logConfig = array(
            'stdout'=>array(
                'class'=>'\Log\Logger\StdoutLogger',
            ),
            'database'=>array(
                'class'=>'\Log\Logger\DatabaseLogger',
            ),
            'file'=>array(
                'class'=>'\Log\Logger\FileLogger',
            ),
            'email'=>array(
                'class'=>'\Log\Logger\EmailLogger',
            ),
        );

        $this->_loggers = array();

        foreach($logConfig as $name=>$logger){
            $obj = new $logger['class']();
            if($obj instanceof ServiceLocatorAwareInterface){
                $obj->setServiceLocator($this->getServiceLocator());
            }
            if($obj instanceof \Log\Logger\AbstractLogger){
                if($obj->init($logger)){
                    $this->_loggers[$name] = $obj;
                }
            }else{
                throw new \Magelink\Exception\MagelinkException('Invalid logger class specified - ' . $logger['class'] . '!');
            }
        }
    }

    /**
     * Enters a new log message, routing it to appropriate destinations (i.e. DB, files, email, etc).
     * It will examine the stack to automatically populate the calling module and calling class.
     *
     * @param string $level
     * @param string $code
     * @param string $message
     * @param array $data
     * @param array $options An array of auxiliary data - supports the following keys:user: User ID to attach to the entry
     * * node: Node ID to attach to the entry
     * * entity: Entity ID to attach to the entry
     * * filter: Router Filter ID to attach to the entry
     * * user: User ID to attach to the entry
     * * exception: An exception class, that will be used to find the module & calling class (instead of where this function is called from)
     * * All other keys will be silently ignored (to allow for backwards-compatibility if logging is enhanced and we backport modules to older versions of Magelink)
     * * Where these are not specified we will try and infer useful values from the stack
     * @return int ID of the new log entry
     */
    public function log($level, $code, $message, $data, $options=array()){
        if($this->_loggers == false){
            $this->initLoggers();
        }

        if($level == self::LEVEL_DEBUGEXTRA && !$this->_enableDebugExtra){
            return;
        }
        if($level == self::LEVEL_DEBUG && !$this->_enableDebug){
            return;
        }


        if(!isset($options['user']) && php_sapi_name() != 'cli'){
            /** @var \Zend\Authentication\AuthenticationService $authService */
            $authService = $this->getServiceLocator()->get('zfcuser_auth_service');

            if($authService && $authService->getIdentity()) {
                $options['user'] = $authService->getIdentity()->getId();
            }
        }

        $topTrace = null;
        if(isset($options['exception']) && $options['exception'] instanceof \Exception){
            /** @var \Exception $ex */
            $ex = $options['exception'];
            $backtrace = $ex->getTrace();
            $topTrace = array(
                'file'=>$ex->getFile(),
                'line'=>$ex->getLine(),
            );
        }else{
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
            array_shift($backtrace);
            $topTrace = $backtrace[0];
        }

        // Parse backtrace for node/entity if not specified
        foreach($backtrace as $bt){
            if(isset($bt['object'])){
                $arr = $this->parseObj($bt['object']);
                $options = array_merge($arr, $options);
            }
            if(isset($bt['args'])){
                foreach($bt['args'] as $k=>$v){
                    if(is_object($v)){
                        $arr = $this->parseObj($v);
                        $options = array_merge($arr, $options);
                    }
                }
            }
        }

        // Find node/entity in data array
        foreach($data as $k=>$v){
            $k = strtolower($k);
            if($k == 'node' || $k == 'node_id' || $k == 'nodeid'){
                if(is_object($v)){
                    $options = array_merge($this->parseObj($v), $options);
                }else if(is_numeric($v) && !isset($options['node'])){
                    $options['node'] = intval($v);
                }
            }
            if($k == 'entity' || $k == 'entity' || $k == 'entity'){
                if(is_object($v)){
                    $options = array_merge($this->parseObj($v), $options);
                }else if(is_numeric($v) && !isset($options['entity'])){
                    $options['entity'] = intval($v);
                }
            }
        }

        // Enforce node ID
        if(isset($options['node']) && is_object($options['node'])){
            $arr = $this->parseObj($options['node']);
            if(isset($arr['node'])){
                $options['node'] = $arr['node'];
            }else{
                $options['note'] = 'Invalid node option ' . get_class($options['node']);
                unset($options['node']);
            }
        }
        if(isset($options['node']) && $options['node'] === 0){
            unset($options['node']);
        }

        // Enforce entity ID
        if(isset($options['entity']) && is_object($options['entity'])){
            $arr = $this->parseObj($options['entity']);
            if(isset($arr['entity'])){
                $options['entity'] = $arr['entity'];
            }else{
                $options['note'] = 'Invalid entity option ' . get_class($options['entity']);
                unset($options['entity']);
            }
        }

        // Enforce exception type
        if(isset($options['exception']) && !($options['exception'] instanceof \Exception)){
            $options['note'] = 'Invalid exception option ' . get_class($options['exception']);
            unset($options['exception']);
        }

        // Output log message
        foreach($this->_loggers as $name=>$logger){
            $logger->printLog($level, $code, $message, $data, $options, $topTrace);
        }
    }

    /**
     * Parse a provided object to see if we can extract a node, entity, or exception from it
     * @todo Parse users
     * @param $obj
     * @return array
     */
    protected function parseObj($obj){
        if($obj instanceof \Node\AbstractNode){
            return array('node'=>$obj->getNodeId());
        }
        if($obj instanceof \Node\Entity\Node){
            return array('node'=>$obj->getId());
        }
        if($obj instanceof \Entity\Entity){
            return array('entity'=>$obj->getId());
        }
        if($obj instanceof \Entity\Update){
            return array('entity'=>$obj->getEntity()->getId());
        }
        if($obj instanceof \Entity\Action){
            return array('entity'=>$obj->getEntity()->getId());
        }
        if($obj instanceof \Entity\Entity\EntityAction){
            return array('entity'=>$obj->getEntityId());
        }
        if($obj instanceof \Exception){
            return array('exception'=>$obj);
        }
        if(method_exists($obj, 'getNodeId') && is_callable(array($obj, 'getNodeId'))){
            return array('node'=>$obj->getNodeId());
        }
        if(method_exists($obj, 'getEntityId') && is_callable(array($obj, 'getEntityId'))){
            return array('entity'=>$obj->getEntityId());
        }
        return array();
    }

    /**
     * Generates a human-readable version of the log message identified by the given ID.
     * @param int $log_id
     */
    public function generateHuman($log_id){

    }

    /**
     * Returns whether or not the provided error code has a human readable version
     * @param string $code
     */
    public function hasHuman($code){

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
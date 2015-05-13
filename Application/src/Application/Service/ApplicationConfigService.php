<?php
/**
 * @category Application
 * @package Application\Service
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2015 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Application\Service;

use Application\CronRunnable;
use Log\Service\LogService;
use Magelink\Exception\SyncException;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


class ApplicationConfigService implements ServiceLocatorAwareInterface
{

    /** @var array|NULL $_config */
    protected $_config = NULL;

    /** @var array $cronjobs */
    protected $cronjobs = array();

    /** @var ServiceLocatorInterface */
    protected $_serviceLocator;


    /**
     * Get service locator
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->_serviceLocator;
    }

    /**
     * Set service locator
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator( ServiceLocatorInterface $serviceLocator )
    {
        $this->_serviceLocator = $serviceLocator;
    }

    /**
     * @param array $array
     * @param string $key
     * @return array $subArray
     */
    protected function getArrayKeyData(array $array, $key, $default = array())
    {
        if ($key && array_key_exists($key, $array)) {
            $subarray = $array[$key];
        }else{
            $subarray = $default;
        }

        return $subarray;
    }

    /**
     * @return array $config
     */
    protected function getConfigData($code = NULL)
    {
        if (!$this->_config) {
            $this->_config = $this->getServiceLocator()->get('Config');
        }

        if ($code && is_array($this->_config)) {
            $config = $this->getArrayKeyData($this->_config, $code);
        }elseif (!$code) {
            $config = $this->_config;
        }else{
            $config = array();
        }

        return $config;
    }

    /**
     * @return array $configCronData
     */
    protected function getConfigCronData()
    {
        return $this->getConfigData('magelink_cron');
    }

    /**
     * @return array $configSystemLogData
     */
    protected function getConfigSystemLogData()
    {
        return $this->getConfigData('system_log');
    }

    /**
     * @return array $configCronData
     */
    protected function getConfigClientData()
    {
        return $this->getArrayKeyData($this->getConfigSystemLogData(), 'client_notification');
    }

    /**
     * @return string $clientEmail
     */
    public function getClientEmail()
    {
        return $this->getArrayKeyData($this->getConfigClientData(), 'email', '');
    }


    protected function getClientHour($code, $default)
    {
        $hour = $this->getArrayKeyData($this->getConfigClientData(), $code, $default);
        $hour = ($hour < 0 || $hour > 24) ? $default : $hour;

        return $hour;
    }

    /**
     * @return int $clientNotificationStarthour
     */
    public function getClientStarthour()
    {
        return $this->getClientHour('starthour', 0);
    }

    /**
     * @return int $clientNotificationEndhour
     */
    public function getClientEndhour()
    {
        return $this->getClientHour('endhour', 24);
    }

    /**
     * @return bool $enableDebug
     */
    public function isDebugLevelEnabled()
    {
        return $this->getArrayKeyData($this->getConfigSystemLogData(), 'enable_debug', FALSE);
    }

    /**
     * @return bool $enableDebugextra
     */
    public function isDebugextraLevelEnabled()
    {
        return $this->getArrayKeyData($this->getConfigSystemLogData(), 'enable_debug_extra', FALSE);
    }

    /**
     * @return bool $enableDebuginternal
     */
    public function isDebuginternalLevelEnabled()
    {
        return $this->getArrayKeyData($this->getConfigSystemLogData(), 'enable_debug_internal', FALSE);
    }

    /**
     * @return bool $enableExtendedDatabase
     */
    public function isExtendedDatabaseLoggingEnabled()
    {
        return $this->getArrayKeyData($this->getConfigSystemLogData(), 'enable_extended_database', FALSE);
    }

    /**
     * @return bool $hasCronJobs
     */
    public function isCronjob()
    {
        $configCron = $this->getConfigCronData();
        return is_array($configCron) && count($configCron);
    }

    /**
     * @return array $cronjobs
     */
    public function getCronjobs()
    {
        $cronjobs = array();

        if ($this->isCronjob()) {
            if (!$this->cronjobs) {
                $magelinkCron = $this->getConfigCronData();
                foreach ($magelinkCron as $name=>$cronjobData) {
                    $class = __CLASS__;
                    extract($cronjobData, EXTR_IF_EXISTS);

                    try{
                        $cronjob = new $class($name, $cronjobData);
                    }catch( SyncException $syncException ){
                        $this->getServiceLocator()->get('logService')->log(
                            LogService::LEVEL_ERROR,
                            'cron_construct',
                            $syncException->getMessage(),
                            array('name'=>$name),
                            array('name'=>$name, 'data'=>$cronjobData)
                        );
                    }

                    if ($cronjob instanceof CronRunnable) {
                        $cronjob->setServiceLocator($this->getServiceLocator());
                        $this->cronjobs[$name] = $cronjob;

                        $logLevel = LogService::LEVEL_DEBUGEXTRA;
                        $logCode = 'cron_add';
                        $logMessage = 'Cron '.$name.' added the cronjobs array';
                    }else {
                        $logLevel = LogService::LEVEL_ERROR;
                        $logCode = 'cron_add_fail';
                        $logMessage = 'Cron '.$name.' does not extend CronRunnable: '.$class;
                    }

                    $this->getServiceLocator()->get('logService')
                        ->log($logLevel, $logCode, $logMessage, array('data'=>$cronjobData), array('cronjob'=>$cronjob));
                }
            }
        }

        return $this->cronjobs;
    }

    /**
     * @param string $name
     * @return CronRunnable|NULL
     */
    public function getCronjob($name)
    {
        $cronjobs = $this->getCronjobs();
        if (isset($cronjobs[$name])) {
            $cronjob = $cronjobs[$name];
            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_DEBUGINTERNAL, 'cron_found', 'Found cron '.$name, array('name'=>$name));
        }else{
            $cronjob = NULL;
            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_ERROR, 'cron_notfound', 'Could not find cron '.$name, array('name'=>$name));
        }

        return $cronjob;
    }

}
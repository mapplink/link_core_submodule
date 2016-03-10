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
use Magelink\Exception\MagelinkException;
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
     * @param mixed $default
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
     * @return array $configSystemCronData
     */
    protected function getConfigSystemCronData()
    {
        return $this->getConfigData('system_cron');
    }

    /**
     * @return string $cronLockDirectory
     */
    public function getConfigCronLockDirectory()
    {
        $systemCronData = $this->getConfigData('system_cron');
        return $systemCronData['lock_directory'];
    }

    /**
     * @return array $firstNotification
     */
    protected function getConfigFirstNotifications()
    {
        $systemCronData = $this->getConfigData('system_cron');
        return $systemCronData['first_notification'];
    }

    /**
     * @return int $firstNotificationTolerance
     */
    protected function getConfigFirstNotificationTolerance()
    {
        $firstNotifications = $this->getConfigFirstNotifications();
        return min(max($firstNotifications['tolerance'], 0.01), 0.99);
    }

    /**
     * @return array $firstAdminNotification
     */
    public function getConfigFirstAdminNotification()
    {
        $firstNotifications = $this->getConfigFirstNotifications();
        $firstNotification = max($firstNotifications['admin'], 1)  - $this->getConfigFirstNotificationTolerance();

        return $firstNotification;
    }

    /**
     * @return int|FALSE|NULL $firstClientNotification
     */
    public function getConfigFirstClientNotification()
    {
        $firstNotifications = $this->getConfigFirstNotifications();
        if (isset($firstNotifications['client']) && $firstNotifications['client'] !== FALSE) {
            $firstNotification = max($firstNotifications['client'], 1) - $this->getConfigFirstNotificationTolerance();
        }else{
            $firstNotification = NULL;
        }

        return $firstNotification;
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
    public function getConfigLoggerData()
    {
        return $this->getArrayKeyData($this->getConfigSystemLogData(), 'logger');
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
     * @return Cronrunnable[] $cronjobs
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
                        $cronjob = new $class($name);
                    }catch( SyncException $syncException ){
                        $this->getServiceLocator()->get('logService')->log(
                            LogService::LEVEL_ERROR,
                            'crn_constrct_err',
                            $syncException->getMessage(),
                            array('name'=>$name),
                            array('name'=>$name, 'data'=>$cronjobData)
                        );
                    }

                    if ($cronjob instanceof CronRunnable) {
                        $cronjob->setServiceLocator($this->getServiceLocator());
                        $cronjob->init($cronjobData);
                        $this->cronjobs[$name] = $cronjob;

                        $logLevel = LogService::LEVEL_DEBUGEXTRA;
                        $logCode = 'crn_add';
                        $logMessage = 'Cron '.$name.' added the cronjobs array';
                    }else {
                        $logLevel = LogService::LEVEL_ERROR;
                        $logCode = 'crn_add_fail';
                        $logMessage = 'Cron '.$name.' does not extend CronRunnable: '.$class;
                    }

                    $this->getServiceLocator()->get('logService')
                        ->log($logLevel, $logCode, $logMessage, array('data'=>$cronjobData), array('cron job'=>$cronjob));
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
                ->log(LogService::LEVEL_DEBUGINTERNAL, 'crn_get', 'Found cron '.$name, array('name'=>$name));
        }else{
            $cronjob = NULL;
            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_ERROR, 'crn_get_fail', 'Could not find cron '.$name, array('name'=>$name));
        }

        return $cronjob;
    }

}

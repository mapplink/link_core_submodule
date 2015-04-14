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
     * @return array|null $this->_config
     */
    protected function getConfigData()
    {
        if (!$this->_config) {
            $this->_config = $this->getServiceLocator()->get('Config');
        }

        return $this->_config;
    }

    /**
     * @return bool $hasCronJobs
     */
    public function isCronjob()
    {
        $config = $this->getConfigData();
        return isset($config['magelink_cron']) && is_array($config['magelink_cron']) && count($config['magelink_cron']);
    }

    /**
     * @return array $cronjobs
     */
    public function getCronjobs()
    {
        $cronjobs = array();

        if ($this->isCronjob()) {
            if (!$this->cronjobs) {
                $magelinkCron = $this->_config['magelink_cron'];
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
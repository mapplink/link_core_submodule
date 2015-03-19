<?php
/**
 * @category Application
 * @package Application\Controller
 * @author Andreas Gerhards <andreas@lero9.com>
 * @copyright Copyright (c) 2015 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Application\Controller;

use Log\Service\LogService;
use Zend\Mvc\Controller\AbstractActionController;


abstract class AbstractMagelinkActionController extends AbstractActionController
{

    /** @var array|NULL $_config */
    protected $_config = NULL;


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
    protected function hasCronjobs()
    {
        $config = $this->getConfigData();
        return isset($config['magelink_cron']) && is_array($config['magelink_cron']) && count($config['magelink_cron']);
    }

    /**
     * @return array $cronJobs
     */
    protected function getCronjobs()
    {
        $cronjobs = array();

        if ($this->hasCronjobs()) {
            $magelinkCron = $this->_config['magelink_cron'];
            foreach ($magelinkCron as $name=>$cronjobData) {
                $class = __CLASS__;
                extract($cronjobData, EXTR_IF_EXISTS);

                $cronjob = new $class($cronjobData);
                if ($cronjob instanceof CronRunnable) {
                    $cronjob->setServiceLocator($this->getServiceLocator());
                    $cronjobs[$name] = $cronjob;

                    $logLevel = LogService::LEVEL_DEBUGEXTRA;
                    $logCode = 'cron_add';
                    $logMessage = 'Cron '.$name.' added the cronjobs array';
                }else{
                    $logLevel = LogService::LEVEL_ERROR;
                    $logCode = 'cron_add_fail';
                    $logMessage = 'Cron '.$name.' does not extend CronRunnable: '.$class;
                }

                $this->getServiceLocator()->get('logService')
                    ->log($logLevel, $logCode, $logMessage, array('data'=>$cronjobData), array('cronjob'=>$cronjob));
            }
        }

        return $cronjobs;
    }

}

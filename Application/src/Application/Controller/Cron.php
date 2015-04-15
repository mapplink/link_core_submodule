<?php
/**
 * Magelink Cron
 * Manages calling of individual cron tasks during a run.
 *
 * @category Application
 * @package Application\Controller
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 *
 * This software is subject to our terms of trade and any applicable licensing agreements.
 */

namespace Application\Controller;

use Application\CronRunnable;
use Application\Helper\ErrorHandler;
use Application\Service\ApplicationConfigService;
use Log\Service\LogService;
use Log\Logger\EmailLogger;
use Magelink\Exception\MagelinkException;
use Web\Controller\CRUD\LogEntryAdminController;
use Zend\Console\Request as ConsoleRequest;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorAwareInterface;


class Cron extends AbstractActionController implements ServiceLocatorAwareInterface
{

    /**
     * No index action on cron
     * @throws MagelinkException
     */
    public function indexAction()
    {
        throw new MagelinkException('Invalid Cron action');
    }

    /*
     * @throws SyncException
     */
    public function runAction()
    {
        new ErrorHandler();

        if (extension_loaded('newrelic')) {
            newrelic_background_job(TRUE);
        }
        $request = $this->getRequest();

        /* Make sure that we are running in a console and the user has not tricked our application into running this
           action from a public web server. */
        if (!$request instanceof ConsoleRequest){
            throw new \RuntimeException('You can only use this action from a console!');
        }

        $this->getServiceLocator()->get('zend_db');
        /** @var ApplicationConfigService $applicationConfigService */
        $applicationConfigService = $this->getServiceLocator()->get('applicationConfigService');

        /** @var int $minutes Timestamp rounded to minutes */
        $time = time();
        $minutes = floor($time / 60);
        $time = date('H:i:s d/m/y', $time);

        $job = $request->getParam('job');
        if ($job == 'all') {
            $job = NULL;
        }
        
        if (!$applicationConfigService->isCronjob()) {
            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_ERROR,
                    'cron_err',
                    'ERROR: No cron jobs configured',
                    array()
                );
            die();
        }

        $ran = FALSE;
        /** @var Cronrunnable $magelinkCron */
        foreach ($applicationConfigService->getCronjobs() as $name=>$magelinkCron) {

            if ($job === NULL || $job == $name) {
                $ran = TRUE;

                $runCron = $magelinkCron->cronCheck($minutes);
                if ($job == $name) {
                    $runCron = TRUE;
                }

                $logData = array(
                    'time'=>date('H:i:s d/m/y', time()),
                    'name'=>$name,
                    'class'=>get_class($magelinkCron),
                );
                $logEntities = array('magelinkCron'=>$magelinkCron);

                if (!$runCron) {
                    $logMessage = 'Skipping cron job '.$name;
                    $this->getServiceLocator()->get('logService')
                        ->log(LogService::LEVEL_INFO, 'cron_skip', $logMessage, $logData, $logEntities);
                }elseif (!$magelinkCron->checkIfUnlocked()) {

                    $logCode = 'cron_lock';
                    $logMessage = 'Cron job '.$name.' locked.';
                    if ($magelinkCron->notifyCustomer()) {
                        $logCode = EmailLogger::ERROR_TO_CLIENT_CODE.$logCode;
                        $logMessage .= ' Please check the synchronisation process '.$name.' in the admin area.';
                    }else {
                        $logMessage .= ' This is a pre-warning. The Client is not notified yet.';
                    }
                    $this->getServiceLocator()->get('logService')
                        ->log(LogService::LEVEL_ERROR, $logCode, $logMessage, $logData, $logEntities);
                }else{
                    $magelinkCron->cronRun();
                }
            }
        }

        if (!$ran && $job !== NULL) {
            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_ERROR,
                    'cron_notfound',
                    'Could not find requested cron job '.$job,
                    array('job'=>$job)
                );
        }

        $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_DEBUG,
            'cron_done', 'Cron completed', array('start time'=>$time, 'end time'=>date('H:i:s d/m/y')));
        die();
    }
}
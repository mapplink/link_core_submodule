<?php
/**
 * Magelink Cron:  * Manages calling of individual cron tasks during a run.
 * @package Application\Controller
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Application\Controller;

use Application\CronRunnable;
use Application\Helper\ErrorHandler;
use Application\Service\ApplicationConfigService;
use Log\Service\LogService;
use Log\Logger\EmailLogger;
use Magelink\Exception\MagelinkException;
use Magelink\Exception\SyncException;
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
        if (!$request instanceof ConsoleRequest) {
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
                ->log(LogService::LEVEL_ERROR, 'crn_none_err', 'No cron jobs configured', array());
            die();
        }

        $ran = FALSE;
        foreach ($applicationConfigService->getCronjobs() as $name=>$magelinkCron) {
            if ($job === NULL || $job == $name) {
                $ran = TRUE;

                try {
                    $runCron = $magelinkCron->cronCheck($minutes);
                    if ($job == $name) {
                        $runCron = TRUE;
                    }

                    if (!$runCron) {
                        $logMessage = 'Skipping cron job '.$name;
                        $logData = array('time'=>date('H:i:s d/m/y', time()), 'name'=>$name);
                        $this->getServiceLocator()->get('logService')
                            ->log(LogService::LEVEL_INFO, 'crn_skip', $logMessage, $logData);
                    }else {
                        $magelinkCron->cronRun();
                    }
                }catch (SyncException $syncException) {
                    $this->getServiceLocator()->get('logService')
                        ->log(LogService::LEVEL_INFO, 'crn_err', $syncException->getMessage(), array('cron'=>$name),
                            array('cron'=>$magelinkCron, 'exception'=>$syncException), FALSE);
                }
            }
        }

        if (!$ran && $job !== NULL) {
            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_ERROR, 'crn_noex_err',
                    'Could not find requested cron job '.$job, array('job'=>$job));
        }

        $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_DEBUG, 'crn_all_done',
            'Cron processing completed', array('start time'=>$time, 'end time'=>date('H:i:s d/m/y')));
        die();
    }
}

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
use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Web\Controller\CRUD\LogEntryAdminController;
use Zend\Console\Request as ConsoleRequest;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorAwareInterface;


class Cron extends AbstractActionController implements ServiceLocatorAwareInterface
{

    const LOCKS_DIRECTORY = 'data/locks';

    /**
     * Get file name of cronjob
     * @param $code
     * @return string
     */
    public static function getLockFileName($code)
    {
        $fileName = self::LOCKS_DIRECTORY.'/'.bin2hex(crc32('cron-'.$code)).'.lock';
        return $fileName;
    }

    /**
     * Check if cronjob is unlocked
     * @param $code
     * @return bool
     */
    public static function checkIfUnlocked($code)
    {
        $fileName = self::getLockFileName($code);
        $unlocked = !file_exists($fileName);

        return $unlocked;
    }

    public static function lockedSince($code)
    {
        if (self::checkIfUnlocked($code)) {
            $sinceTimestamp = FALSE;
        }else {
            $fileName = Cron::getLockFileName($code);
            $lockInformation = file_get_contents($fileName);

            $cronName = strtok($lockInformation, ';');
            $sinceTimestamp = strtok(';');
        }

        return $sinceTimestamp;
    }

    /**
     * Acquire an exclusive lock for the provided lock codename
     * @param $code
     * @return bool
     * @throws MagelinkException
     */
    protected function acquireLock($code)
    {
        if (!is_dir(self::LOCKS_DIRECTORY)) {
            mkdir(self::LOCKS_DIRECTORY);
        }
        if (!is_writable(self::LOCKS_DIRECTORY)) {
            throw new SyncException('Lock directory not writable!');
        }

        $unlocked = self::checkIfUnlocked($code);

        if ($unlocked){
            $fileName = self::getLockFileName($code);
            $handle = fopen($fileName, 'x');

            if (!$handle) {
                $unlocked = FALSE;
            }else{
                $unlocked = flock($handle, LOCK_EX | LOCK_NB);
                if ($unlocked) {
                    fwrite($handle, $code.'; '.time().'; Date: '.date('Y-m-d H:i:s'));
                    fflush($handle);
                    flock($handle, LOCK_UN);
                    fclose($handle);
                }
            }
        }

        return $unlocked;
    }

    /**
     * Release an exclusive lock for the provided lock codename. NOTE: Does not check if we have the lock, simply unlocks, so can be dangerous.
     * @param $code
     */
    protected function releaseLock($code)
    {
        $maxTries = 3;
        do {
            $fileName = self::getLockFileName($code);
            unlink($fileName);
        }while (!($unlocked = self::checkIfUnlocked($code)) && --$maxTries > 0);

        return $unlocked;
    }

    /**
     * No index action on cron
     * @throws MagelinkException
     */
    public function indexAction()
    {
        throw new MagelinkException('Invalid Cron action');
    }

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

        /** @var int $minutes Timestamp rounded to minutes */
        $time = time();
        $minutes = floor($time / 60);
        $time = date('H:i:s d/m/y', $time);

        $job = $request->getParam('job');
        if ($job == 'all') {
            $job = NULL;
        }
        
        if (!$this->hasCronjobs()) {
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
        foreach ($this->getCronjobs() as $name=>$magelinkCron) {

            if ($job === NULL || $job == $name) {
                $ran = TRUE;

                if ($job == $name) {
                    $runCron = TRUE;
                }else{
                    $runCron = $magelinkCron->cronCheck($minutes);
                }

                $start = time();
                $startDate = date('H:i:s d/m/y', $start);
                $logData = array(
                    'time'=>$startDate,
                    'name'=>$name,
                    'class'=>get_class($magelinkCron),
                    'file'=>__FILE__
                );
                $logEntities = array('magelinkCron'=>$magelinkCron);

                if (!$runCron) {
                    $logMessage = 'Skipping cron job '.$name;
                    $this->getServiceLocator()->get('logService')
                        ->log(LogService::LEVEL_INFO, 'cron_skip', $logMessage, $logData, $logEntities);
                }elseif (!self::checkIfUnlocked($name)) {
                    $logMessage = 'Cron job '.$name.' locked. Don\'t take any action, before this error appears'
                        .' at least for a 2nd time in a row.';
                    $this->getServiceLocator()->get('logService')
                        ->log(LogService::LEVEL_ERROR, 'cno_cron_lock', $logMessage, $logData, $logEntities);
                }else{
                    $lock = $this->acquireLock($name);
                    $logMessage = 'Running cron job: '.$name.', begin '.$startDate;
                    $this->getServiceLocator()->get('logService')
                        ->log(LogService::LEVEL_DEBUGEXTRA, 'cron_run_'.$name, $logMessage, $logData, $logEntities);

                    $magelinkCron->cronRun();

                    $end = time();
                    $runtime = $end - $start;
                    $logMessage = 'Cron job '.$name.' finished at '.date('H:i:s d/m/y', $end).'.'
                        .' Runtime was '.round($runtime / 60, 1).' minutes.';
                    $logData['runtime[s]'] = $runtime;
                    $this->getServiceLocator()->get('logService')
                        ->log(LogService::LEVEL_DEBUG, 'cron_run_'.$name, $logMessage, $logData, $logEntities);

                    if (!$this->releaseLock($name)) {
                        $file = self::getLockFileName($name);
                        $logMessage = 'Unlocking of cron job '.$name.' ('.$file.') failed';
                        $logData = array('name'=>$name, 'file'=>$file);
                        $this->getServiceLocator()->get('logService')
                            ->log(LogService::LEVEL_ERROR, 'cron_unl_fail', $logMessage, $logData);
                    }
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

        $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_INFO,
            'cron_done', 'Cron completed', array('start time'=>$time, 'end time'=>date('H:i:s d/m/y')));
        die();
    }
}
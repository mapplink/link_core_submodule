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

use Magelink\Exception\MagelinkException;
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
     * @throws \Magelink\Exception\MagelinkException
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
        $unlockTries = 3;
        do {
            $fileName = self::getLockFileName($code);
            unlink($fileName);

        }while (!($unlocked = self::checkIfUnlocked($code)) && --$unlockTries > 0);

        return $unlocked;
    }

    /**
     * No index action on cron
     * @throws MagelinkException
     */
    public function indexAction()
    {
        throw new \Magelink\Exception\MagelinkException('Invalid Cron action');
    }

    public function runAction()
    {
        new \Application\Helper\ErrorHandler();

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
        
        $config = $this->getServiceLocator()->get('Config');

        if (!isset($config['magelink_cron'])) {
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_ERROR,
                    'cron_err',
                    'ERROR: No cron jobs configured',
                    array()
                );
            die();
        }

        $ran = FALSE;
        $cronJobs = $config['magelink_cron'];

        foreach ($cronJobs as $name=>$class) {
            if ($job === NULL || $job == $name) {
                $ran = TRUE;

                $magelinkCron = new $class();
                if (!($magelinkCron instanceof \Application\CronRunnable)) {
                    $message = 'Cron task does not implement CronRunnable - '.$class;
                    throw new \Magelink\Exception\MagelinkException($message);
                }
                if ($magelinkCron instanceof \Zend\ServiceManager\ServiceLocatorAwareInterface) {
                    $magelinkCron->setServiceLocator($this->getServiceLocator());
                }

                if ($job == $name) {
                    $runCron = TRUE;
                }else{
                    $runCron = $magelinkCron->cronCheck($minutes);
                }

                $logInfo = array('time'=>date('H:i:s d/m/y', time()), 'name'=>$name, 'class'=>$class);
                if (!$runCron) {
                    $this->getServiceLocator()->get('logService')
                        ->log(\Log\Service\LogService::LEVEL_INFO,
                            'cron_skip',
                            'Skipping cron job '.$name,
                            $logInfo
                        );
                }elseif (!self::checkIfUnlocked($name)) {
                    $this->getServiceLocator()->get('logService')
                        ->log(\Log\Service\LogService::LEVEL_ERROR,
                            'cron_locked',
                            'Locked cron job '.$name,
                            $logInfo
                        );
                }else{
                    $lock = $this->acquireLock($name);
                    $this->getServiceLocator()->get('logService')
                        ->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA,
                            'cron_run_'.substr($name, 0, 4),
                            'Running cron job: '.$name.', begin '.date('H:i:s d/m/y'),
                            $logInfo
                        );
                    $magelinkCron->cronRun();
                    $this->getServiceLocator()->get('logService')
                        ->log(
                            \Log\Service\LogService::LEVEL_DEBUG,
                            'cron_run_'.substr($name, 0, 4),
                            'Cron job '.$name.' finished at '.date('H:i:s d/m/y'),
                            $logInfo
                        );
                    if (!$this->releaseLock($name)) {
                        $file = self::getLockFileName($name);
                        $this->getServiceLocator()->get('logService')
                            ->log(\Log\Service\LogService::LEVEL_ERROR,
                                'cron_unl_fail',
                                'Unlocking of cron job '.$name.' ('.$file.') failed',
                                array('name'=>$name, 'file'=>$file)
                            );
                    }
                }
            }
        }

        if (!$ran && $job !== NULL) {
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_ERROR,
                    'cron_notfound',
                    'Could not find requested cron job '.$job,
                    array('job'=>$job)
                );
        }

        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_INFO,
            'cron_done', 'Cron completed', array('start time'=>$time, 'end time'=>date('H:i:s d/m/y')));
        die();
    }
}
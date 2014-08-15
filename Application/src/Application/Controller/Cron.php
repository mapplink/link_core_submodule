<?php

/* 
 * Copyright (c) 2014 Lero9 Limited
 * All Rights Reserved
 * This software is subject to our terms of trade and any applicable licensing agreements.
 */

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Console\Request as ConsoleRequest;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Magelink\Exception\MagelinkException;

/**
 * Manages calling of individual cron tasks during a run.
 */
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
        if (file_exists($fileName)) {
            $unlocked = FALSE;
        }else{
            $unlocked = TRUE;
        }

        return $unlocked;
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
        if (!is_writable(self::LOCKS_DIRECTORY)){
            throw new MagelinkException('Lock directory not writable!');
        }

        $unlocked = self::checkIfUnlocked($code);

        if ($unlocked){
            $fileName = self::getLockFileName($code);
            $handle = fopen($fileName, 'x');

            if (!$handle) {
                $unlocked = FALSE;
            }else{
                $res = flock($handle, LOCK_EX | LOCK_NB);
                if (!$res){
                    $unlocked = FALSE;
                }else{
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
        $fileName = self::getLockFileName($code);
        unlink($fileName);
    }

    public function indexAction()
    {
        throw new \Magelink\Exception\MagelinkException('Invalid Cron action');
    }

    public function runAction(){
        new \Application\Helper\ErrorHandler();

        if (extension_loaded('newrelic')) {
            newrelic_background_job(true);
        }
        $request = $this->getRequest();

        // Make sure that we are running in a console and the user has not tricked our
        // application into running this action from a public web server.
        if (!$request instanceof ConsoleRequest){
            throw new \RuntimeException('You can only use this action from a console!');
        }

        $this->getServiceLocator()->get('zend_db');

        $time = time();
        $time = floor($time/300)*300;

        $job = $request->getParam('job');
        if($job == 'all'){
            $job = null;
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

        $ran = false;
        $cronJobs = $config['magelink_cron'];
        foreach($cronJobs as $name=>$class){
            if($job !== null && $job != $name){
                // If we have a job specified, and this is not it, skip.
                continue;
            }

            $ran = TRUE;

            $obj = new $class();
            if(!($obj instanceof \Application\CronRunnable)){
                throw new \Magelink\Exception\MagelinkException('Cron task does not implement CronRunnable - ' . $class);
            }
            if($obj instanceof \Zend\ServiceManager\ServiceLocatorAwareInterface){
                $obj->setServiceLocator($this->getServiceLocator());
            }
            
            $check = $obj->cronCheck($time);
            if($job == $name){
                $check = TRUE;
                // Forcing run for forced name
            }
            if(!$check){
                $this->getServiceLocator()->get('logService')
                    ->log(\Log\Service\LogService::LEVEL_INFO,
                        'cron_skip',
                        'Skipping cron job '.$name,
                        array('time'=>$time, 'name'=>$name, 'class'=>$class)
                    );
                continue;
            }else{
                $lock = $this->acquireLock($name);
                if (!$lock) {
                    $this->getServiceLocator()->get('logService')
                        ->log(\Log\Service\LogService::LEVEL_ERROR,
                            'cron_locked',
                            'Locked cron job '.$name,
                            array('time'=>$time, 'name'=>$name, 'class'=>$class)
                        );
                    continue;
                }
                $this->getServiceLocator()->get('logService')
                    ->log(\Log\Service\LogService::LEVEL_DEBUG,
                        'cron_run',
                        'Running cron job '.$name,
                        array('time'=>$time, 'name'=>$name, 'class'=>$class)
                    );
                $obj->cronRun();
                $this->releaseLock($name);
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

        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_INFO, 'cron_done', 'Cron completed', array('time'=>$time));
        die();
    }
}
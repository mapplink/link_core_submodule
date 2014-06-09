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

    /**
     * Acquire an exclusive lock for the provided lock codename
     * @param $code
     * @return bool
     * @throws \Magelink\Exception\MagelinkException
     */
    protected function acquireLock($code){
        if(!is_dir('data/locks')){
            mkdir('data/locks');
        }
        if(!is_writable('data/locks')){
            throw new MagelinkException('Lock directory not writable!');
        }
        $filename = 'data/locks/' . bin2hex(crc32($code)) . '.lock';

        if(file_exists($filename)){
            return false;
        }else{
            $hdl = fopen($filename, 'x');
            if(!$hdl){
                return false;
            }else{
                $res = flock($hdl, LOCK_EX | LOCK_NB);
                if(!$res){
                    return false;
                }
                fwrite($hdl, date('Y-m-d H:i:s'));
                fflush($hdl);
                flock($hdl, LOCK_UN);
                fclose($hdl);
                return true;
            }
        }
        return false;
    }

    /**
     * Release an exclusive lock for the provided lock codename. NOTE: Does not check if we have the lock, simply unlocks, so can be dangerous.
     * @param $code
     */
    protected function releaseLock($code){
        $filename = 'data/locks/' . bin2hex(crc32($code)) . '.lock';

        unlink($filename);
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

        if(!isset($config['magelink_cron'])){
            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR, 'cron_err', 'ERROR: No cron jobs configured', array());
            die();
        }

        $ran = false;
        $cronJobs = $config['magelink_cron'];
        foreach($cronJobs as $name=>$class){
            if($job !== null && $job != $name){
                // If we have a job specified, and this is not it, skip.
                continue;
            }

            $lock = $this->acquireLock('cron-'.$name);
            if(!$lock){
                $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR, 'cron_locked', 'Locked cron job ' . $name, array('time'=>$time, 'name'=>$name, 'class'=>$class));
                continue;
            }

            $ran = true;

            $obj = new $class();
            if(!($obj instanceof \Application\CronRunnable)){
                throw new \Magelink\Exception\MagelinkException('Cron task does not implement CronRunnable - ' . $class);
            }
            if($obj instanceof \Zend\ServiceManager\ServiceLocatorAwareInterface){
                $obj->setServiceLocator($this->getServiceLocator());
            }
            
            $check = $obj->cronCheck($time);
            if($job == $name){
                $check = true;
                // Forcing run for forced name
            }
            if(!$check){
                $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_INFO, 'cron_skip', 'Skipping cron job ' . $name, array('time'=>$time, 'name'=>$name, 'class'=>$class));
                $this->releaseLock('cron-'.$name);
                continue;
            }else{
                $ran = true;
                $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUG, 'cron_run', 'Running cron job ' . $name, array('time'=>$time, 'name'=>$name, 'class'=>$class));
                $obj->cronRun();
                $this->releaseLock('cron-'.$name);
            }
            
        }

        if(!$ran && $job !== null){
            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR, 'cron_notfound', 'Could not find requested cron job ' . $job, array('job'=>$job));
        }

        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_INFO, 'cron_done', 'Cron completed', array('time'=>$time));
        die();
        
    }
}
<?php
/*
 * The base abstract class for all Magelink Cron Tasks.
 *
 * @category Application
 * @package Application
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Application;

use Application\Helper\ErrorHandler;
use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Magelink\Exception\SyncException;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;


abstract class CronRunnable implements ServiceLocatorAwareInterface
{

    const LOCKS_DIRECTORY = 'data/locks';
    const FIRST_CUSTOMER_LOCK_NOTIFICATION = 2;

    /** @var string|NULL $name */
    protected $name = NULL;

    /** @var string|NULL $filename */
    protected $filename = NULL;

    /** @var array $attributes  default values */
    protected $attributes = array(
        'interval'=>1,
        'offset'=>0,
        'lockTime'=>180
    );

    /** @var ServiceLocatorInterface $_serviceLocator */
    protected $_serviceLocator;


    /**
     * Set service locator
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->_serviceLocator = $serviceLocator;
    }

    /**
     * Get service locator
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->_serviceLocator;
    }

    /**
     * @param string $name
     * @param array $cronData
     */
    public function __construct($name, array $cronData)
    {
        if ($name && is_string($name)) {
            $this->name = $name;
            $this->filename = self::LOCKS_DIRECTORY.'/'.bin2hex(crc32('cron-'.$name)).'.lock';

            foreach ($this->attributes as $code => $defaultValue) {
                if (isset($cronData[$code]) && is_int($cronData[$code]) && $cronData[$code] > 0) {
                    $this->attributes[$code] = $cronData[$code];
                }
            }
        }else{
            throw new SyncException(get_class($this).' creation failed. No valid name provided.');
        }
    }

    /**
     * Checks whether we should run the cron task this run through.
     * @param int $minutes
     * @param array $cronData
     * @return bool $run
     */
    public function cronCheck($minutes)
    {
        $run = ($minutes % $this->getInterval() == $this->getOffset());
        return $run;
    }

    /**
     * Wrapper which does the logging and error handling on the scheduled actions.
     * @throws SyncException
     */
    public function cronRun()
    {
        $start = microtime(TRUE);
        $startDate = date('H:i:s d/m/y', $start);
        $logData = array('start'=>$startDate, 'name'=>$this->getName(), 'class'=>get_class($this));

        $lock = $this->acquireLock();

        $logMessage = 'Running cron job: '.$this->getName().', begin '.$startDate;
        $logEntities = array('magelinkCron'=>$this);
        $this->getServiceLocator()->get('logService')
            ->log(LogService::LEVEL_DEBUGEXTRA, 'cron_run_'.$this->getName(), $logMessage, $logData);

        $this->_cronRun();

        $end = microtime(TRUE);
        $endDate = date('H:i:s d/m/y', $end);

        $runtime = $end - $start;
        $runMinutes = floor($runtime / 60);
        $runSeconds = $runtime % 60;

        $logMessage = 'Cron job '.$this->getName().' finished at '.$end
        .'. Runtime was '.($runMinutes ? $runMinutes.' min and ' : '').$runSeconds.' s.';
        $this->getServiceLocator()->get('logService')
            ->log(LogService::LEVEL_INFO, 'cron_run_'.$this->getName(), $logMessage, $logData, $logEntities);

        if (!$this->releaseLock()) {
            $logMessage = 'Unlocking of cron job '.$this->getName().' ('.$this->filename.') failed';
            $logData = array('name'=>$this->getName(), 'file'=>$this->filename);
            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_ERROR, 'cron_unl_fail', $logMessage, $logData);
        }
    }

    /**
     * Performs any scheduled actions.
     */
    abstract protected function _cronRun();

    /**
     * Check if cronjob is unlocked
     * @return bool $unlocked
     */
    public function checkIfUnlocked()
    {
        $unlocked = !file_exists($this->filename);
        return $unlocked;
    }

    /**
     * Acquire an exclusive lock for the provided lock codename
     * @param $code
     * @return bool
     * @throws MagelinkException
     */
    protected function acquireLock()
    {
        if (!is_dir(self::LOCKS_DIRECTORY)) {
            mkdir(self::LOCKS_DIRECTORY);
        }
        if (!is_writable(self::LOCKS_DIRECTORY)) {
            throw new SyncException('Lock directory not writable!');
        }

        $unlocked = $this->checkIfUnlocked();
        if ($unlocked) {
            $handle = fopen($this->filename, 'x');
            if (!$handle) {
                $unlocked = FALSE;
            }else{
                $unlocked = flock($handle, LOCK_EX | LOCK_NB);
                if ($unlocked) {
                    fwrite($handle, $this->getName().'; '.time().'; Date: '.date('Y-m-d H:i:s'));
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
     * @param string $code
     * @return bool $unlocked
     */
    protected function releaseLock()
    {
        $maxTries = 3;
        do {
            unlink($this->filename);
        }while (!($unlocked = $this->checkIfUnlocked()) && --$maxTries > 0);

        return $unlocked;
    }

    public function adminReleaseLock()
    {
        $name = $this->getName();
        $user = $this->getServiceLocator()->get('zfcuser_auth_service')->getIdentity();

        if (!is_writable(self::LOCKS_DIRECTORY)) {
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_ERROR,
                    'cron_unlock_fail_'.$name,
                    'Unlock failed on cron job '.$name.'. Directory not writable.',
                    array('cron job'=>$name, 'directory'=>realpath(self::LOCKS_DIRECTORY), 'user id'=>$user->getId())
                );
            $success = FALSE;
        }elseif (!$this->canAdminUnlock()) {
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_ERROR,
                    'cron_unlock_lock_'.$name,
                    'Unlock failed on cron job '.$name.'. Admin cannot unlock yet.',
                    array('name'=>$name, 'locked seconds'=>$this->getAdminLockedSeconds(), 'user id'=>$user->getId())
                );
            $success = FALSE;
        }else{
            $this->releaseLock();

            $filename = realpath($this->filename);
            $subject = 'cron_unlock_'.$name;
            $message = 'Successfully unlocked cronjob '.$name.' ('.$filename.'). User '.$user->getId().'.';

            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_INFO,
                    $subject, $message, array('cron job'=>$name, 'file name'=>$filename, 'user id'=>$user->getId()));
            mail(ErrorHandler::ERROR_TO, $subject, $message, 'From: ' . ErrorHandler::ERROR_FROM);
            $success = TRUE;
        }

        return $success;
    }

    /**
     * Get timestamp of lock start time
     * @return bool|string
     */
    public function lockedSince()
    {
        if ($this->checkIfUnlocked()) {
            $sinceTimestamp = FALSE;
        }else {
            $lockInformation = file_get_contents($this->filename);
            $cronName = strtok($lockInformation, ';');
            $sinceTimestamp = strtok(';');
        }

        return $sinceTimestamp;
    }

    /**
     * Return the seconds in which the cron can be unlocked through the backend
     * @return int
     */
    public function getAdminLockedSeconds()
    {
        $lockedSeconds = $this->lockedSince() + $this->getLockTime() * 60 - time();
        return $lockedSeconds;
    }

    /**
     * @return bool
     */
    public function canAdminUnlock()
    {
        return !($this->getAdminLockedSeconds() > 0);
    }

    /**
     * @return bool $notifyCustomer
     */
    public function notifyCustomer()
    {
        $notifyAfter = $this->lockedSince() + $this->getInterval() * (self::FIRST_CUSTOMER_LOCK_NOTIFICATION - 0.1);
        return time() >= $notifyAfter;
    }

    /**
     * @return NULL|string $this->name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return int $this->attributes['interval']
     */
    public function getInterval()
    {
        return $this->attributes['interval'];
    }

    /**
     * @return int $this->attributes['offset']
     */
    public function getOffset()
    {
        return $this->attributes['offset'];
    }

    /**
     * @return int $this->attributes['lockTime']
     */
    public function getLockTime()
    {
        return $this->attributes['lockTime'];
    }

}
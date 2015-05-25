<?php
/*
 * The base abstract class for all Magelink Cron Tasks.
 * @category Application
 * @package Application
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Application;

use Application\Helper\ErrorHandler;
use Application\Service\ApplicationConfigService;
use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Magelink\Exception\SyncException;
use Zend\Db\TableGateway\TableGateway;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;


abstract class CronRunnable implements ServiceLocatorAwareInterface
{

    /** @var bool $scheduledRun */
    protected $scheduledRun;

    /** @var string|NULL $name */
    protected $name = NULL;
    /** @var string|NULL $lockDirectory */
    protected $lockDirectory = NULL;
    /** @var string|NULL $filename */
    protected $filename = NULL;
    /** @var array $attributes  default values */
    protected $attributes = array(
        'interval'=>NULL,
        'offset'=>0,
        'lockTime'=>NULL,
        'autoLockMultiplier'=>10,
        'overdue'=>FALSE
    );

    /** @var TableGateway $_cronTableGateway */
    protected $_cronTableGateway;
    /** @var  array|FALSE $cronData */
    protected $cronData;

    /** @var  ApplicationConfigService $_applicationConfigService */
    protected $_applicationConfigService;
    /** @var  LogService $_logService */
    protected $_logService;
    /** @var ServiceLocatorInterface $_serviceLocator */
    protected $_serviceLocator;


    /**
     * Set service locator
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->_serviceLocator = $serviceLocator;
        $this->_applicationConfigService = $serviceLocator->get('applicationConfigService');
        $this->_logService = $serviceLocator->get('logService');
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
            $this->lockDirectory = $this->_applicationConfigService->getConfigCronLockDirectory();
            $this->filename = $this->lockDirectory.'/'.bin2hex(crc32('cron-'.$name)).'.lock';

            foreach ($this->attributes as $code=>$defaultValue) {
                if (isset($cronData[$code]) && is_int($cronData[$code]) && $cronData[$code] > 0) {
                    $this->attributes[$code] = $cronData[$code];
                }
            }

            if ($cronData['lockTime'] === NULL && isset($cronData['interval'])) {
                $cronData['lockTime'] = $cronData['interval'] * $cronData['autoLockMultiplier'];
            }
            foreach ($this->attributes as $code) {
                if ($cronData[$code] === NULL) {
                    throw new SyncException(get_class($this).' setup failed. No valid '.$code.' value provided.');
                }
            }
        }else{
            throw new SyncException(get_class($this).' creation failed. No valid name provided.');
        }
    }

    /**
     * Returns a new TableGateway instance for cron table
     * @return TableGateway $this->_cronTableGateway
     */
    protected function getCronTableGateway()
    {
        if (!$this->_cronTableGateway) {
            $this->_cronTableGateway = new TableGateway('cron', $this->getServiceLocator()->get('zend_db'));
        }

        return $this->_cronTableGateway;
    }

    /**
     * @return \ArrayObject|FALSE
     */
    protected function getCronDataFromDatabase()
    {
        if (!$this->cronData) {
            $cronTableGateway = $this->getCronTableGateway();
            $rowset = $cronTableGateway->select(array('cron_name'=>$this->getName()));
            $this->cronData = $rowset->current();
        }

        return $this->cronData;
    }

    /**
     * @return bool $isCronDataExisting
     */
    protected function isCronDataExisting()
    {
        return (bool) $this->getCronDataFromDatabase();
    }

    /**
     * @return bool $isOverdueEnabled
     */
    protected function isOverdueEnabled()
    {
        return $this->attributes['overdue'];
    }

    /**
     * @return bool $isOverdue
     */
    protected function isOverdue()
    {
        $cronData = $this->getCronDataFromDatabase();
        return ($cronData && $cronData['overdue'] ? TRUE : FALSE);
    }

    /**
     * @return int $flaggedCronAsOverdue
     */
    protected function flagCronAsOverdue()
    {
        $tableGateway = $this->getCronTableGateway();
        $set = array('overdue'=>1, 'updated_at'=>date('Y-m-d H:i:s'));
        if ($isCronDataExisting = $this->isCronDataExisting()) {
            $where = array('cron_name'=>$this->getName());
        }else{
            $set['cron_name'] = $this->getName();
        }

        $try = 1;
        $maxTries = 5;
        do {
            usleep(($try - 1) * 600);
            if ($isCronDataExisting) {
                $success = $tableGateway->update($set, $where);
            }else{
                $success = $tableGateway->insert($set);
            }
        }while ($try++ < $maxTries && !$success);

        $logCode = 'cron_'.$this->getCode().'_sof';
        $logData = array('dateTime'=>date('d/m H:i:s'), 'magelinkCron'=>$this->getName());
        if ($success) {
            $logMessage = 'Flagged cron '.$this->getName().' successfully as overdue.';
            $this->_logService->log(LogService::LEVEL_INFO, $logCode, $logMessage, $logData);
        }else{
            $logMessage = 'Overdue flagging of cron '.$this->getName().' failed';
            $this->_logService->log(LogService::LEVEL_ERROR, $logCode.'_err', $logMessage, $logData);
        }

        return $success;
    }

    /**
     * @return bool|int $removedOverdueFlag
     */
    protected function removeOverdueFlag()
    {
        if ($this->isOverdue()) {
            $tableGateway = $this->getCronTableGateway();
            $set = array('overdue'=>0, 'updated_at'=>date('Y-m-d H:i:s'));
            $where = array('cron_name'=>$this->getName());

            $try = 1;
            $maxTries = 5;
            do {
                usleep(($try - 1) * 600);
                $success = $tableGateway->update($set, $where);
            }while ($try++ < $maxTries && !$success);

            $logCode = 'cron_'.$this->getCode().'_rof';
            $logData = array('dateTime'=>date('d/m H:i:s'), 'magelinkCron'=>$this->getName());
            if ($success) {
                $logMessage = 'Removed overdue flag on cron '.$this->getName().' successfully.';
                $this->_logService->log(LogService::LEVEL_INFO, $logCode, $logMessage, $logData);
            }else{
                $logMessage = 'Overdue flag removal on cron '.$this->getName().' failed';
                $this->_logService->log(LogService::LEVEL_ERROR, $logCode.'_err', $logMessage, $logData);
            }
        }else{
            $success = TRUE;
        }

        return $success;

    }

    /**
     * Checks whether we should run the cron task this run through.
     * @param int $minutes
     * @param array $cronData
     * @return bool $run
     */
    public function cronCheck($minutes)
    {
        $this->scheduledRun = ($minutes % $this->getInterval() == $this->getOffset());
        $run = $this->scheduledRun || $this->isOverdueEnabled() && $this->isOverdue() && $this->checkIfUnlocked();

        return $run;
    }

    /**
     * Wrapper which does the logging and error handling on the scheduled actions.
     * @throws SyncException
     */
    public function cronRun()
    {
        $start = microtime(TRUE);
        $startDate = date('H:i:s d/m', $start);
        $unlocked = $this->checkIfUnlocked();

        if (!$unlocked && $this->scheduledRun) {
            $logCode = 'cron_lock_'.$this->getCode();
            $logMessage = 'Cron job '.$this->getName().' locked.';
            if ($this->notifyCustomer()) {
                $logCode = EmailLogger::ERROR_TO_CLIENT_CODE.$logCode;
                $logMessage .= ' Please check the synchronisation process `'.$this->getName().'` in the admin area.';
            }else{
                $logMessage .= ' This is a pre-warning. The Client is not notified yet.';
            }
            $logData = array('time'=>date('H:i:s d/m/y', time()), 'name'=>$this->getName(), 'class'=>get_class($this));
            $logEntities = array('magelinkCron'=>$this);
            $this->_logService->log(LogService::LEVEL_ERROR, $logCode, $logMessage, $logData, $logEntities);

            $this->flagCronAsOverdue();
        }elseif ($unlocked) {
            $lock = $this->acquireLock();
            $this->removeOverdueFlag();

            $logMessage = 'Cron '.$this->getName().' started at '.$startDate;
            $logData = array('name'=>$this->getName(), 'class'=>get_class($this), 'start'=>$startDate);
            $logEntities = array('magelinkCron'=>$this);
            $this->_logService->log(LogService::LEVEL_INFO,
                'cron_run_'.$this->getCode(), $logMessage, $logData);

            $this->_cronRun();

            $end = microtime(TRUE);
            $endDate = date('H:i:s', $end);

            $runtime = $end - $start;
            $runMinutes = floor($runtime / 60);
            $runSeconds = round(fmod($runtime, 60), 1);

            $logMessage = 'Cron '.$this->getName().' finished at '.$endDate
                .'. Runtime was '.($runMinutes ? $runMinutes.'min, ' : '').$runSeconds.'s.';
            $logData = array_merge($logData, array('end'=>$endDate, 'runtime'=>$runtime));
            $this->_logService->log(LogService::LEVEL_INFO,
                'cron_run_'.$this->getCode(), $logMessage, $logData, $logEntities);

            if (!$this->releaseLock()) {
                $logMessage = 'Unlocking of cron job '.$this->getName().' ('.$this->filename.') failed';
                $logData = array('name'=>$this->getName(), 'file'=>$this->filename);
                $this->_logService->log(LogService::LEVEL_ERROR,
                    'cron_unl_fail_'.$this->getCode(), $logMessage, $logData);
            }
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
        if (!is_dir($this->lockDirectory)) {
            mkdir($this->lockDirectory);
        }
        if (!is_writable($this->lockDirectory)) {
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
     * Releases an exclusive lock. NB: Does not check if we have the lock, simply unlocks, so can be dangerous.
     * @param string $code
     * @return bool $unlocked
     */
    protected function releaseLock()
    {
        $try = 1;
        $maxTries = 3;
        do {
            usleep(($try - 1) * 700);
            unlink($this->filename);
            $unlocked = $this->checkIfUnlocked();
        }while ($try++ < $maxTries && !$unlocked);

        return $unlocked;
    }

    /**
     * Admin unlock to release an exclusive lock. Checks if admin can unlock.
     * @return bool
     */
    public function adminReleaseLock()
    {
        $name = $this->getName();
        $user = $this->getServiceLocator()->get('zfcuser_auth_service')->getIdentity();

        if (!is_writable($this->lockDirectory)) {
            $this->_logService->log(LogService::LEVEL_ERROR,
                    'cron_unlock_dir_fail_'.$name,
                    'Unlock failed on cron job '.$name.'. Directory not writable.',
                    array('cron job'=>$name, 'directory'=>realpath($this->lockDirectory), 'user id'=>$user->getId())
                );
            $success = FALSE;
        }elseif (!$this->canAdminUnlock()) {
            $this->_logService->log(LogService::LEVEL_ERROR,
                    'cron_unlock_lock_'.$name,
                    'Unlock failed on cron job '.$name.'. Admin cannot unlock yet.',
                    array('name'=>$name, 'locked seconds'=>$this->getAdminLockedSeconds(), 'user id'=>$user->getId())
                );
            $success = FALSE;
        }else{
            $filename = realpath($this->filename);
            $success = $this->releaseLock();

            if ($success) {
                $subject = 'cron_unlock_'.$name;
                $message = 'Successfully unlocked cronjob '.$name.' ('.$filename.'); User '.$user->getId().'.';
            }else{
                $subject = 'cron_unlock_rls_fail_'.$name;
                $message = 'User '.$user->getId().' tried to unlock cronjob '.$name.' ('.$filename.') unsuccessfully.';
            }

            $this->_logService->log(LogService::LEVEL_INFO,
                    $subject, $message, array('cron job'=>$name, 'file name'=>$filename, 'user id'=>$user->getId()));
            mail(ErrorHandler::ERROR_TO, $subject, $message, 'From: ' . ErrorHandler::ERROR_FROM);
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
        $numberOfIntervalsBeforeNotifying = $this->_applicationConfigService->getConfigFirstClientNotification();
        $notifyAfter = $this->lockedSince() + $this->getIntervalSeconds() * $numberOfIntervalsBeforeNotifying;

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
     * @return NULL|string $this->name
     */
    public function getCode()
    {
        return substr($this->getName(), 0, 4);
    }

    /**
     * Get the value for the interval [min]
     * @return int $this->attributes['interval']
     */
    public function getInterval()
    {
        return $this->attributes['interval'];
    }

    /**
     * Multiplies $this->attributes['interval'] with 60 to return the interval seconds
     * @return int $intervalSeconds
     */
    protected function getIntervalSeconds()
    {
        return $this->getInterval() * 60;
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
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
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\TableGateway\TableGateway;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;


abstract class CronRunnable implements ServiceLocatorAwareInterface
{

    const MAX_OVERDUE = 1;

    /** @var bool $scheduledRun */
    protected $scheduledRun;

    /** @var string|NULL $name */
    protected $name = NULL;
    /** @var string|NULL $lockDirectory */
    protected $lockDirectory = NULL;
    /** @var string|NULL $filename */
    protected $filename = NULL;
    /** @var array $this->fileHeaders */
    protected $fileHeaders = array(
        'name',
        'timestamp',
        'timeDate',
        'processId'
    );
    /** @var array $this->fileData */
    protected $fileData = NULL;
    /** @var array $attributes  default values */
    protected $attributes = array(
        'interval'=>NULL,
        'offset'=>0,
        'lockTime'=>NULL,
        'defaultLockTime'=>600,
        'autoLockMultiplier'=>10,
        'overdue'=>FALSE
    );

    /** @var TableGateway $_cronTableGateway */
    protected $_cronTableGateway;
    /** @var  array|FALSE $cronData */
    protected $cronData;

    /** @var ApplicationConfigService $_applicationConfigService */
    protected $_applicationConfigService;
    /** @var LogService $_logService */
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
     */
    public function __construct($name)
    {
        if ($name && is_string($name)) {
            $this->name = $name;
        }else{
            throw new SyncException(get_class($this).' creation failed. No valid name provided.');
        }
    }

    /**
     * @param array $data
     */
    public function init(array $data)
    {
        if ($this->getServiceLocator() instanceof ServiceLocatorInterface) {
            $this->lockDirectory = $this->_applicationConfigService->getConfigCronLockDirectory();
            $this->filename = $this->lockDirectory.'/'.bin2hex(crc32('cron-'.$this->name)).'.lock';

            foreach ($this->attributes as $code=>$default) {
                if (isset($data[$code])) {
                    $new = $data[$code];
                    $isValidValue = (is_null($default) && is_int($new) || gettype($default) === gettype($new))
                        && ($new || $new === FALSE) || $code == 'interval' && is_string($new) && strlen($new) > 0;
                    if ($isValidValue) {
                        $this->attributes[$code] = $new;
                    }
                }
            }

            if (is_null($this->attributes['lockTime']) && isset($this->attributes['interval'])) {
                if (is_numeric($this->attributes['interval'])) {
                    $this->attributes['lockTime'] =
                        $this->attributes['interval'] * $this->attributes['autoLockMultiplier'];
                }else{
                    $this->attributes['lockTime'] = $this->attributes['defaultLockTime'];
                }
            }
            unset($this->attributes['defaultLockTime'], $this->attributes['autoLockMultiplier']);

            foreach ($this->attributes as $code=>$value) {
                if (is_null($value) || (is_int($value) || is_float($value)) && $value < 0) {
                    $message = get_class($this).' init failed.'
                        .' No valid '.$code.' value ('.var_export($value, TRUE).') provided.';
                    throw new SyncException($message);
                }
            }
        }else{
            throw new SyncException(get_class($this).' init failed. No valid service locator provided.');
        }
    }

    /** @return AdapterInterface */
    protected function getAdapterInterface()
    {
        return $this->getServiceLocator()->get('zend_db');
    }

    /**
     * Returns a new TableGateway instance for cron table
     * @return TableGateway $this->_cronTableGateway
     */
    protected function getCronTableGateway()
    {
        if (!$this->_cronTableGateway) {
            $this->_cronTableGateway = new TableGateway('cron', $this->getAdapterInterface());
        }

        return $this->_cronTableGateway;
    }

    /**
     * @return \ArrayObject|FALSE $cronDataFromDatabase
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
     * @return mixed $maxOverdue
     */
    protected function getMaxOverdue()
    {
        return min(1, self::MAX_OVERDUE);
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
        $sql = $this->getCronTableGateway()->getSql();

        if ($isCronDataExisting = $this->isCronDataExisting()) {
            $set = array('overdue'=>new Expression('overdue + 1'));
            $where = new Where();
            $where->equalTo('cron_name', $this->getName())
                ->lessThan('overdue', $this->getMaxOverdue());
        }else{
            $set = array('cron_name'=>$this->getName(), 'overdue'=>1);
        }
        $set['updated_at'] = date('Y-m-d H:i:s');

        if ($isCronDataExisting) {
            $sqlObject = $sql->update()->set($set)->where($where);
        }else{
            $sqlObject = $sql->insert()->set($set);
        }
        $sqlStatement = $sql->prepareStatementForSqlObject($sqlObject);

        $try = 1;
        $maxTries = 5;
        do {
            usleep(($try - 1) * 600);
            $success = $sqlStatement->execute();;
        }while ($try++ < $maxTries && !$success);

        $logCode = $this->getLogCode().'_sof';
        $logData = array(
            'dateTime'=>date('d/m H:i:s'),
            'magelinkCron'=>$this->getName(),
            'update sql'=>$sqlObject->getSqlString($this->getAdapterInterface()->getPlatform())
        );
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
     * @param bool|FALSE $remove
     * @return bool|int $removedOverdueFlag
     */
    protected function removeOverdueFlag($remove = FALSE)
    {
        if ($overdue = $this->isOverdue()) {
            $sql = $this->getCronTableGateway()->getSql();
            if ($remove) {
                $set = array('overdue'=>0, 'updated_at' => date('Y-m-d H:i:s'));
            }else{
                $set = array('overdue'=>new Expression('overdue - 1'), 'updated_at' => date('Y-m-d H:i:s'));
            }
            $where = new Where();
            $where->equalTo('cron_name', $this->getName());

            $try = 1;
            $maxTries = 7;
            $update = $sql->update()->set($set)->where($where);
            $sqlStatement = $sql->prepareStatementForSqlObject($update);

            do {
                usleep(($try - 1) * 500);
                $success = $sqlStatement->execute();
            }while ($try++ < $maxTries && !$success);

            $logCode = $this->getLogCode().'_rof';
            $logData = array(
                'dateTime'=>date('d/m H:i:s'),
                'magelinkCron'=>$this->getName(),
                'update sql'=>$update->getSqlString($this->getAdapterInterface()->getPlatform())
            );
            if ($success) {
                if ($this->isOverdue()) {
                    $logMessage = 'Reduced';
                }else{
                    $logMessage = 'Removed';
                }
                $logMessage .= ' overdue flag on cron '.$this->getName().' successfully.';
                $this->_logService->log(LogService::LEVEL_INFO, $logCode, $logMessage, $logData);
            }else{
                $logMessage = 'Overdue-flag-removal/reduction on cron '.$this->getName().' failed';
                $this->_logService->log(LogService::LEVEL_ERROR, $logCode.'_err', $logMessage, $logData);
            }
        }else{
            $success = TRUE;
        }

        return $success;

    }

    /**
     * @param bool|FALSE $remove
     * @return bool|int $reducedOverdueFlag
     */
    protected function reduceOverdueFlag($remove = FALSE)
    {
        if (!$this->scheduledRun || $this->getMaxOverdue() > 1) {
            $success = $this->removeOverdueFlag($remove);
        }else{
            $success = TRUE;
        }

        return $success;
    }

    /**
     * Checks whether we should run the cron task this run through.
     * @param int $minutes
     * @return bool $run
     */
    public function cronCheck($minutes)
    {
        $this->scheduledRun = ($minutes % $this->getInterval() == $this->getOffset());
        $run = $this->scheduledRun || $this->isOverdueEnabled() && $this->isOverdue() && !$this->isRunning();

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

        $logData = array('name'=>$this->getName());

        if (!$this->isRunning() && !$this->isUnlocked()) {
            $logCode = $this->getLogCode().'_fulk';
            $logMessage = 'orced remove of lock file of cron job '.$this->getName().' ('.$this->filename.') ';
            $logDataUnlock = array_merge($logData, array( 'file'=>$this->filename, 'data'=>$this->getLockFileData()));
            if ($this->releaseLock()) {
                $logMessage = 'Successful f'.$logMessage.'.';
            }else{
                $logCode .= 'err';
                $logMessage = 'F'.$logMessage.'failed.';
            }
            $this->_logService->log(LogService::LEVEL_ERROR, $logCode, $logMessage, $logDataUnlock);
            unset($logDataUnlock);
        }
        $unlocked = $this->isUnlocked();

        $logData['class'] = get_class($this);
        if (!$unlocked) {
            if ($this->scheduledRun) {
                $logLevel = LogService::LEVEL_ERROR;
                $this->flagCronAsOverdue();
            }else{
                $logLevel = LogService::LEVEL_DEBUGINTERNAL;
            }

            $logCode = $this->getLogCode().'_lck';
            $logMessage = 'Cron job '.$this->getName().' locked.';
            $logData['time'] = date('H:i:s d/m/y', time());
            $logEntities = array('magelinkCron'=>$this);

            if ($this->notifyClient()) {
                $logMessage .= ' Please check the synchronisation process `'.$this->getName().'` in the admin area.';
                $this->_logService->log($logLevel, $logCode, $logMessage, $logData, $logEntities, TRUE);
            }elseif ($this->notifyAdmin()) {
                $logMessage .= ' This is a pre-warning. The Client is not notified yet.';
                $this->_logService->log($logLevel, $logCode, $logMessage, $logData, $logEntities);
            }
        }else{
            $lock = $this->acquireLock();
            $this->reduceOverdueFlag();

            $logCode = $this->getLogCode().'_run';
            $logMessage = 'Cron '.$this->getName().' started at '.$startDate;
            $logData['start'] = $startDate;
            $logEntities = array('magelinkCron'=>$this);
            $this->_logService->log(LogService::LEVEL_INFO, $logCode, $logMessage, $logData);

            $this->_cronRun();

            $end = microtime(TRUE);
            $endDate = date('H:i:s', $end);

            $runtime = $end - $start;
            $runMinutes = floor($runtime / 60);
            $runSeconds = round(fmod($runtime, 60), 1);

            $logMessage = 'Cron '.$this->getName().' finished at '.$endDate
                .'. Runtime was '.($runMinutes ? $runMinutes.'min, ' : '').$runSeconds.'s.';
            $logData = array_merge($logData, array('end'=>$endDate, 'runtime'=>$runtime));
            $this->_logService->log(LogService::LEVEL_INFO, $logCode.'done', $logMessage, $logData, $logEntities);

            $this->releaseLock();
        }
    }

    /**
     * Performs any scheduled actions.
     */
    abstract protected function _cronRun();

    /**
     * @return array $lockData
     */
    protected function getLockData()
    {
        $data = array(
            'name'=>$this->getName(),
            'timestamp'=>time(),
            'timeDate'=>date('H:i:s d/m/Y'),
            'processId'=>posix_getpid()
        );

        return $data;
    }

    /**
     * @throws MagelinkException
     */
    protected function writeDataToLockFile()
    {
        $handle = fopen($this->filename, 'x');
        if (!$handle) {
            $unlocked = FALSE;
        }else {
            $unlocked = flock($handle, LOCK_EX | LOCK_NB);
            if ($unlocked) {
                $data = $this->getLockData();
                $contentArray = array();
                foreach ($this->fileHeaders as $key) {
                    if (isset($data[$key])) {
                        $contentArray[] = $data[$key];
                    }else{
                        $message = 'Data for lock file '.$this->filename.' ('.$this->getName().') does not match'
                            .' with the provided headers.';
                        throw new MagelinkException($message);
                        $unlocked = FALSE;
                    }
                }
                $content = implode(';', $contentArray).PHP_EOL;

                fwrite($handle, $content);
                fflush($handle);
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }

        return $unlocked;
    }
    /**
     * @param string|null $code
     * @return string|array $lockFileData
     */
    protected function getLockFileData($code = NULL)
    {
        if (is_null($this->fileData) || !is_array($this->fileData)) {
            $handle = fopen($this->filename, 'r');
            $firstLine = fgets($handle);
            fclose($handle);

            $fileData = array();
            foreach (explode(';', $firstLine) as $key=>$value) {
                $fileData[$this->fileHeaders[$key]] = $value;
            }

            if (count($fileData) === count($this->fileHeaders)) {
                $this->fileData = $fileData;
            }else{
                $message = 'Data from lock file '.$this->filename.' ('.$this->getName().') does not match'
                    .' with the provided headers: '.var_export($fileData, TRUE).'.';
                throw new MagelinkException($message);
            }

        }

        if (is_null($code)) {
            $returnValue = $this->fileData;
        }else{
            if (in_array($code, $this->fileHeaders)) {
                $returnValue = $this->fileData[$code];
            }else{
                $returnValue = FALSE;
            }
        }

        return $returnValue;
    }

    /**
     * @return bool $isRunning
     */
    public function isRunning()
    {
        $isRunning = !$this->isUnlocked();

        if ($isRunning) {
            $processId = $this->getLockFileData('processId');
            $logCode = $this->getLogCode().'_mch';

            if ($processId) {
                $processesMatching = array();
                $processId = (int) $processId;
                exec("ps -eo pid,start,cmd | grep 'zf.php cron run' | grep -v grep", $processes);

                foreach ($processes as $processNo=>$process) {
                    list($pid, $startCmd) = explode(' ', trim($process), 2);
                    $processTime = trim(strstr($startCmd, 'php ', TRUE));
                    $processCommand = trim(strstr($startCmd, 'php '));
                    if ($processId == (int) $pid) {
                        $processesMatching[$processNo] = $process;
                    }
                }

                if (count($processesMatching) == 0) {
                    $isRunning = FALSE;
                }elseif (count($processesMatching) > 1) {
                    $this->_logService->log(LogService::LEVEL_ERROR, $logCode.'_mny',
                        $processId.' is matching several processes.', array('processes'=>$processesMatching));
                }
            }else{
                $this->_logService->log(LogService::LEVEL_ERROR, $logCode.'_err',
                    ucfirst($this->getLockFileData('name')).' lock file did not contain process id.',
                    array('lock file'=>$this->getLockFileData(), 'directory'=>realpath($this->lockDirectory))
                );
            }
        }

        return $isRunning;
    }

    /**
     * Check if cronjob is unlocked
     * @return bool $unlocked
     */
    public function isUnlocked()
    {
        $unlocked = !file_exists($this->filename);
        return $unlocked;
    }

    /**
     * Acquire an exclusive lock for the provided lock codename
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

        $unlocked = $this->isUnlocked();
        if ($unlocked) {
            $unlocked = $this->writeDataToLockFile();
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
        $logCode = $this->getLogCode().'_ulk';
        $logData = array('cron job '=>$this->getName(), 'file'=>$this->filename);
        try{
            $try = 1;
            $maxTries = 3;
            do {
                usleep(($try - 1) * 700);
                unlink($this->filename);
                $unlocked = $this->isUnlocked();
            }while ($try++ < $maxTries && !$unlocked);

            $logLevel = ($unlocked ? LogService::LEVEL_INFO : LogService::LEVEL_ERROR);
            $logCode .= ($unlocked ? '' : '_fl');
            $logMessage = 'Releasing of cron job '.$this->getName().($unlocked ? ' was successfully.' : ' failed.');
            $logData['unlocked'] = $unlocked;
        }catch (\Exception $exception) {
            $logLevel = LogService::LEVEL_ERROR;
            $logCode .= '_err';
            $logMessage = 'Error while trying to release cron job '.$this->getName().': '.$exception->getMessage();
            $logData['exception message'] = $exception->getMessage();
            $logData['exception code'] = $exception->getCode();
        }
        $this->_logService->log($logLevel, $logCode, $logMessage, $logData);

        return $unlocked;
    }

    /**
     * Admin unlock to release an exclusive lock. Checks if admin can unlock.
     * @return bool
     */
    public function adminReleaseLock()
    {
        $logCode = $this->getLogCode().'_aulk';

        $name = $this->getName();
        $user = $this->getServiceLocator()->get('zfcuser_auth_service')->getIdentity();

        if (!is_writable($this->lockDirectory)) {
            $this->_logService->log(LogService::LEVEL_ERROR, $logCode.'dir',
                    'Unlock failed on cron job '.$name.'. Directory not writable.',
                    array('cron job'=>$name, 'directory'=>realpath($this->lockDirectory), 'user id'=>$user->getId())
                );
            $success = FALSE;
        }elseif (!$this->canAdminUnlock()) {
            $this->_logService->log(LogService::LEVEL_ERROR, $logCode.'err',
                    'Unlock failed on cron job '.$name.'. Admin cannot unlock yet.',
                    array('name'=>$name, 'locked seconds'=>$this->getAdminLockedSeconds(), 'user id'=>$user->getId())
                );
            $success = FALSE;
        }else{
            $filename = realpath($this->filename);
            $success = $this->releaseLock();

            if ($success) {
                $subject = $logCode;
                $message = 'Successfully unlocked cronjob '.$name.' ('.$filename.'); User '.$user->getId().'.';
            }else{
                $subject = $logCode.'_fl';
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
        if ($this->isUnlocked()) {
            $sinceTimestamp = FALSE;
        }else{
            $sinceTimestamp = $this->getLockFileData('timestamp');
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
     * @return bool $notifyAdmin
     */
    public function notifyAdmin()
    {
        if ($numberOfIntervalsBeforeNotifying = $this->_applicationConfigService->getConfigFirstAdminNotification()) {
            $notifyAfter = $this->lockedSince() + $this->getIntervalSeconds() * $numberOfIntervalsBeforeNotifying;
            $notify = (time() >= $notifyAfter);
        }else{
            $notify = FALSE;
        }

        return $notify;
    }

    /**
     * @return bool $notifyClient
     */
    public function notifyClient()
    {
        if ($numberOfIntervalsBeforeNotifying = $this->_applicationConfigService->getConfigFirstClientNotification()) {
            $notifyAfter = $this->lockedSince() + $this->getIntervalSeconds() * $numberOfIntervalsBeforeNotifying;
            $notify = (time() >= $notifyAfter);
        }else{
            $notify = FALSE;
        }

        return $notify;
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
    public function getLogCode()
    {
        return 'crn_'.substr($this->getName(), 0, 4);
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

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

use Log\Service\LogService;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;


abstract class CronRunnable implements ServiceLocatorAwareInterface
{

    /** @var ServiceLocatorInterface $_serviceLocator */
    protected $_serviceLocator;

    /** @var array $attributes  default values */
    protected $attributes = array(
        'interval'=>1,
        'offset'=>0,
        'lockTime'=>180
    );


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
     * @param array $cronData
     */
    public function __construct(array $cronData)
    {
        foreach ($this->attributes as $code=>$defaultValue) {
            if (isset($cronData[$code]) && is_int($cronData[$code]) && $cronData[$code] > 0) {
                $this->attributes[$code] = $cronData[$code];
            }
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

    /**
     * Performs any scheduled actions.
     */
    abstract public function cronRun();
    
}
<?php

/* 
 * Copyright (c) 2014 Lero9 Limited
 * All Rights Reserved
 * This software is subject to our terms of trade and any applicable licensing agreements.
 */

namespace Application;

/**
 * The base interface for all Magelink Cron Tasks.
 */
interface CronRunnable {
    
    /**
     * Checks whether we should run the cron task this run through.
     * @param int $minutes The time in minutes of this cron run (unix timestamp in minutes)
     * @return boolean
     */
    public function cronCheck($minutes);
    
    /**
     * Performs any scheduled actions.
     */
    public function cronRun();
    
}
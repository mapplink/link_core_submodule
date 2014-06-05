<?php
namespace Magelink\Entity;

/* 
 * Copyright (c) 2014 Lero9 Limited
 * All Rights Reserved
 * This software is subject to our terms of trade and any applicable licensing agreements.
 */

interface DatadInterface {
    
    /**
     * If $key is specified, return a single value from the simple data, loading if necessary.
     * If $key is null, return an array of all simple data.
     * @param $key null|string
     */
    public function getSimpleData($key = null);
    
    /**
     * Set the simple data with the given key to the given value. Ensures that data is loaded first.
     * @param $key string
     * @param $value string
     */
    public function setSimpleData($key, $value);
    
    /**
     * Replace all the simple data with the given array, or merge to the data if $replace=false
     * @param $data array
     * @param $replace boolean If false, merges the the given array into the existing one
     */
    public function setAllSimpleData(array $data, $replace = TRUE);
    
    /**
     * Loads the simple data from the backend store
     * @param $key The primary key in the main table
     */
    public function loadSimpleData($key=null);
    
    /**
     * Saves the simple data to the backend store
     */
    public function saveSimpleData();
    
}
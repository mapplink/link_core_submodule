<?php
/**
 * @package Magelink\Model
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Magelink\Model;


class SimpleModel
{
    
    private $_data = null;
    
    /**
     * If $key is specified, return a single value from the data, if data loaded.
     * If $key is null, return an array of all simple data.
     * @param $key null|string
     * @return mixed|array|null
     */
    public function getData($key = null){
        if($this->_data === null){
            return null;
        }
        if($key === null){
            return $this->_data;
        }else if(isset($this->_data[$key])){
            return $this->_data[$key];
        }else{
            return null;
        }
    }
    
    /**
     * Set the data with the given key to the given value. Ensures that data is loaded first.
     * @param $key string
     * @param $value string
     */
    public function setData($key, $value){
        if($this->_data === null){
            throw new \Exception('Attempting to set data on non-loaded model.');
        }
        $this->_data[$key] = $value;
    }
    
    /**
     * Replace all the data with the given array, or merge to the data if $replace=false
     * @param $data array
     * @param $replace boolean If false, merges the the given array into the existing one
     */
    public function setAllData($data, $replace=true){
        assert(is_array($data));
        if($replace || $this->_data === null){
            $this->_data = $data;
        }else{
            $this->_data = array_merge($this->_data, $data);
        }
    }
    
    /**
     * Loads the data array - not implemented in this parent class.
     */
    public function loadData($options=array()){
        if($this->_data === null){
            $this->_data = array();
        }
        return true;
    }
    
    /**
     * Loads an empty data array
     */
    public function loadEmpty(){
        $this->_data = array();
    }
    
    /**
     * Saves the data array - not implemented in this parent class.
     */
    public function saveData($options=array()){
        return true;
    }
    
    /**
     * Translates a camel case string into a string with
     * underscores (e.g. firstName -> first_name)
     *
     * @param string $str String in camel case format
     * @return string $str Translated into underscore format
     */
    protected function _fromCamelCase($str) {
        $str[0] = strtolower($str[0]);
        $func = create_function('$c', 'return "_" . strtolower($c[1]);');
        return preg_replace_callback('/([A-Z])/', $func, $str);
    }

    /**
     * Translates a string with underscores
     * into camel case (e.g. first_name -> firstName)
     *
     * @param string $str String in underscore format
     * @param bool $capitalise_first_char If true, capitalise the first char in $str
     * @return string $str translated into camel caps
     */
    protected function _toCamelCase($str, $capitalise_first_char = false) {
        if ($capitalise_first_char) {
            $str[0] = strtoupper($str[0]);
        }
        $func = create_function('$c', 'return strtoupper($c[1]);');
        return preg_replace_callback('/_([a-z])/', $func, $str);
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value) {
        if(strpos($name, '_') === false){
            $name = $this->_fromCamelCase($name);
        }
        
        return $this->setData($name, $value);
    }

    /**
     * @param string $name
     * @return array|mixed|null $value
     */
    public function __get($name) {
        if(strpos($name, '_') === false){
            $name = $this->_fromCamelCase($name);
        }
        
        return $this->getData($name);
    }

}
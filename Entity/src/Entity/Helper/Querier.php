<?php
/**
 * Responsible for advanced query services
 * @package Entity\Helper
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Entity\Helper;

use Magelink\Exception\MagelinkException;
use Entity\Entity;


class Querier extends AbstractHelper implements \Zend\ServiceManager\ServiceLocatorAwareInterface {

    /**
     * @var \Entity\Service\EntityConfigService Cache of entity config service
     */
    protected $_entityConfigService = null;

    /**
     * @return \Entity\Service\EntityConfigService
     */
    protected function getEntityConfigService(){
        if($this->_entityConfigService === null){
            $this->_entityConfigService = $this->getServiceLocator()->get('entityConfigService');
        }
        return $this->_entityConfigService;
    }

    /**
     * Execute a MLQL query and return all rows as associative arrays
     * @param string $mlql The MLQL to be executed (see separate MLQL docs)
     * @return array
     */
    public function executeQuery($mlql)
    {
        $sql = $this->parseQuery($mlql);
        $resultArray = $this->executeSqlQuery($sql);

        return $resultArray;
    }

    /**
     * Execute a SQL query and return all rows as associative arrays
     * @param string $mlql The MLQL to be executed (see separate MLQL docs)
     * @return array
     */
    public function executeSqlQuery($sql)
    {
        $result = $this->getAdapter()->query($sql, \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);

        $resultArray = array();
        foreach($result as $row){
            $resultArray[] = (array)$row;
        }

        return $resultArray;
    }

    /**
     * Execute a MLQL query and return the first column of the first row
     * @param string $mlql The MLQL to be executed (see separate MLQL docs)
     * @return mixed|null
     */
    public function executeQueryScalar($mlql){
        $sql = $this->parseQuery($mlql);
        $res = $this->getAdapter()->query($sql, \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);

        foreach($res as $row){
            $row = (array)$row;
            return array_shift($row);
        }
        return null;
    }

    /**
     * @param string $mlql The query string to be parsed.
     * @return string
     *{string:string}
     */
    public function parseQuery($mlql)
    {
        $splitMlql = '#{'
                .'([a-z0-9]+)' // entity
                .':([a-z0-9]+)' // alias
                .':?([a-z0-9,_]*)' // attribute list (ALL)
                .':?([a-zA-Z0-9,<=>!+\-*\/\.\'"_ ()]*)'
                .':?([a-zA-Z0-9,<=>!+\-*\/\.\'"_ ]*)'
            .'}#';
        $mlql = preg_replace_callback($splitMlql, array($this, 'parseQueryTable'), $mlql);
        //$mlql = str_replace(array('{', '}'), '', $mlql);

        return $mlql;
    }

    /**
     * Called by preg_replace_callback from parseQuery to process table matches
     * @param array $matches
     * @return string
     */
    protected function parseQueryTable($matches)
    {
        $entityType = $matches[1];
        $alias = $matches[2];

        $columns = (isset($matches[3]) && strlen($matches[3]) ? explode(',', $matches[3]) : FALSE);
        if(count($columns) == 1 && $columns[0] == 'ALL'){
            $columns = false;
        }

        $where = array();
        if (isset($matches[4])) {
            $whereSplit = explode(',', $matches[4]);
            foreach ($whereSplit as $condition) {
                if (strlen($condition) > 0) {
                    $options = array();
                    $splitWhereConditions = '#^([a-z0-9\._]+)\s([=<>!]+|!?in|!?like)\s([a-zA-Z0-9\._ <=>!+\-*\/()]+)$#';
                    preg_match($splitWhereConditions, $condition, $options);
                    if ($options[2] == '=' && $options[3] == 'NULL') {
                        $options[2] = 'null';
                    }elseif ($options[2] == '!=' && $options[3] == 'NULL') {
                        $options[2] = 'notnull';
                    }
                    $where[$options[1]] = array($options[2], $options[3]);
                }
            }
        }

        $mlqlOptions = array();
        if (isset($matches[5])) {
            $optionSplit = explode(',', $matches[5]);
            foreach ($optionSplit as $condition) {
                if (strlen($condition) > 0) {
                    $options = array();
                    preg_match('#^([a-z0-9_]+)=([a-zA-Z0-9<=>!+\-*\/.\'"]+)$#', $condition, $options);
                    $mlqlOptions[$options[1]] = $options[2];
                }
            }
        }

        return $this->getEntitySubselect($entityType, $alias, $columns, $where, $mlqlOptions);
    }

    /**
     * Generate a JOIN-to-subselect type SQL clause for the given entityType using the given alias
     * @param string $entityType
     * @param string $alias
     * @param array $attributes
     * @return string The generated SQL clause
     */
    protected function getEntitySubselect($entityType, $alias, $attributes=false, $where=array(), $options=array()){

        $entityType = $this->getEntityConfigService()->parseEntityType($entityType);

        if(array_key_exists('no_select', $options) && $options['no_select']){
            $cols = array();
        }else{
            $cols = array('e.entity_id, e.parent_id, e.unique_id, e.store_id, e.updated_at');
        }
        if(array_key_exists('select', $options)){
            $cols[] = $options['select'];
        }
        $joins = array();
        if($attributes === false){
            $attributes = $this->getEntityConfigService()->getAttributesCode($entityType);
        }

        if(array_key_exists('join', $options)){
            foreach($attributes as $k){
                if(strlen($k) == 0){
                    continue;
                }
                $k = strtolower($k);
                $id = $this->getEntityConfigService()->parseAttribute($k, $entityType);
                if(!$id){
                    throw new MagelinkException('Invalid attribute ' . $k);
                }
                $options['join'] = str_replace($k, 'att_'.$id.'.value', $options['join']);
            }
            $joins[] = $options['join'];
        }

        $whereOut = array();
        $whereType = (array_key_exists('where_type', $options) ? $options['where_type'] : 'AND');

        foreach($attributes as $k){
            if(strlen($k) == 0){
                continue;
            }
            $k = strtolower($k);
            $id = $this->getEntityConfigService()->parseAttribute($k, $entityType);
            if(!$id){
                throw new MagelinkException('Invalid attribute ' . $k);
            }
            $att = $this->getEntityConfigService()->getAttribute($id);

            $joinType = 'LEFT JOIN';
            $extraCond = '';
            if(array_key_exists($k, $where) && !array_key_exists('where_type', $options)){
                // Apply WHEREs to JOINs where possible
                $val = $where[$k][1];
                if(!in_array($val, $attributes)){
                    $attWhere = $this->generateFieldCriteria('att_'.$id.'.value', $val, $where[$k][0], false, ($val != 'NULL') && !preg_match('#^\((.*)\)$#', $val));
                    if(in_array($where[$k][0], array('=', 'in', 'like')) && $where[$k][1] != null){
                        $joinType = 'INNER JOIN';
                    }
                    $extraCond = ' '.$whereType.' ' . $attWhere;
                    $whereOut[] = $attWhere;
                    unset($where[$k]);
                }
            }
            $joins[] = $joinType . ' entity_value_' . $att['type'] . ' AS att_'.$id . ' ON att_'.$id.'.entity_id = e.entity_id AND att_'.$id.'.attribute_id = ' . $this->escape($id) . $extraCond;
            if($att['type'] == 'multi'){
                $cols[] = 'att_'.$id.'.`key` AS '.$k.'_key';
            }
            $cols[] = 'att_'.$id.'.value AS '.$k;
        }

        // Additional WHERE conditions are from Entity (or maybe direct external)
        foreach($where as $att=>$arr){
            $val = $arr[1];
            $escape = ($val != 'NULL') && !preg_match('#^\((.*)\)$#', $val);
            if(in_array($val, $attributes)){
                $vid = $this->getEntityConfigService()->parseAttribute($val, $entityType);
                if(!$vid){
                    throw new MagelinkException('Invalid attribute ' . $k);
                }
                $val = 'att_'.$vid.'.value';
                $escape = false;
            }else if(strpos($val, '.') !== false){
                $escape = false;
            }
            if(in_array($att, $attributes)){
                $aid = $this->getEntityConfigService()->parseAttribute($att, $entityType);
                if(!$aid){
                    throw new MagelinkException('Invalid attribute ' . $k);
                }
                $att = 'att_'.$aid.'.value';
            }else if(strpos($att, '.') === false){
                $att = 'e.'.$att;
            }
            $whereOut[] = $this->generateFieldCriteria($att, $val, $arr[0], false, $escape);
        }
        if(array_key_exists('where', $options)){
            foreach($attributes as $k){
                if(strlen($k) == 0){
                    continue;
                }
                $k = strtolower($k);
                $id = $this->getEntityConfigService()->parseAttribute($k, $entityType);
                $options['where'] = str_replace($k, 'att_'.$id.'.value', $options['where']);
            }
            $whereOut[] = $options['where'];
        }

        $sql = '';
        if(!array_key_exists('no_wrap', $options) || !$options['no_wrap']){
            $sql .= '(';
        }
        $sql .= 'SELECT '.PHP_EOL;
        $sql .= implode(', ', $cols).PHP_EOL;
        $sql .= 'FROM entity AS e '.PHP_EOL;
        $sql .= implode(' '.PHP_EOL, $joins).PHP_EOL;
        $sql .= 'WHERE e.type_id = ' . $this->escape($entityType).PHP_EOL;
        if(count($whereOut)){
            if($whereType != 'AND'){
                $sql .= 'AND (' . implode(' '.$whereType.' ', $whereOut) . ')'.PHP_EOL;
            }else{
                $sql .= 'AND ' . implode(' AND ', $whereOut).PHP_EOL;
            }
        }
        if(!array_key_exists('no_wrap', $options) || !$options['no_wrap']){
            $sql .= ') AS ' . $alias;
        }

        return $sql;
    }
    

}
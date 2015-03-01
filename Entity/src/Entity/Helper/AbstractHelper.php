<?php
/**
 * Class AbstractHelper provides standard functionality for use by the various Entity helpers (Saver/Loader/Querier)
 * @category Entity
 * @package Entity\Helper
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Entity\Helper;

use Magelink\Exception\MagelinkException;
use Magelink\Exception\NodeException;
use Zend\Db\RowGateway\RowGateway;
use Zend\Db\TableGateway\TableGateway;


abstract class AbstractHelper implements \Zend\ServiceManager\ServiceLocatorAwareInterface
{

    const MYSQL_ER_LOCK_DEADLOCK = '40001';

    protected static $_transactionStack = array();
    protected $_attributeCache = array();
    protected $_attributeCodeCache = array();

    /** @var \Zend\ServiceManager\ServiceLocatorInterface The service locator */
    protected $_serviceLocator = null;



    protected static function isRestartTransaction(\Exception $exception)
    {
        $restart = ($exception->getCode() == self::MYSQL_ER_LOCK_DEADLOCK
            || strpos($exception->getMessage(), 'try restarting transaction'));

        return $restart;
    }

    /**
     * Starts a transaction with the given ID
     * @param string $id
     */
    public function beginTransaction($id)
    {
        self::$_transactionStack[] = $id;
        if(count(self::$_transactionStack) == 1){
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA,
                    'trans_begin_actual',
                    'beginTransaction - actual - '.$id,
                    array('id'=>$id, 'stack'=>self::$_transactionStack)
                );
            // This is our only transaction, so start one in MySQL.
            $adapter = $this->getAdapter();
            $adapter->getDriver()->getConnection()->beginTransaction();
        }else{
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA,
                    'trans_begin_fake',
                    'beginTransaction - fake - '.$id, array('id'=>$id, 'stack'=>self::$_transactionStack)
                );
        }
    }

    /**
     * Rollback a transaction with the given ID (and by that, the whole stack).
     * @param string $id
     * @throws MagelinkException If an invalid transaction ID is passed
     */
    public function rollbackTransaction($id)
    {
        if(!in_array($id, self::$_transactionStack)){
            $this->rollbackTransactionInternal();
            throw new MagelinkException('Invalid transaction to roll back - ' . $id);
        }
        $top = array_pop(self::$_transactionStack);
        if($top != $id){
            $this->rollbackTransactionInternal();
            throw new MagelinkException('Transaction not at top of stack (top was ' . $top . ', we were ' . $id . ')!');
        }
        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA, 'trans_rollback', 'rollbackTransaction - ' . $id, array('id'=>$id, 'stack'=>self::$_transactionStack));

        $this->rollbackTransactionInternal();
    }

    /**
     * Commit a transaction with the given ID (only forced to DB if at bottom of stack)
     * @param string $id
     * @throws MagelinkException If an invalid transaction ID is passed
     */
    public function commitTransaction($id)
    {
        if(!in_array($id, self::$_transactionStack)){
            throw new MagelinkException('Invalid transaction to commit - ' . $id);
        }
        $top = array_pop(self::$_transactionStack);
        if ($top != $id) {
            throw new MagelinkException('Transaction not at top of stack (top was ' . $top . ', we were ' . $id . ')!');
        }
        if (count(self::$_transactionStack) == 0) {
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA,
                    'trans_commit_actual',
                    'commitTransaction - actual - '.$id,
                    array('id'=>$id, 'stack'=>self::$_transactionStack)
                );
            // End of stack, commit
            $adapter = $this->getAdapter();
            $adapter->getDriver()->getConnection()->commit();
        }else{
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA,
                    'trans_commit_fake',
                    'commitTransaction - fake - '.$id,
                    array('id'=>$id, 'stack'=>self::$_transactionStack)
                );
        }
    }

    /**
     * Internal function to rollback a transaction, used in error cases of above functions.
     */
    protected function rollbackTransactionInternal()
    {
        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA,
                'trans_rollback_int',
                'rollbackTransactionInternal',
                array('stack'=>self::$_transactionStack)
            );
        $adapter = $this->getAdapter();
        $adapter->getDriver()->getConnection()->rollback();
    }
    
    /**
     * Escape a value for use in raw SQL
     * 
     * @param mixed $value Should be a scalar value or something that will automatically convert to a string.
     * @return string
     */
    protected function escape($value)
    {
        // ToDo (maybe): Remove if the source is found
        if (is_array($value)) {
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_ERROR,
                    'escape_array',
                    'Tries to escape an array',
                    array(
                        'debug backtrace'=>debug_backtrace(),
                        'value'=>$value
                    )
                );
        }

        try{
            $quotedValue = $this->getAdapter()->platform->quoteValue($value);
        }catch (\Exception $exception) {
            // ToDo (maybe): Remove if the source is found
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_ERROR,
                    'escape_array',
                    'Tried to escape an array',
                    array(
                        'exception message'=>$exception->getMessage(),

                        'debug backtrace'=>debug_backtrace(),
                        'exception message'=>$exception->getMessage(),
                        'value'=>$value,
                        'quoted value'=>isset($quotedValue) ? $quotedValue : 'NULL'
                    ),
                    array('exception object'=>$exception)
                );
            // ToDo: Remove temporary fallback, till the real issue is found
            if (is_array($value)) {
                $value = array_shift($value);
                $quotedValue = $this->escape($value);
            }

        }
        return $quotedValue;
    }

    /**
     * Escape values of a column value array for usage in raw SQL
     * @param array $columnValueArray
     * @return array
     */
    public function getEscapedColumnValueArray(array $columnValueArray)
    {
        $escapedColumnValueArray = array();
        foreach ($columnValueArray as $column=>$value) {
            try{
                $escapedColumn = $this->getAdapter()->getPlatform()->quoteIdentifier($column);
                $escapedValue = $this->escape($value);
                if (strlen($escapedColumn) && strlen(strval($escapedValue))) {
                    $escapedColumnValueArray[$escapedColumn] = $escapedValue;
                }
            }catch (\Exception $exception) {
                $this->getServiceLocator()->get('logService')
                    ->log(\Log\Service\LogService::LEVEL_ERROR,
                        'escape_error',
                        'Exception during the column/value escaping',
                        array(
                            'exception message'=>$exception->getMessage(),
                            'columnValueArray'=>$columnValueArray,
                            'column'=>$column,
                            'value'=>$value,
                            'escapedColumnValueArray'=>$escapedColumnValueArray,
                            'escaped column'=>$escapedColumn,
                            'value'=>$escapedValue
                        ),
                        array('exception object'=>$exception)
                    );
                // ToDo: Remove temporary fallback, till the real issue is found
                if ($escapedColumn && is_array($value)) {
                    $value = array_shift($value);
                    $escapedValue = $this->escape($value);
                    if (strlen($escapedColumn) && strlen(strval($escapedValue))) {
                        $escapedColumnValueArray[$escapedColumn] = $escapedValue;
                    }
                }
            }
        }

        return $escapedColumnValueArray;
    }
    
    /**
     * Get attribute data array, loading if necessary
     * @param int|string $code Numeric ID or code of attribute
     * @param int $entity_type_id
     * @return array|false
     * @throws \Magelink\Exception\MagelinkException
     */
    public function getAttribute($code, $entity_type_id)
    {
        if(!isset($this->_attributeCache[$entity_type_id])){
            $this->_attributeCache[$entity_type_id] = array();
        }
        if(!isset($this->_attributeCodeCache[$entity_type_id])){
            $this->_attributeCodeCache[$entity_type_id] = array();
        }
        $result = false;
        if(is_string($code)){
            if(isset($this->_attributeCodeCache[$entity_type_id][$code])){
                return $this->_attributeCodeCache[$entity_type_id][$code];
            }else{
                $result = $this->loadAttribute($code, 'code', $entity_type_id);
                $this->_attributeCodeCache[$entity_type_id][$code] = $result;
                return $result;
            }
        }else if(!is_int($code)){
            throw new NodeException('Invalid attribute name/ID');
        }
        if(!$code){
            throw new NodeException('Invalid code ' . $code);
        }
        if(isset($this->_attributeCache[$entity_type_id][$code])){
            return $this->_attributeCache[$entity_type_id][$code];
        }
        $result = $this->loadAttribute($code, 'attribute_id', $entity_type_id);
        $this->_attributeCache[$entity_type_id][$code] = $result;
        return $result;
    }

    /**
     * Internal function to retrieve attribute caches for debugging
     * @return array
     */
    public function getAttributeDebuggingData()
    {
        return array($this->_attributeCache, $this->_attributeCodeCache);
    }
    
    /**
     * Load an attribute from DB storage
     * @param int|string $id
     * @param string $field Which field to search by
     * @param int $entity_type
     * @return array
     */
    protected function loadAttribute($id, $field, $entity_type = null){
        $rs = $this->getTableGateway('entity_attribute')->select(array($field=>$id, 'entity_type_id'=>$entity_type));
        foreach($rs as $row){
            return $row;
        }
        return null;
    }

    /**
     * Create a WHERE clause for a given field using the locate-style search types.
     * @param string $field The field name to check (fully qualified with table name)
     * @param string|array $value The value to compare against - will be escaped
     * @param string $searchType The search type, see EntityService->locateEntity
     * @param boolean $inverse Whether to do the opposite of what searchType suggests (i.e. != instead of =)
     * @param boolean $escape Whether to enable escaping of the value
     * @return string SQL criteria
     * @throws MagelinkException
     */
    protected function generateFieldCriteria($field, $value, $searchType, $inverse=false, $escape=true)
    {
        if(is_null($value)){
            switch($searchType){
                case 'neq':
                case 'not_eq':
                case '!=':
                case 'not_in':
                case '!in':
                case '!like':
                case 'gt':
                case '>':
                case 'all_gt':
                case 'gteq':
                case '>=':
                case 'all_gteq':
                    $searchType = 'notnull';
                    break;
                case 'eq':
                case 'all_eq':
                case '=':
                case 'in':
                case 'all_in':
                case 'like':
                    $searchType = 'null';
                    break;
                case 'lt':
                case 'all_lt':
                case '<':
                case 'lteq':
                case 'all_lteg':
                case '<=':
                    $searchType = 'impossible';
                    break;
            }
        }else if(is_scalar($value) && $escape){
            $value = $this->escape($value);
        }

        switch($searchType){
            case 'impossible':
                return '1 != 1';
            case 'notnull':
                $inverse = !$inverse;
            case 'null':
                if($inverse){
                    return $field . ' IS NOT NULL';
                }
                return $field . ' IS NULL';
            case 'neq':
            case 'not_eq':
            case '!=':
                $inverse = !$inverse;
            case 'all_eq':
            case 'eq':
            case '=':
                if($inverse){
                    return $field . ' != ' . $value;
                }
                return $field . ' = ' . $value;
            case 'not_in':
            case '!in':
                $inverse = !$inverse;
            case 'all_in':
            case 'in':
                if($value instanceof \Zend\Db\Sql\Expression){
                    return $field . ' ' . ($inverse ? 'NOT IN' : 'IN') . ' (' . $value->getExpression() . ')';
                }
                if(!is_array($value)){
                    $value = array($value);
                }else if(!count($value)){
                    return '0 = 1';
                }
                if(is_array($value)){
                    foreach($value as $k=>&$v){
                        if(is_null($v)){
                            $v = 'NULL';
                        }else{
                            $v = $this->escape($v);
                        }
                    }
                }
                return $field . ' ' . ($inverse ? 'NOT IN' : 'IN') . ' (' . implode(', ', $value) . ')';
            case 'all_gt':
            case 'gt':
            case '>':
                if($inverse){
                    return $field . ' <= ' . $value;
                }
                return $field . ' > ' . $value;
            case 'all_gteq':
            case 'gteq':
            case '>=':
                if($inverse){
                    return $field . ' < ' . $value;
                }
                return $field . ' >= ' . $value;

            case 'all_lt':
            case 'lt':
            case '<':
                if($inverse){
                    return $field . ' >= ' . $value;
                }
                return $field . ' < ' . $value;
            case 'all_lteq':
            case 'lteq':
            case '<=':
                if($inverse){
                    return $field . ' > ' . $value;
                }
                return $field . ' <= ' . $value;

            case '!like':
                $inverse = !$inverse;
            case 'like':
                if($inverse){
                    return $field . ' NOT LIKE ' . $value;
                }
                return $field . ' LIKE ' . $value;
            default:
                throw new NodeException('Unknown search type: `'.$searchType.'`');
        }
    }
    
    /**
     * Return the database adapter to be used to communicate with Entity storage.
     * @return \Zend\Db\Adapter\Adapter
     */
    protected function getAdapter()
    {
        return $this->getServiceLocator()->get('zend_db');
    }
    
    /**
     * Returns a new TableGateway instance for the requested table
     * @param string $table
     * @return \Zend\Db\TableGateway\TableGateway
     */
    protected function getTableGateway($table)
    {
        return new TableGateway($table, \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter());
    }
    
    /**
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->_serviceLocator;
    }

    /**
     * @param \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(\Zend\ServiceManager\ServiceLocatorInterface $serviceLocator)
    {
        $this->_serviceLocator = $serviceLocator;
    }
}
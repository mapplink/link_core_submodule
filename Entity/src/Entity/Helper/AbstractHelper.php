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

use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Magelink\Exception\NodeException;
use Zend\Db\RowGateway\RowGateway;
use Zend\Db\TableGateway\TableGateway;


abstract class AbstractHelper implements \Zend\ServiceManager\ServiceLocatorAwareInterface
{

    const MYSQL_ER_LOCK_DEADLOCK = '40001';

    /** @var array $_transactionStack */
    protected static $_transactionStack = array();
    /** @var bool|NULL $_inRollback  */
    protected static $_inRollback = NULL;

    /** @var array $_attributeCache */
    protected $_attributeCache = array();
    /** @var array $_attributeCodeCache */
    protected $_attributeCodeCache = array();

    /** @var \Zend\ServiceManager\ServiceLocatorInterface The service locator */
    protected $_serviceLocator = NULL;


    /**
     * Static method which check if the transaction should be retried
     * @param \Exception $exception
     * @return bool
     */
    protected static function isRestartTransaction(\Exception $exception)
    {
        $restart = ($exception->getCode() == self::MYSQL_ER_LOCK_DEADLOCK
            || strpos($exception->getMessage(), 'try restarting transaction'));

        return $restart;
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
     * Starts a transaction with the given ID
     * @param string $id
     */
    public function beginTransaction($id)
    {
        self::$_transactionStack[] = $id;
        $logData = array('id'=>$id, 'stack'=>self::$_transactionStack);

        if (count(self::$_transactionStack) == 1) {
            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_DEBUGEXTRA, 'tract_begin_real', 'Begin real transaction '.$id, $logData);
            // This is our only transaction, so start one in MySQL.
            $adapter = $this->getAdapter();
            $adapter->getDriver()->getConnection()->beginTransaction();
        }else{
            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_DEBUGEXTRA, 'tract_begin_fake', 'Begin fake transaction '.$id, $logData);
        }
    }

    /**
     * Commit a transaction with the given ID (only forced to DB if at bottom of stack)
     * @param string $id
     * @return bool $commited
     * @throws MagelinkException If an invalid transaction ID is passed
     */
    public function commitTransaction($id)
    {
        $commited = FALSE;

        $logCode = 'tract_cmmit';
        $logData = array('id'=>$id, 'stack'=>self::$_transactionStack);

        if (!in_array($id, self::$_transactionStack)) {
            throw new MagelinkException('Invalid transaction to commit - '.$id);
        }else {
            $top = array_pop(self::$_transactionStack);
            if ($top != $id) {
                throw new MagelinkException('Transaction not at top of stack (top was '.$top.', we were '.$id.')!');
/*            }elseif (self::$_inRollback) {
                $this->getServiceLocator()->get('logService')
                    ->log(LogService::LEVEL_WARN,
                        'tract_rback_cmit',
                        'Commit '.$id.' could not be executed. Rollback was already triggered.',
                        array('id'=>$id, 'stack'=>self::$_transactionStack));
                throw new MagelinkException('Commit '.$id.' could not be executed. Rollback was already triggered.');
*/            }else{
                if (count(self::$_transactionStack) == 0) {
                    $logCode .= '_real';
                    $logMessage = 'Commited real transaction '.$id;

                    $adapter = $this->getAdapter();
                    $adapter->getDriver()->getConnection()->commit();
                }else{
                    $logCode .= '_fake';
                    $logMessage = 'Commited fake transaction '.$id;
                }

                $this->getServiceLocator()->get('logService')
                    ->log(LogService::LEVEL_DEBUGEXTRA, $logCode, $logMessage, $logData);
                $commited = TRUE;
            }
        }

        return $commited;
    }

    /**
     * Internal function to rollback a transaction, used in error cases of above functions.
     * @param $id
     * @return bool $rolledBack
     */
    protected function rollBackRealTransaction($id)
    {
//        if (self::$_inRollback !== TRUE) {
            $adapter = $this->getAdapter();
            $adapter->getDriver()->getConnection()->rollback();
            $rolledBackReal = TRUE;
/*        }else{
            $rolledBackReal = FALSE;
        }
*/
        if (count(self::$_transactionStack) == 0) {
            self::$_inRollback = FALSE;
            $logLevel = LogService::LEVEL_DEBUGEXTRA;
            $logCode = 'tract_rback_real';
            $logMessage = 'Rollback real transaction '.$id;
        }else{
            self::$_inRollback = TRUE;
            $logLevel = LogService::LEVEL_DEBUG;
            $logCode = 'tract_rback_fake';
            $logMessage = 'Rollback fake transaction '.$id;
        }

        $this->getServiceLocator()->get('logService')
            ->log($logLevel, $logCode, $logMessage.' (',
                array('id'=>$id, 'stack'=>self::$_transactionStack, 'rolledBackReal'=>$rolledBackReal));

        return $rolledBackReal;
    }

    /**
     * Rollback a transaction with the given ID (and by that, the whole stack).
     * @param string $id
     * @throws MagelinkException If an invalid transaction ID is passed
     */
    public function rollbackTransaction($id)
    {
        if (!in_array($id, self::$_transactionStack)) {
//            $this->rollBackRealTransaction($id);
            throw new MagelinkException('Invalid transaction to roll back: '.$id.'. No rollback done.');
            $rolledBack = FALSE;
        }else{
            $rolledBack = TRUE;
            $top = array_pop(self::$_transactionStack);
            if ($top != $id) {
                $this->rollBackRealTransaction($id);
//                throw new MagelinkException('Transaction not at top of stack (top was '.$top.', we were '.$id.')!');
            }else {
                $this->rollBackRealTransaction($id);
            }
        }

        return $rolledBack;
    }

    /**
     * Escape a value for use in raw SQL
     * @param mixed $value  Should be a scalar value or something that will automatically convert to a string.
     * @param bool $recursive
     * @return string $quotedValue
     */
    protected function escape($value, $recursive = FALSE)
    {
        try{
            $quotedValue = $this->getAdapter()->platform->quoteValue($value);
        }catch(\Exception $exception) {
            // ToDo: Remove temporary fallback, till the real issue is found
            if (is_array($value)) {
                $value = array_shift($value);
                $quotedValue = $this->escape($value, true);
            }

            if (!$recursive) {
                $this->getServiceLocator()->get('logService')
                    ->log(LogService::LEVEL_ERROR, 'escape_fail', $exception->getMessage(),
                    array(
                        'value'=>$value,
                        'quoted value'=>isset($quotedValue) ? $quotedValue : 'NULL',
                        'debug backtrace'=>debug_backtrace()
                    ),
                    array('exception object'=>$exception)
                );
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
                    ->log(LogService::LEVEL_ERROR,
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
     * @param int $entityTypeId
     * @return array|false
     * @throws \Magelink\Exception\MagelinkException
     */
    public function getAttribute($code, $entityTypeId)
    {
        if (!isset($this->_attributeCache[$entityTypeId])) {
            $this->_attributeCache[$entityTypeId] = array();
        }

        if (!isset($this->_attributeCodeCache[$entityTypeId])) {
            $this->_attributeCodeCache[$entityTypeId] = array();
        }

        $result = FALSE;
        if (is_string($code)) {
            if (isset($this->_attributeCodeCache[$entityTypeId][$code])) {
                return $this->_attributeCodeCache[$entityTypeId][$code];
            }else{
                $result = $this->loadAttribute($code, 'code', $entityTypeId);
                $this->_attributeCodeCache[$entityTypeId][$code] = $result;

                return $result;
            }
        }elseif (!is_int($code)) {
            throw new NodeException('Invalid attribute name/ID');
        }

        if (!$code) {
            throw new NodeException('Invalid code '.$code);
        }

        if (isset($this->_attributeCache[$entityTypeId][$code])) {
            return $this->_attributeCache[$entityTypeId][$code];
        }

        $result = $this->loadAttribute($code, 'attribute_id', $entityTypeId);
        $this->_attributeCache[$entityTypeId][$code] = $result;

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
     * @param int $entityType
     * @return array
     */
    protected function loadAttribute($id, $field, $entityType = NULL)
    {
        $rs = $this->getTableGateway('entity_attribute')->select(array($field=>$id, 'entity_type_id'=>$entityType));
        foreach ($rs as $row) {
            return $row;
        }
        return NULL;
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
        if (is_null($value)) {
            switch($searchType) {
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
        }else if (is_scalar($value) && $escape) {
            $value = $this->escape($value);
        }

        switch($searchType) {
            case 'impossible':
                return '1 != 1';
            case 'notnull':
                $inverse = !$inverse;
            case 'null':
                if ($inverse) {
                    return $field.' IS NOT NULL';
                }
                return $field.' IS NULL';
            case 'neq':
            case 'not_eq':
            case '!=':
                $inverse = !$inverse;
            case 'all_eq':
            case 'eq':
            case '=':
                if ($inverse) {
                    return $field.' != '.$value;
                }
                return $field.' = '.$value;
            case 'not_in':
            case '!in':
                $inverse = !$inverse;
            case 'all_in':
            case 'in':
                if ($value instanceof \Zend\Db\Sql\Expression) {
                    return $field.' '.($inverse ? 'NOT IN' : 'IN').' ('.$value->getExpression().')';
                }
                if (!is_array($value)) {
                    $value = array($value);
                }else if (!count($value)) {
                    return '0 = 1';
                }
                if (is_array($value)) {
                    foreach ($value as $k=>&$v) {
                        if (is_null($v)) {
                            $v = 'NULL';
                        }else{
                            $v = $this->escape($v);
                        }
                    }
                }
                return $field.' '.($inverse ? 'NOT IN' : 'IN').' ('.implode(', ', $value).')';
            case 'all_gt':
            case 'gt':
            case '>':
                if ($inverse) {
                    return $field.' <= '.$value;
                }
                return $field.' > '.$value;
            case 'all_gteq':
            case 'gteq':
            case '>=':
                if ($inverse) {
                    return $field.' < '.$value;
                }
                return $field.' >= '.$value;

            case 'all_lt':
            case 'lt':
            case '<':
                if ($inverse) {
                    return $field.' >= '.$value;
                }
                return $field.' < '.$value;
            case 'all_lteq':
            case 'lteq':
            case '<=':
                if ($inverse) {
                    return $field.' > '.$value;
                }
                return $field.' <= '.$value;

            case '!like':
                $inverse = !$inverse;
            case 'like':
                if ($inverse) {
                    return $field.' NOT LIKE '.$value;
                }
                return $field.' LIKE '.$value;
            default:
                throw new NodeException('Unknown search type: `'.$searchType.'`');
        }
    }

}

<?php
/**
 * Responsible for updating entities in the database
 * @category Entity
 * @package Entity\Helper
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Entity\Helper;

use Entity\Entity;
use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Magelink\Exception\NodeException;
use Zend\Db\Adapter\Adapter;


class Saver extends AbstractHelper implements \Zend\ServiceManager\ServiceLocatorAwareInterface
{

    /**
     * Deletes an Entity from the system along with any attached data (comments, identifiers, etc).
     * @param Entity $entity
     * @throws \Exception Passes on any exceptions thrown while executing SQL.
     */
    public function deleteEntity(Entity $entity)
    {
        $this->beginTransaction('delete-'.$entity->getId());
        // @todo: Implement proper Zend Framework functionality
        $sql = array(
            'DELETE FROM entity_value_datetime WHERE entity_id = :eid',
            'DELETE FROM entity_value_decimal WHERE entity_id = :eid',
            'DELETE FROM entity_value_fkey WHERE entity_id = :eid',
            'DELETE FROM entity_value_int WHERE entity_id = :eid',
            'DELETE FROM entity_value_multi WHERE entity_id = :eid',
            'DELETE FROM entity_value_text WHERE entity_id = :eid',
            'DELETE FROM entity_value_varchar WHERE entity_id = :eid',
            'DELETE FROM entity_value_entity WHERE entity_id = :eid OR value = :eid',
            'DELETE FROM entity_identifier WHERE entity_id = :eid',
            'DELETE FROM entity_comment WHERE entity_id = :eid',
            'UPDATE entity_action_status SET status = 1 WHERE action_id IN (SELECT id FROM entity_action WHERE entity_id = :eid)',
            'UPDATE entity_update SET complete = 1 WHERE entity_id = :eid',
            'DELETE FROM entity WHERE entity_id = :eid',
        );

        try{
            foreach ($sql as $s) {
                $this->getAdapter()->query(str_replace(':eid', $entity->getId(), $s), Adapter::QUERY_MODE_EXECUTE);
            }
            $this->commitTransaction('delete-'.$entity->getId());
        }catch (\Exception $exception) {
            $this->rollbackTransaction('delete-'.$entity->getId());
            throw $exception;
        }
    }

    /**
     * Update just an entities unique ID, without touching any other data or the updated_at
     * @param int $entityId
     * @param string $uniqueId
     */
    public function setEntityUnique($entityId, $uniqueId)
    {
        // @todo: Implement proper Zend Framework functionality
        $sql = "UPDATE entity AS e SET e.unique_id = ".$this->escape($uniqueId)
            ." WHERE e.entity_id = ".$this->escape($entityId).";";
        $this->getAdapter()->query($sql, Adapter::QUERY_MODE_EXECUTE);
    }

    /**
     * Update just an entities parent, without touching any other data or the updated_at
     * @param $childId
     * @param $parentId
     */
    public function setEntityParent($childId, $parentId)
    {
        // @todo: Implement proper Zend Framework functionality
        $sql = "UPDATE entity AS e SET e.parent_id = ".$this->escape($parentId)
            ." WHERE e.entity_id = ".$this->escape($childId).";";
        $this->getAdapter()->query($sql, Adapter::QUERY_MODE_EXECUTE);
    }

    /**
     * Updates the updated_at timestamp on an entity and optionally some attributes
     * @param Entity $entity
     * @param string[] $attributeCodes
     * @throws \Exception
     */
    public function touchEntity(Entity $entity, array $attributeCodes = array())
    {
        $timestamp = date('Y-m-d H:i:s');
        // @todo: Implement proper Zend Framework functionality
        $sqls = array(
            "UPDATE entity AS e SET e.updated_at = ".$this->escape($timestamp)
                ." WHERE e.entity_id = ".$this->escape($entity->getId()).";"
        );

        foreach ($attributeCodes as $attributeCode) {
            $attData = $this->getAttribute($attributeCode, $entity->getType());
            // @todo: Implement proper Zend Framework functionality
            $sqls[] = "UPDATE entity_value_".$attData['type']." AS ev SET ev.updated_at = ".$this->escape($timestamp)
                ." WHERE ev.entity_id = ".$this->escape($entity->getId())
                    ." AND ev.attribute_id = ".$this->escape($attData['attribute_id']);
        }

        $adapter = $this->getAdapter();
        $transactionId = 'touch-'.$entity->getId();
        $this->beginTransaction($transactionId);
        try {
            foreach ($sqls as $sql) {
                $this->getServiceLocator()->get('logService')
                    ->log(LogService::LEVEL_DEBUGEXTRA,
                        'sav_touch_sql',
                        'touchEntity - '.$entity->getId().' SQL: '.$sql,
                        array('sql'=>$sql),
                        array('entity'=>$entity)
                    );

                $response = $adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
                if (!$response) {
                    throw new MagelinkException('Unknown error executing attribute update query: '.$sql);
                }
            }

            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_DEBUGEXTRA,
                    'sav_touch_commit',
                    'touchEntity - '.$entity->getId().' committed, '.count($sql).' queries ran',
                    array('sql'=>$sql),
                    array('entity'=>$entity)
                );
            $this->commitTransaction($transactionId);
        }catch (\Exception $exception) {
            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_DEBUGEXTRA,
                    'sav_touch_err',
                    'touchEntity - '.$entity->getId().' - Exception in processing, rolling back',
                    array('message'=>$exception->getMessage()),
                    array('entity'=>$entity, 'exception'=>$exception)
                );
            $this->rollbackTransaction($transactionId);
            throw $exception;
        }
    }

    /**
     * Create new entity in the database. Does not update any logging or distribution tables, or cause any routing.
     *
     * @param int $entityTypeId
     * @param int $storeId
     * @param string $uniqueId
     * @param int $parentId
     * @param array $data
     * @throws MagelinkException
     * @return int The entity ID of the newly created entity
     */
    public function createEntity($entityTypeId, $storeId, $uniqueId, $parentId, array $data)
    {
        $dataArray = array(
            'type_id'=>$entityTypeId,
            'store_id'=>$storeId,
            'unique_id'=>$uniqueId,
            'parent_id'=>($parentId ? $parentId : NULL),
            'updated_at'=>date('Y-m-d H:i:s')
       );

        $affectedRows = $this->getTableGateway('entity')->insert($dataArray);
        $entityId = $this->getAdapter()->getDriver()->getLastGeneratedValue();
        //$entityId = $this->getTableGateway('entity')->getLastInsertValue();
        $dataArray['entity_id'] = intval($entityId);

        if (!$affectedRows || !$entityId) {
            throw new MagelinkException('Unknown error inserting entity record');
            $entityId = NULL;
        }else {
            foreach ($data as $code=>$value) {
                if (is_object($value) && $value instanceof Entity) {
                    if (!$value->getId()) {
                        throw new NodeException('Invalid ID for Entity-type value');
                    }else{
                        $data[$code] = $value->getId();
                    }
                }elseif (is_null($value)) {
                    unset($data[$code]);
                }
            }

            $attributes = array();
            $attributeCodes = array_keys($data);

            foreach ($attributeCodes as $code) {
                $attributes[$code] = $this->getAttribute($code, $entityTypeId);
            }

            $entity = new Entity($dataArray, $attributes, 0);
            $this->updateData($entity, $data, array(), array(), $attributeCodes, array(), $attributes);

            $entityId = $dataArray['entity_id'];
        }

        return $entityId;
    }

    /**
     * Save new entity data into the database. Does not update any logging or distribution tables (i.e. entity_update)
     *
     * @param Entity $entity The Entity to update.
     * @param array $updatedData
     * @param array $merge
     * @param bool $forcedUpdate
     * @return string[] A list of all attribute codes that were updated
     */
    public function saveEntity(Entity $entity, array $updatedData, $merge = array(), $forcedUpdate = FALSE)
    {
        $attributesToUpdate = array();
        $attributesToMerge = array();
        $attributesToCreate = array();
        $attributesToDelete = array();

        foreach ($updatedData as $code=>$newValue) {
            $oldValue = $entity->getData($code);
            if (is_object($newValue) && $newValue instanceof Entity) {
                if (!$newValue->getId()) {
                    throw new NodeException('Invalid ID for Entity-type value');
                    $newValue = $oldValue;
                }else{
                    $newValue = $newValue->getId();
                }
            }

            if (is_null($oldValue) && !is_null($newValue)) {
                $attributesToCreate[] = $code;
            }elseif (!is_null($oldValue) && is_null($newValue)) {
                $attributesToDelete[] = $code;
            }elseif (!is_null($oldValue) && !is_null($newValue)) {
                settype($newValue, gettype($oldValue));

                if ($oldValue !== $newValue || $forcedUpdate) {
                    if (is_array($oldValue) && $merge === TRUE) {
                        $attributesToMerge[] = $code;
                    }elseif (is_array($merge)) {
                        if (isset($merge[$code]) && $merge[$code] === TRUE) {
                            $attributesToMerge[] = $code;
                        }else {
                            $attributesToUpdate[] = $code;
                        }
                    }else {
                        $attributesToUpdate[] = $code;
                    }
                }
            }
        }

        $attributes = array();
        $attributeCodes = array_merge($attributesToUpdate, $attributesToMerge, $attributesToCreate, $attributesToDelete);
        foreach ($attributeCodes as $code) {
            $attributes[$code] = $this->getAttribute($code, $entity->getType());
        }

        if (count($attributes)) {
            $sql = "UPDATE entity AS e SET e.updated_at = NOW() WHERE e.entity_id = ".$this->escape($entity->getId()).";";
            $extraSqls = array($sql);

            $this->updateData($entity, $updatedData, $attributesToUpdate, $attributesToMerge, $attributesToCreate,
                $attributesToDelete, $attributes, $extraSqls);
        }

        return array_keys($attributes);

    }

    /**
     * Updates actual attribute values in DB. Used internally by both createEntity and saveEntity
     * @param Entity $entity The entity to update
     * @param array $updatedData An array of data being updated
     * @param string[] $attributesToUpdate
     * @param string[] $attributesToMerge
     * @param string[] $attributesToCreate
     * @param string[] $attributesToDelete
     * @param array $attributes Array of attribute rows, key is attribute code
     * @param array $extraSqls Any extra SQL queries to be run in the same transaction (i.e. updated_at changes)
     * @throws \Exception
     */
    protected function updateData(Entity $entity, array $updatedData, array $attributesToUpdate,
        array $attributesToMerge, array $attributesToCreate, array $attributesToDelete, array $attributes,
        array $extraSqls = array())
    {
        $sqls = array();

        foreach ($attributesToCreate as $code) {
            if (!array_key_exists($code, $attributes) || !$attributes[$code]) {
                throw new NodeException('Invalid attribute '.$code);
                // @todo: Stop further processing even the exception is commented out
            }
            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_DEBUGEXTRA,
                    'sav_upd_atcr',
                    'updateData - '.$entity->getId().' - create '.$code,
                    array('type'=>'create', 'attribute code'=>$code, 'new'=>$updatedData[$code]),
                    array('entity'=>$entity)
               );

            try{
                $sqls[] = $this->getValueInsertSql($entity->getId(), $attributes[$code], $updatedData[$code]);
            }catch (\Exception $exception) {
                $this->getServiceLocator()->get('logService')
                    ->log(LogService::LEVEL_ERROR,
                        'sav_upd_atcr_err',
                        'Exception during the insert',
                        array(
                            'entity data'=>$entity->getFullArrayCopy(),
                            'attribute code'=>$code,
                            'attributes['.$code.']'=>$attributes[$code],
                            'updatedData['.$code.']'=>$updatedData[$code],
                       ),
                        array(
                            'exception object'=>$exception,
                            'attribute'=>$attributes,
                            'updatedData'=>$updatedData,
                            'attributesToUpdate'=>$attributesToUpdate,
                            'attributesToMerge'=>$attributesToMerge,
                            'attributesToCreate'=>$attributesToCreate,
                            'attributesToDelete'=>$attributesToDelete
                       )
                   );
                throw new $exception;
            }
        }

        foreach ($attributesToDelete as $code) {
            if (!array_key_exists($code, $attributes) || !$attributes[$code]) {
                throw new NodeException('Invalid attribute ' . $code);
                // @todo: Stop further processing even the exception is commented out
            }
            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_DEBUGEXTRA,
                    'sav_upd_atde',
                    'updateData - '.$entity->getId().' - delete '.$code,
                    array('type'=>'delete', 'att'=>$code),
                    array('entity'=>$entity)
               );

            $sqls[] = $this->getValueDeleteSql($entity->getId(), $attributes[$code]);
        }

        foreach ($attributesToUpdate as $code) {
            if (!array_key_exists($code, $attributes) || !$attributes[$code]) {
                throw new NodeException('Invalid attribute '.$code);
                // @todo: Stop further processing even the exception is commented out
            }
            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_DEBUGEXTRA,
                    'sav_upd_atup',
                    'updateData - '.$entity->getId().' - update '.$code,
                    array(
                        'type'=>'update',
                        'attribute code'=>$code,
                        'old'=>$entity->getData($code),
                        'new'=>$updatedData[$code]
                    ),
                    array('entity'=>$entity)
               );

            $sqls[] = $this->getValueDeleteSql($entity->getId(), $attributes[$code]);
            try {
                $sqls[] = $this->getValueInsertSql($entity->getId(), $attributes[$code], $updatedData[$code]);
            }catch (\Exception $exception) {
                $this->getServiceLocator()->get('logService')
                    ->log(LogService::LEVEL_ERROR,
                        'sav_upd_atup_err',
                        'Exception during the insert',
                        array(
                            'entity data'=>$entity->getFullArrayCopy(),
                            'attribute code'=>$code,
                            'attributes['.$code.']'=>$attributes[$code],
                            'updatedData['.$code.']'=>$updatedData[$code],
                       ),
                        array(
                            'exception object'=>$exception,
                            'attribute'=>$attributes,
                            'updatedData'=>$updatedData,
                            'attributesToUpdate'=>$attributesToUpdate,
                            'attributesToMerge'=>$attributesToMerge,
                            'attributesToCreate'=>$attributesToCreate,
                            'attributesToDelete'=>$attributesToDelete
                       )
                   );
                throw new $exception;
                // @todo: Stop further processing even the exception is commented out
            }
        }

        foreach ($attributesToMerge as $code) {
            if (!array_key_exists($code, $attributes) || !$attributes[$code]) {
                throw new NodeException('Invalid attribute ' . $code);
                // @todo: Stop further processing even the exception is commented out
            }
            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_DEBUGEXTRA,
                    'sav_upd_atme',
                    'updateData - '.$entity->getId().' - merge '.$code,
                    array(
                        'type'=>'merge',
                        'attribute code'=>$code,
                        'old'=>$entity->getData($code),
                        'new'=>$updatedData[$code]
                    ),
                    array('entity'=>$entity)
               );
            $sqls = array_merge($sqls,
                $this->getValueMergeSql($entity->getId(), $attributes[$code], $updatedData[$code], $entity->getData($code))
           );
        }

        $sqls = array_merge($sqls, $extraSqls);

        $try = 0;
        $maxTries = 7;
        $success = FALSE;

        $adapter = $this->getAdapter();
        $transactionLabel = 'save-'.$entity->getId().'-'.$entity->getUniqueId();
        $this->beginTransaction($transactionLabel);

        do {
            try{
                foreach ($sqls as $sql) {
                    $this->getServiceLocator()->get('logService')
                        ->log(LogService::LEVEL_DEBUGEXTRA,
                            'sav_upd_sql',
                            'updateData - '.$entity->getId().' SQL: '.$sql,
                            array('sql'=>$sql),
                            array('entity'=>$entity)
                       );
                    $result = $adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
                    if (!$result) {
                        throw new MagelinkException('Unknown error executing attribute update query: '.$sql);
                    }
                }

                $this->getServiceLocator()->get('logService')
                    ->log(LogService::LEVEL_DEBUGEXTRA,
                        'sav_upd_commit'.$try,
                        'updateData - '.$entity->getId().' committed, '.count($sqls).' queries ran',
                        array('sqls'=>implode('; ', $sqls)),
                        array('entity'=>$entity)
                   );
                $success = $this->commitTransaction($transactionLabel);
            }catch (\Exception $exception) {
                $this->rollbackTransaction($transactionLabel);

                if (self::isRestartTransaction($exception)) {
                    $logCode = $try;
                    $isLast = (++$try >= $maxTries);
                }else{
                    $isLast = TRUE;
                    $logCode = '';
                }

                if ($isLast) {
                    $sleepMicroseconds = 0;
                    $logLevel = LogService::LEVEL_ERROR;
                    $logMessage = 'rolled back transaction';
                }else {
                    $sleepMicroseconds = sqrt($try) * 500;
                    $logLevel = LogService::LEVEL_WARN;
                    $logMessage = 'retrying '.$sleepMicroseconds.' ms later';
                }
                $logCode = 'sav_upd_fail'.$logCode;
                $logMessage = 'updateData of entity '.$entity->getId().', Exception in processing, '.$logMessage
                    .' ('.$try.'/'.$maxTries.').';
                $this->getServiceLocator()->get('logService')
                    ->log($logLevel, $logCode, $logMessage,
                        array(
                            'entity id'=>$entity->getId(),
                            'message'=>$exception->getMessage(),
                            'code'=>$exception->getCode(),
                            'sqls'=>implode('; ', $sqls),
                            'sql'=>(isset($sql) ? $sql : '')
                       ),
                        array('entity'=>$entity, 'exception'=>$exception)
                   );
                usleep($sleepMicroseconds);
            }
        }while (!$success && !$isLast);

        if (!$success) {
            throw new MagelinkException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Returns SQL to insert new value for attribute
     * @param int $entityId
     * @param array $attributes
     * @param mixed $value
     * @return string
     * @throws MagelinkException
     */
    protected function getValueInsertSql($entityId, $attributes, $value)
    {
        $values = array();
        if (!is_array($value)) {
            $values = array($value);
        }elseif ($attributes['type'] == 'multi') {
            if (!isset($value[0])) { // Correct format, associative
                $values = $value;
            }else{
                throw new NodeException('Invalid format for multi data');
            }
        }else{
            $values = $value;
        }

        $template = ' ('.$this->escape($entityId).', '.$this->escape($attributes['attribute_id']).', NOW(), {})';

        $valuesSql = array();
        foreach ($values as $key=>$value) {
            if ($attributes['type'] == 'multi') {
                $valuesSql[] = str_replace('{}', $this->escape($key).', '.$this->escape($value), $template);
            }elseif ($attributes['type'] == 'entity' && is_object($value)) {
                $valuesSql[] = str_replace('{}', $this->escape($value->getId()), $template);
            }else{
                $valuesSql[] = str_replace('{}', $this->escape($value), $template);
            }
        }

        if ($valuesSql) {
            $sql = "INSERT INTO entity_value_".$attributes['type']." (entity_id, attribute_id, updated_at,"
                .($attributes['type'] == 'multi' ? " `key`," : "")." value) VALUES ".implode(', ', $valuesSql).";";
        }else{
            $sql = NULL;
        }

        return $sql;
    }

    /**
     * Returns array of SQL to update attribute with new merged data
     * @param int $entityId
     * @param array $attributes
     * @param mixed $value
     * @param mixed $oldValue
     * @return array
     * @throws MagelinkException
     */
    protected function getValueMergeSql($entityId, $attributes, $value, $oldValue)
    {
        if ($attributes['type'] == 'multi') {
            throw new MagelinkException('multi attribute merging not yet supported - @todo');
            $sqls = array();
        }else{
            $values = array();
            if (is_array($oldValue) && !is_array($value)) {
                $values = $oldValue;
                $values[] = $value;
            }else {
                if (!is_array($oldValue) && is_array($value)) {
                    $values = $value;
                    array_unshift($values, $oldValue);
                }else {
                    if (!is_array($oldValue) && !is_array($value)) {
                        $values = array($oldValue, $value);
                    }else { // Both arrays
                        $values = array_merge($oldValue, $value);
                    }
                }
            }

            $sqls = array(
                $this->getValueDeleteSql($entityId, $attributes),
                $this->getValueInsertSql($entityId, $attributes, $values),
            );
        }

        return $sqls;
    }

    /**
     * Returns SQL to delete value from attribute
     * @param int $entityId
     * @param array $attributes
     * @return string
     */
    protected function getValueDeleteSql($entityId, $attributes)
    {
        // @todo: Implement proper Zend Framework functionality
        $sql = "DELETE FROM entity_value_".$attributes['type']." WHERE entity_id = ".$this->escape($entityId)
            ." AND attribute_id = ".$this->escape($attributes['attribute_id']).";";
        return $sql;
    }

}

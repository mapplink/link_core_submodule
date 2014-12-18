<?php

/* 
 * Copyright (c) 2014 Lero9 Limited
 * All Rights Reserved
 * This software is subject to our terms of trade and any applicable licensing agreements.
 */

namespace Entity\Helper;

use Magelink\Exception\MagelinkException;
use Magelink\Exception\NodeException;

/**
 * Responsible for updating entities in the database
 */
class Saver extends AbstractHelper implements \Zend\ServiceManager\ServiceLocatorAwareInterface
{

    const MYSQL_ER_LOCK_DEADLOCK = 40001;

    /**
     * Deletes an Entity from the system along with any attached data (comments, identifiers, etc).
     * @param \Entity\Entity $entity
     * @throws \Exception Passes on any exceptions thrown while executing SQL.
     */
    public function deleteEntity( \Entity\Entity $entity ){
        $this->beginTransaction('delete-'.$entity->getId());
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
            foreach($sql as $s){
                $this->getAdapter()->query(str_replace(':eid', $entity->getId(), $s), \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
            }
            $this->commitTransaction('delete-'.$entity->getId());
        }catch(\Exception $e){
            $this->rollbackTransaction('delete-'.$entity->getId());
            throw $e;
        }
    }

    /**
     * Update just an entities unique ID, without touching any other data or the updated_at
     * @param int $entity_id
     * @param string $unique_id
     */
    public function setEntityUnique( $entity_id, $unique_id ){
        $this->getAdapter()->query('UPDATE entity AS e SET e.unique_id = ' . $this->escape($unique_id) . ' WHERE e.entity_id = ' . $this->escape($entity_id), \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
    }

    /**
     * Update just an entities parent, without touching any other data or the updated_at
     * @param $child_id
     * @param $parent_id
     */
    public function setEntityParent ( $child_id, $parent_id ){
        $this->getAdapter()->query('UPDATE entity AS e SET e.parent_id = ' . $this->escape($parent_id) . ' WHERE e.entity_id = ' . $this->escape($child_id), \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
    }

    /**
     * Updates the updated_at timestamp on an entity and optionally some attributes
     * @param \Entity\Entity $entity
     * @param string[] $attributes
     * @throws \Exception
     */
    public function touchEntity ( \Entity\Entity $entity, $attributes=array() ){
        $ts = date('Y-m-d H:i:s');

        $sql = array('UPDATE entity AS e SET e.updated_at = ' . $this->escape($ts) . ' WHERE e.entity_id = ' . $this->escape($entity->getId()));

        foreach($attributes as $att){
            $attData = $this->getAttribute($att, $entity->getType());
            $sql[] = 'UPDATE entity_value_' . $attData['type'] . ' AS ev SET ev.updated_at = ' . $this->escape($ts) . ' WHERE ev.entity_id = ' . $this->escape($entity->getId()) . ' AND ev.attribute_id = ' . $this->escape($attData['attribute_id']);
        }

        $adapter = $this->getAdapter();
        $this->beginTransaction('touch-'.$entity->getId());
        try{
            foreach($sql as $s){
                $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA, 'sav_touch_sql', 'touchEntity - ' . $entity->getId() . ' SQL: ' . $s, array('sql'=>$s), array('entity'=>$entity));
                $res = $adapter->query($s, \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
                if(!$res){
                    throw new MagelinkException('Unknown error executing attribute update query: ' . $s);
                }
            }
            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA, 'sav_touch_commit', 'touchEntity - ' . $entity->getId() . ' committed, ' . count($sql) . ' queries ran', array('sql'=>$s), array('entity'=>$entity));
            $this->commitTransaction('touch-'.$entity->getId());
        }catch(\Exception $e){
            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA, 'sav_touch_err', 'touchEntity - ' . $entity->getId() . ' - Exception in processing, rolling back', array('message'=>$e->getMessage()), array('entity'=>$entity, 'exception'=>$e));
            $this->rollbackTransaction('touch-'.$entity->getId());
            throw $e;
        }
    }

    /**
     * Create new entity in the database. Does not update any logging or distribution tables, or cause any routing.
     *
     * @param int $entity_type_id
     * @param int $store_id
     * @param string $unique_id
     * @param int $parent_id
     * @param array $data
     * @throws MagelinkException
     * @return int The entity ID of the newly created entity
     */
    public function createEntity( $entity_type_id, $store_id, $unique_id, $parent_id, $data ){

        $dataArr = array(
            'type_id'=>$entity_type_id,
            'store_id'=>$store_id,
            'unique_id'=>$unique_id,
            'parent_id'=>($parent_id ? $parent_id : null),
            'updated_at'=>date('Y-m-d H:i:s')
        );

        $val = $this->getTableGateway('entity')->insert($dataArr);
        $ent_id = $this->getAdapter()->getDriver()->getLastGeneratedValue();//$this->getTableGateway('entity')->getLastInsertValue();
        $dataArr['entity_id'] = intval($ent_id);
        if(!$val || !$ent_id){
            throw new MagelinkException('Unknown error inserting entity record');
        }

        foreach($data as $k=>$v){
            if(is_object($v) && $v instanceof \Entity\Entity){
                if(!$v->getId()){
                    throw new NodeException('Invalid ID for Entity-type value');
                }
                $data[$k] = $v->getId();
            }
            if($v == null){
                unset($data[$k]);
            }
        }

        $attributeKeys = array_keys($data);

        $attribute = array();
        foreach($attributeKeys as $att){
            $attribute[$att] = $this->getAttribute($att, $entity_type_id);
        }

        $ent = new \Entity\Entity($dataArr, $attribute, 0);

        $this->updateData($ent, $data, array(), array(), $attributeKeys, array(), $attribute);

        return $dataArr['entity_id'];
    }

    /**
     * Save new entity data into the database. Does not update any logging or distribution tables (i.e. entity_update)
     * 
     * @param \Entity\Entity $entity The Entity to update.
     * @param array $updatedData
     * @param array $merge
     * @param bool $forcedUpdate
     * @return string[] A list of all attribute codes that were updated
     */
    public function saveEntity(\Entity\Entity $entity, $updatedData, $merge = array(), $forcedUpdate = FALSE)
    {
        $attributesToUpdate = array();
        $attributesToMerge = array();
        $attributesToCreate = array();
        $attributesToDelete = array();
        
        foreach ($updatedData as $code=>$newValue) {

            if (is_object($newValue) && $newValue instanceof \Entity\Entity){
                if (!$newValue->getId()) {
                    throw new NodeException('Invalid ID for Entity-type value');
                }else{
                    $newValue = $newValue->getId();
                }
            }

            $oldValue = $entity->getData($code);
            if ($oldValue !== NULL && $newValue !== NULL) {
                settype($newValue, gettype($oldValue));
            }

            if ($newValue === NULL && $oldValue !== NULL) {
                $attributesToDelete[] = $code;

            }elseif ($oldValue === NULL && $newValue !== NULL) {
                $attributesToCreate[] = $code;

            }elseif ($oldValue !== $newValue || $forcedUpdate) {
                if (is_array($oldValue) && $merge === TRUE) {
                    $attributesToMerge[] = $code;
                }elseif (is_array($merge)) {
                    if (isset($merge[$code]) && $merge[$code] === TRUE) {
                        $attributesToMerge[] = $code;
                    }else{
                        $attributesToUpdate[] = $code;
                    }
                }else{
                    $attributesToUpdate[] = $code;
                }
            }else{
                // Not changed - perhaps warn
            }
        }

        $attribute = array();
        foreach (array_merge($attributesToUpdate, $attributesToMerge, $attributesToCreate, $attributesToDelete) as $att) {
            $attribute[$att] = $this->getAttribute($att, $entity->getType());
        }

        if(count($attribute)){
            $extraSql = array('UPDATE entity AS e SET e.updated_at = NOW() WHERE e.entity_id = ' . $this->escape($entity->getId()) . ';');

            $this->updateData($entity, $updatedData, $attributesToUpdate, $attributesToMerge, $attributesToCreate, $attributesToDelete, $attribute, $extraSql);
        }

        return array_keys($attribute);

    }

    /**
     * Updates actual attribute values in DB. Used internally by both createEntity and saveEntity
     * @param \Entity\Entity $entity The entity to update
     * @param array $updatedData An array of data being updated
     * @param string[] $attributesToUpdate
     * @param string[] $attributesToMerge
     * @param string[] $attributesToCreate
     * @param string[] $attributesToDelete
     * @param array $attribute Array of attribute rows, key is attribute code
     * @param array $extraSql Any extra SQL queries to be ran at the same time, in the same transaction (i.e. updated_at changes)
     * @throws \Exception
     */
    protected function updateData(\Entity\Entity $entity, array $updatedData, array $attributesToUpdate,
        array $attributesToMerge, array $attributesToCreate, array $attributesToDelete, array $attribute,
        array $extraSql = array()){

        $sql = array();

        foreach($attributesToCreate as $att){
            if(!array_key_exists($att, $attribute) || !$attribute[$att]){
                throw new NodeException('Invalid attribute ' . $att);
            }
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA,
                    'sav_update_att',
                    'updateData - '.$entity->getId().' - create '.$att,
                    array('type'=>'create', 'att'=>$att, 'new'=>$updatedData[$att]),
                    array('entity'=>$entity)
                );
            try{
            $sql[] = $this->getValueInsertSql($entity->getId(), $attribute[$att], $updatedData[$att]);
            }catch (\Exception $exception) {
                $this->getServiceLocator()->get('logService')
                    ->log(\Log\Service\LogService::LEVEL_ERROR,
                        'insert_sql_error',
                        'Exception during the insert',
                        array(
                            'entity data'=>$entity->getFullArrayCopy(),
                            'att'=>$att,
                            'attribute[$att]'=>$attribute[$att],
                            'attribute[att]'=>$attribute[$att],
                            'updatedData[att]'=>$updatedData[$att],
                        ),
                        array(
                            'exception object'=>$exception,
                            'attribute'=>$attribute,
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
        foreach($attributesToDelete as $att){
            if(!array_key_exists($att, $attribute) || !$attribute[$att]){
                throw new NodeException('Invalid attribute ' . $att);
            }
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA,
                    'sav_update_att',
                    'updateData - '.$entity->getId().' - delete '.$att,
                    array('type'=>'delete', 'att'=>$att),
                    array('entity'=>$entity)
                );
            $sql[] = $this->getValueDeleteSql($entity->getId(), $attribute[$att]);
        }
        foreach($attributesToUpdate as $att){
            if(!array_key_exists($att, $attribute) || !$attribute[$att]){
                throw new NodeException('Invalid attribute ' . $att);
            }
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA,
                    'sav_update_att',
                    'updateData - '.$entity->getId().' - update '.$att,
                    array('type'=>'update', 'att'=>$att, 'old'=>$entity->getData($att), 'new'=>$updatedData[$att]),
                    array('entity'=>$entity)
                );
            $sql[] = $this->getValueDeleteSql($entity->getId(), $attribute[$att]);
            try {
            $sql[] = $this->getValueInsertSql($entity->getId(), $attribute[$att], $updatedData[$att]);
            }catch (\Exception $exception) {
                $this->getServiceLocator()->get('logService')
                    ->log(\Log\Service\LogService::LEVEL_ERROR,
                        'insert_sql_error',
                        'Exception during the insert',
                        array(
                            'entity data'=>$entity->getFullArrayCopy(),
                            'att'=>$att,
                            'attribute[$att]'=>$attribute[$att],
                            'attribute[att]'=>$attribute[$att],
                            'updatedData[att]'=>$updatedData[$att],
                        ),
                        array(
                            'exception object'=>$exception,
                            'attribute'=>$attribute,
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
        foreach($attributesToMerge as $att){
            if(!array_key_exists($att, $attribute) || !$attribute[$att]){
                throw new NodeException('Invalid attribute ' . $att);
            }
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_DEBUGEXTRA,
                    'sav_update_att',
                    'updateData - '.$entity->getId().' - merge '.$att,
                    array('type'=>'merge', 'att'=>$att, 'old'=>$entity->getData($att), 'new'=>$updatedData[$att]),
                    array('entity'=>$entity)
                );
            $sql = array_merge(
                $sql,
                $this->getValueMergeSql($entity->getId(), $attribute[$att], $updatedData[$att], $entity->getData($att))
            );
        }
        $try = 1;
        $maxTries = 3;
        $success = FALSE;
        $adapter = $this->getAdapter();

        do {
            $this->beginTransaction('save-'.$entity->getId());
            try{
                foreach ($sql as $s) {
                    $this->getServiceLocator()->get('logService')
                        ->log(
                            \Log\Service\LogService::LEVEL_DEBUGEXTRA,
                            'sav_update_sql',
                            'updateData - '.$entity->getId().' SQL: '.$s,
                            array('sql' => $s),
                            array('entity' => $entity)
                        );
                    $res = $adapter->query($s, \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
                    if (!$res) {
                        throw new MagelinkException('Unknown error executing attribute update query: '.$s);
                    }
                }
                $this->getServiceLocator()->get('logService')
                    ->log(
                        \Log\Service\LogService::LEVEL_DEBUGEXTRA,
                        'sav_update_commit',
                        'updateData - '.$entity->getId().' committed, '.count($sql).' queries ran',
                        array('sql' => $sql),
                        array('entity' => $entity)
                    );
                $this->commitTransaction('save-'.$entity->getId());
                $success = TRUE;
            }catch( \Exception $exception ){
                $this->rollbackTransaction('save-'.$entity->getId());
                $this->getServiceLocator()->get('logService')
                    ->log(
                        \Log\Service\LogService::LEVEL_ERROR,
                        'sav_update_err'.($maxTries ? '_'.$try : ''),
                        'updateData - '.$entity->getId().' - Exception in processing, rolling back',
                        array(
                            'entity id' => $entity->getId(),
                            'message' => $exception->getMessage(),
                            'code' => $exception->getCode()
                        ),
                        array('entity' => $entity, 'exception' => $exception)
                    );

                if ($exception->getCode() == self::MYSQL_ER_LOCK_DEADLOCK) {
                    sleep(2);
                }else {
                    $maxTries = 0;
                }
            }
        }while (!$success && $maxTries - $try++ > 0);

        if (!$success) {
            throw new MagelinkException($exception->getMessage(), $exception->getCode(), $exception->getPrevious());
        }
    }
    
    /**
     * Returns SQL to insert new value for attribute
     * @param int $entity_id
     * @param array $attribute
     * @param mixed $value
     * @return string
     * @throws MagelinkException
     */
    protected function getValueInsertSql( $entity_id, $attribute, $value ) {
        $values = array();
        if(!is_array($value)){
            $values = array($value);
        }else if($attribute['type'] == 'multi'){
            if(!isset($value[0])){
                // Correct format, associative
                $values = $value;
            }else{
                throw new NodeException('Invalid format for multi data');
            }
        }else{
            $values = $value;
        }
        
        $template = '(' . $this->escape($entity_id) . ', ' . $this->escape($attribute['attribute_id']) . ', NOW(), {})';
        
        $valuesSql = array();
        foreach($values as $k=>$val){
            if($attribute['type'] == 'multi'){
                $valuesSql[] = str_replace('{}', $this->escape($k) . ', ' . $this->escape($val), $template);
            }else if($attribute['type'] == 'entity' && is_object($val)){
                $valuesSql[] = str_replace('{}', $this->escape($val->getId()), $template);
            } else{
                $valuesSql[] = str_replace('{}', $this->escape($val), $template);
            }
        }
        
        $sql = 'INSERT INTO entity_value_' . $attribute['type'] . ' (entity_id, attribute_id, updated_at, ';
        if($attribute['type'] == 'multi'){
            $sql .= '`key`, ';
        }
        $sql .= 'value) VALUES ';
        
        $sql .= implode(', ', $valuesSql);
        
        $sql .= ';';
        
        return $sql;
    }
    
    /**
     * Returns array of SQL to update attribute with new merged data
     * @param int $entity_id
     * @param array $attribute
     * @param mixed $value
     * @param mixed $oldValue
     * @return array
     * @throws MagelinkException
     */
    protected function getValueMergeSql( $entity_id, $attribute, $value, $oldValue ) {
        
        if($attribute['type'] == 'multi'){
            throw new MagelinkException('multi attribute merging not yet supported - TODO');
        }
        
        $values = array();
        
        if(is_array($oldValue) && !is_array($value)){
            $values = $oldValue;
            $values[] = $value;
        }else if(!is_array($oldValue) && is_array($value)){
            $values = $value;
            array_unshift($values, $oldValue);
        }else if(!is_array($oldValue) && !is_array($value)){
            $values = array($oldValue, $value);
        }else{ // Both arrays
            $values = array_merge($oldValue, $value);
        }
        
        return array(
            $this->getValueDeleteSql($entity_id, $attribute),
            $this->getValueInsertSql($entity_id, $attribute, $values),
        );
        
    }
    
    /**
     * Returns SQL to delete value from attribute
     * @param int $entity_id
     * @param array $attribute
     * @return string
     */
    protected function getValueDeleteSql( $entity_id, $attribute ) {
        return 'DELETE FROM entity_value_' . $attribute['type'] . ' WHERE entity_id = ' . $this->escape($entity_id) . ' AND attribute_id = ' . $this->escape($attribute['attribute_id']) . ';';
    }
    
}
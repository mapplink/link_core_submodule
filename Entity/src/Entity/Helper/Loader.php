<?php
/*
 * Responsible for locating and loading entities from the database
 * @category Entity
 * @package Entity\Helper
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright(c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Entity\Helper;

use Entity\Entity;
use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Magelink\Exception\NodeException;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;


class Loader extends AbstractHelper implements \Zend\ServiceManager\ServiceLocatorAwareInterface
{

    /**
     * Load skeleton entities using search and fill in all needed attributes
     * @param int|string $entityTypeId
     * @param int $storeId
     * @param array $searchData
     * @param array $attributeCodes
     * @param array $searchType
     * @param array $options
     * @return \Entity\Entity[]
     * @throws MagelinkException
     */
    protected function getEntitiesLocateSelect($entityTypeId, $storeId, array $searchData, array $attributeCodes,
        array $searchType = array(), array $options = array())
    {
        if ($entityTypeId === false) {
            throw new NodeException('No entity type ID passed to loadEntities');
        }

        foreach ($searchData as $attributeCode=>$value) {
            if (strtoupper($attributeCode) === $attributeCode) {
                // Special tokens, i.e. UNIQUE_ID, allowed.
                continue;
            }
            if (strpos($attributeCode, '.') !== FALSE) {
                // Foreign-key types bypass initial checks (may fail later, but too difficult to sanity-check here)
                continue;
            }
            if (!in_array($attributeCode, $attributeCodes)) {
                throw new NodeException('Invalid search attribute '.$attributeCode);
            }
        }

        $locateSelect = $this->getLocateSelect($entityTypeId, $storeId, $searchData, $searchType, $options);

        $this->getServiceLocator()->get('logService')
            ->log(
                LogService::LEVEL_DEBUGEXTRA,
                'load_locate',
                'loadEntities - locate query:'.$locateSelect->getSqlString($this->getAdapter()->getPlatform()),
                array('query' => $locateSelect->getSqlString($this->getAdapter()->getPlatform()))
            );

        return $locateSelect;
    }

    /**
     * @param int|string $entityTypeId
     * @param int $storeId
     * @param array $searchData
     * @param array $searchType
     * @param array $options
     * @return \Entity\Entity[]
     * @throws MagelinkException
     */
    public function areEntities($entityTypeId, $storeId, array $searchData, array $searchType = array(),
        array $options = array())
    {
        $locateSelect = $this->getEntitiesLocateSelect(
            $entityTypeId,
            $storeId,
            $searchData,
            array(),
            $searchType,
            $options
        );


        try{
            $result = $this->getAdapter()->query(
                $locateSelect->getSqlString($this->getAdapter()->getPlatform()),
                \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
            );
            $are = (bool) $result->count();
        }catch (\Exception $exception) {
            $are = FALSE;
        }

        return $are;
    }
        /**
     * Load entities using search and fill in all needed attributes
     * @param int|string $entityTypeId
     * @param int $storeId
     * @param array $searchData
     * @param array $attributeCodes
     * @param array $searchType
     * @param array $options
     * @return \Entity\Entity[]
     * @throws MagelinkException
     */
    public function loadEntities($entityTypeId, $storeId, array $searchData, array $attributeCodes,
        array $searchType = array(), array $options = array())
    {
        $locateSelect = $this->getEntitiesLocateSelect(
            $entityTypeId,
            $storeId,
            $searchData,
            $attributeCodes,
            $searchType,
            $options
        );

        try{
            $result = $this->getAdapter()->query(
                $locateSelect->getSqlString($this->getAdapter()->getPlatform()),
                \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
            );
        }catch( \Exception $ex ){
            throw new MagelinkException(
                'Error in locate query: '.$locateSelect->getSqlString($this->getAdapter()->getPlatform()), 0, $ex
            );
        }

        if (!$result) {
            throw new MagelinkException('Unknown error in locate select: '.$locateSelect->getSqlString($this->getAdapter()->getPlatform()));
        }

        if (array_key_exists('count', $options)) {
            if (!$result || !count($result)) {
                return 0;
            }elseif (count($result) > 1) {
                throw new MagelinkException('Count returned multiple rows!');
            }else{
                foreach ($result as $row) {
                    return (int) $row['count'];
                }
                return 0;
            }
        }
        if (array_key_exists('aggregate', $options)) {
            if (!$result || !count($result)) {
                return array();
            }elseif (count($result) > 1) {
                throw new MagelinkException('Aggregate returned multiple rows!');
            }else{
                foreach ($result as $row) {
                    return $row;
                }
                return 0;
            }

        }

        if (count($result) == 0) {
            // No need to progress further
            $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_DEBUGEXTRA, 'load_locate_none', 'loadEntities - locate query found no results', array());
            return array();
        }
        $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_DEBUGEXTRA, 'load_locate_c', 'loadEntities - locate query found '.count($result).' results', array('count'=>count($result)));

        if (array_key_exists('static_field', $options)) {
            $res = array();
            foreach ($result as $row) {
                $res[] = $row[$options['static_field']];
            }
            return $res;
        }

        /** @var \Entity\Entity[] $entities */
        $entities = array();
        $entityIds = array();

        $entityTypeName = $this->getServiceLocator()->get('entityConfigService')->parseEntityTypeReverse($entityTypeId);
        $attributes = array();
        foreach ($attributeCodes as $attId) {
            $d = $this->getAttribute($attId, $entityTypeId);
            if (!$d || !(is_array($d) || $d instanceof \ArrayObject)) {
                $this->getServiceLocator()->get('logService')
                    ->log(
                        LogService::LEVEL_DEBUG,
                        'load_noatt',
                        'loadEntities - failed to find attribute '.$attId.' for '.$entityTypeId.'',
                        array('res' => $d, 'debug' => $this->getAttributeDebuggingData(), 'codes' => $attributeCodes)
                    );
                throw new NodeException('Could not find attribute '.$attId.' for '.$entityTypeId);
            }
            if (isset($d['fetch_data']) && !is_array($d['fetch_data']) && strlen($d['fetch_data'])) {
                $d['fetch_data'] = unserialize($d['fetch_data']);
            }
            $attributes[$d['attribute_id']] = $d;
        }

        foreach ($result as $row) {
            $entityIds[] = $row['entity_id'];

            $config = $this->getServiceLocator()->get('Config');

            if ($row['type_id'] == $entityTypeId) {
                $type = $entityTypeName;
            }else{
                $type = $this->getServiceLocator()->get('entityConfigService')->parseEntityTypeReverse($row['type_id']);
            }

            $class = isset($config['entity_class'][$type]) ? $config['entity_class'][$type] : '\Entity\Entity';
            $entities[$row['entity_id']] = new $class($row, $attributes, (array_key_exists('node_id', $options) ? $options['node_id'] : 0));
            $entities[$row['entity_id']]->setServiceLocator($this->getServiceLocator());
        }

        if (count($attributes)) {
            $fillSql = $this->getAttributeFillSql($entityIds, $attributes);
            $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_DEBUGEXTRA, 'load_locate_fill', 'loadEntities - fill query: '.$fillSql, array('sql'=>$fillSql));
            $fillResult = $this->getAdapter()->query($fillSql, \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);

            $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_DEBUGEXTRA, 'load_locate_fillc', 'loadEntities - fill query got '.count($fillResult).' values', array('count'=>count($fillResult)));

            $entityValues = $this->processFillResult($fillResult);

            foreach ($entityValues as $entId=>$arr) {
                // Populate values into entities
                if (!array_key_exists($entId, $entities)) {
                    throw new MagelinkException('Invalid entity returned from fill query - '.$entId);
                }
                $entities[$entId]->populateRaw($arr);
            }
        }else{
            $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_DEBUGEXTRA, 'load_locate_nf', 'loadEntities - no fill query, no attributes ', array());
        }

        return $entities;
    }

    /**
     * Fetches the entity type ID for a given entity from the database
     * @param $entityId
     * @return bool
     * @throws \Magelink\Exception\MagelinkException If the given entity ID is in an invalid format
     */
    public function getEntityTypeId($entityId)
    {
        if (!is_int($entityId)) {
            throw new NodeException('Invalid entityId format: '.var_export($entityId, TRUE));
        }

        $query = 'SELECT e.type_id AS type_id FROM entity AS e WHERE e.entity_id = '.$this->escape($entityId);
        $result = $this->getAdapter()->query($query, Adapter::QUERY_MODE_EXECUTE);

        $entityTypeId = FALSE;
        if ($result) {
            foreach ($result as $row) {
                if (isset($row['type_id'])) {
                    $entityTypeId = $row['type_id'];
                }
                break;
            }
        }

        return $entityTypeId;
    }

    /**
     * Load additional attributes into a given entity
     * @param \Entity\Entity $entity
     * @param array $attributeCodes
     * @return \Entity\Entity
     */
    public function enhanceEntity(\Entity\Entity &$entity, $attributeCodes)
    {
        if (!is_array($attributeCodes) || !count($attributeCodes)) {
            throw new NodeException('Invalid attribute list passed to enhanceEntity');
        }
        $attributes = array();
        foreach ($attributeCodes as $attId) {
            if (!$attId || !strlen($attId)) {
                continue;
            }
            $d = $this->getAttribute($attId, $entity->getType());
            if (!$d) {
                throw new NodeException('Non-existing attribute passed to enhanceEntity - '.$attId.' on type '.$entity->getType());
            }
            $attributes[$d['attribute_id']] = $d;
            $entity->addAttribute($d);
        }

        $fillSql = $this->getAttributeFillSql(array($entity->getId()), $attributes);
        if (strlen($fillSql)) {
            $fillResult = $this->getAdapter()->query($fillSql, \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);

            $entityValues = $this->processFillResult($fillResult);

            if (array_key_exists($entity->getId(), $entityValues)) {
                $entity->populateRaw($entityValues[$entity->getId()]);
            }
        }else{
            $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_DEBUGEXTRA, 'enhance_empty', 'enhanceEntity - no fill query for '.$entity->getId().' with req '.implode(', ', $attributeCodes), array('atts'=>$attributeCodes));
        }

        return $entity;
    }

    /**
     * Take the results from a fill query and populate the entity values, parsing for array values etc.
     * @param array|\Zend\Db\ResultSet\ResultSet $fillResult
     * @return array
     */
    protected function processFillResult($fillResult)
    {
        $entityValues = array();
        foreach ($fillResult as $row) {
            if (!array_key_exists($row['ent_id'], $entityValues)) {
                $entityValues[$row['ent_id']] = array();
            }

            if (array_key_exists('key', $row) && $row['key'] !== null) {
                // Multi type attribute
                if (!array_key_exists($row['att_id'], $entityValues[$row['ent_id']])) {
                    $entityValues[$row['ent_id']][$row['att_id']] = array();
                }
                $entityValues[$row['ent_id']][$row['att_id']][$row['key']] = $row['value'];
            }elseif (array_key_exists($row['att_id'], $entityValues[$row['ent_id']])) {
                // Already set, transform to array if needed or just append
                if (is_array($entityValues[$row['ent_id']][$row['att_id']])) {
                    $entityValues[$row['ent_id']][$row['att_id']][] = $row['value'];
                }else{
                    $entityValues[$row['ent_id']][$row['att_id']] = array($entityValues[$row['ent_id']][$row['att_id']], $row['value']);
                }
            }else{
                // New value
                $entityValues[$row['ent_id']][$row['att_id']] = $row['value'];
            }
        }
        return $entityValues;
    }

    /**
     * Based on the provided options, generates a Select object to retrieve matching records
     * @param int $entityTypeId
     * @param string $storeId
     * @param array $searchData
     * @param array $searchType
     * @param array $options
     * @see EntityService->locateEntity - several parameters match with better descriptions.
     * @throws MagelinkException
     * @return \Zend\Db\Sql\Select
     */
    protected function getLocateSelect($entityTypeId, $storeId, $searchData, $searchType, $options)
    {
        $this->preprocessSearchType($searchData, $searchType);

        /* @var $select \Zend\Db\Sql\Select */
        $zendDb = new \Zend\Db\Sql\Sql($this->getAdapter());
        $select = $zendDb->select();
        /* @var $select \Zend\Db\Sql\Select */
        $select->from(array('e'=>'entity'));

        $suppressSelect = (array_key_exists('aggregate', $options) || array_key_exists('no_select', $options) || array_key_exists('group', $options) || array_key_exists('count', $options));

        $prefix = '';
        if (array_key_exists('select_prefix', $options)) {
            $prefix = $options['select_prefix'];
        }

        $extraJoin = array();
        if (array_key_exists('extra_join_att', $options) && is_array($options['extra_join_att'])) {
            $extraJoin = $options['extra_join_att'];
        }
        if (array_key_exists('order', $options)) {
            foreach ($options['order'] as $att=>$dir) {
                if (strtoupper($att) !== $att) {
                    $extraJoin[] = $att;
                }
            }
        }

        if (array_key_exists('nest_join', $options)) {
            /** @var \Zend\Db\Sql\Select $select */
            $select = $options['nest_join_select'];
            $select->join(
                array($prefix.'e'=>'entity'),
                $options['nest_join_condition'],
                array(),
                $options['nest_join_type']
            );
        }else{
            /* @var $select \Zend\Db\Sql\Select */
            $zendDb = new \Zend\Db\Sql\Sql($this->getAdapter());
            $select = $zendDb->select();
            /* @var $select \Zend\Db\Sql\Select */
            $select->from(array($prefix.'e'=>'entity'));
        }

        $fkey_type_ids = array();
        $resolved_fkeys = array();

        if (array_key_exists('fkey', $options)) {
            foreach ($options['fkey'] as $fkey_field=>$fkey_type) {
                if (!array_key_exists($fkey_field, $searchData) && !in_array($fkey_field, $extraJoin)) {
                    $this->addJoin($select, $fkey_field, true, $entityTypeId, $prefix);
                }
                $resolved_fkeys[] = $fkey_field;
                $this->addFkeyJoin($select, $fkey_type_ids, $fkey_field, $fkey_type, $prefix, $resolved_fkeys);
            }
        }

        if (array_key_exists('count', $options)) {
            $countField = $options['count'];
            if ($countField == 'STORE_ID') {
                $countField = $prefix.'e.store_id';
            }elseif ($countField == 'UNIQUE_ID') {
                $countField = $prefix.'e.unique_id';
            }elseif ($countField == 'PARENT_ID') {
                $countField = $prefix.'e.parent_id';
            }
            $select->columns(array('count'=>new \Zend\Db\Sql\Expression('COUNT('.$countField.')')));
        }elseif (array_key_exists('aggregate', $options)) {
            foreach ($options['aggregate'] as $att=>$type) {
                if (!array_key_exists($att, $searchData) && !in_array($att, $extraJoin)) {
                    $extraJoin[] = $att;
                }
                $select->columns(array('agg_'.$att.'_'.strtolower($type) => new \Zend\Db\Sql\Expression($type.'('.$prefix.'val_'.$att.'.value)')));
            }
        }elseif (!array_key_exists('no_select', $options)) {
            $select->columns(array('entity_id'=>'entity_id', 'type_id'=>'type_id', 'store_id'=>'store_id', 'unique_id'=>'unique_id', 'updated_at'=>'updated_at', 'parent_id'=>'parent_id'));
        }

        if (array_key_exists('group', $options)) {
            foreach ($options['group'] as $att) {
                if (!array_key_exists($att, $searchData) && !in_array($att, $extraJoin)) {
                    $extraJoin[] = $att;
                }
                $select->group(new \Zend\Db\Sql\Expression($prefix.'val_'.$att.'.value'));
            }
        }

        if (array_key_exists('linked_to_node', $options)) {
            $select->join(
                array($prefix.'eid_link'=>'entity_identifier'),
                new Expression($prefix.'eid_link.entity_id = '.$prefix.'e.entity_id AND '.$prefix.'eid_link.node_id = '.$this->escape($options['linked_to_node'])),
                ($suppressSelect ? array() : array($prefix.'local_id'=>'local_id')),
                $select::JOIN_INNER
            );
            if (array_key_exists('LOCAL_ID', $searchData)) {
                $select->where($this->generateFieldCriteria($prefix.'eid_link.local_id', $searchData['LOCAL_ID'], $searchType['LOCAL_ID']));
                unset($searchData['LOCAL_ID']);
            }
        }elseif (array_key_exists('LOCAL_ID', $searchData)) {
            throw new NodeException('Local ID specified but not checking node linkage');
        }

        if ($entityTypeId !== false) {
            $select->where(array($prefix.'e.type_id'=>$entityTypeId));
        }
        if ($storeId > 0 && !array_key_exists('STORE_ID', $searchData)) {
            $select->where(array($prefix.'e.store_id'=>$storeId));
        }
        if (array_key_exists('ENTITY_ID', $searchData)) {
            $select->where($this->generateFieldCriteria($prefix.'e.entity_id', $searchData['ENTITY_ID'], $searchType['ENTITY_ID']));
            unset($searchData['ENTITY_ID']);
        }
        if (array_key_exists('UNIQUE_ID', $searchData)) {
            $select->where($this->generateFieldCriteria($prefix.'e.unique_id', $searchData['UNIQUE_ID'], $searchType['UNIQUE_ID']));
            unset($searchData['UNIQUE_ID']);
        }
        if (array_key_exists('PARENT_ID', $searchData)) {
            $select->where($this->generateFieldCriteria($prefix.'e.parent_id', $searchData['PARENT_ID'], $searchType['PARENT_ID']));
            unset($searchData['PARENT_ID']);
        }
        if (array_key_exists('STORE_ID', $searchData)) {
            $select->where($this->generateFieldCriteria($prefix.'e.store_id', $searchData['STORE_ID'], $searchType['STORE_ID']));
            unset($searchData['STORE_ID']);
        }
        if (array_key_exists('UPDATED_AT', $searchData)) {
            $select->where($this->generateFieldCriteria($prefix.'e.updated_at', $searchData['UPDATED_AT'], $searchType['UPDATED_AT']));
            unset($searchData['UPDATED_AT']);
        }

        foreach ($searchData as $k=>$v) {
            if (strpos($k, '.') !== false) {
                $fkey_field = strtolower(substr($k, 0, strpos($k, '.')));
                $fkey_att = substr($k, strpos($k, '.')+1);

                if (!in_array($fkey_field, $resolved_fkeys)) {
                    $resolved_fkeys[] = $fkey_field;
                    $d = $this->getAttribute($fkey_field, $entityTypeId);
                    if (!$d) {
                        throw new NodeException('Invalid fkey attribute '.$fkey_field.' for '.$k);
                    }elseif ($d['type'] != 'entity') {
                        throw new NodeException('Invalid attribute type for '.$fkey_field.' - '.$k);
                    }elseif (!isset($d['fetch_data']['fkey_type'])) {
                        throw new NodeException('Fkey attribute '.$fkey_field.' for '.$k.' missing required fetchdata');
                    }
                    $this->addFkeyJoin($select, $fkey_type_ids, $fkey_field, $d['fetch_data']['fkey_type'], $prefix);
                }

                $attribute = $this->getAttribute($fkey_att, $fkey_type_ids[$fkey_field]);
                if (!$attribute) {
                    throw new NodeException('Invalid fkey search attribute '.$k.' for type '.$fkey_type_ids[$fkey_field]);
                }
                $table = $prefix.'fkey_'.$fkey_field.'_'.$fkey_att;
                $field = $table.'.value';
                $baseCondition = $table.'.entity_id = '.$prefix.'fkey_'.$fkey_field.'.entity_id AND '.$table.'.attribute_id = '.$attribute['attribute_id'];
            }else{
                $attribute = $this->getAttribute($k, $entityTypeId);
                if (!$attribute) {
                    throw new NodeException('Invalid search attribute '.$k.' for type '.$entityTypeId);
                }
                $table = $prefix.'val_'.$k;
                $field = $table.'.value';
                $baseCondition = $table.'.entity_id = '.$prefix.'e.entity_id AND '.$table.'.attribute_id = '.$attribute['attribute_id'];
            }

            switch($searchType[$k]) {
            case 'multi_key':
                $field = $table.'.`key`';
            case 'multi_value':
                $select->join(
                    array($table => 'entity_value_'.$attribute['type']),
                    new \Zend\Db\Sql\Expression($baseCondition.' AND '.$this->generateFieldCriteria($field, $v, 'in', FALSE, FALSE)),
                    ($suppressSelect ? array() : array($table.'_v'=>new \Zend\Db\Sql\Expression($field))),
                    $select::JOIN_INNER
                );
                break;
            case 'all_eq':
            case 'all_in':
            case 'all_gt':
            case 'all_lt':
                $select->join(
                        array($table.'_neg' => 'entity_value_'.$attribute['type']),
                        new \Zend\Db\Sql\Expression($baseCondition.' AND '.$this->generateFieldCriteria($table.'_neg.value', $v, $searchType[$k], true)),
                        array(),
                        $select::JOIN_LEFT
                    );
                $select->where($table.'_neg.value_id IS NULL');
                break;
            case 'null':
            case 'not_eq':
                $cond = $this->generateFieldCriteria($field, $v, $searchType[$k]);
                $select->join(
                    array($table => 'entity_value_'.$attribute['type']),
                    new \Zend\Db\Sql\Expression($baseCondition),
                    ($suppressSelect ? array() : array($table.'_v'=>new \Zend\Db\Sql\Expression($field))),
                    $select::JOIN_LEFT
                );
                if ($searchType[$k] != 'null' && $v != null) {
                    $cond = '('.$cond.' OR '.$this->generateFieldCriteria($field, null, 'null').')';
                }
                $select->where($cond);
                break;
            default:
                $select->join(
                        array($table => 'entity_value_'.$attribute['type']),
                        new \Zend\Db\Sql\Expression($baseCondition.' AND '.$this->generateFieldCriteria($field, $v, $searchType[$k])),
                        ($suppressSelect ? array() : array($table.'_v'=>new \Zend\Db\Sql\Expression($field))),
                        $select::JOIN_INNER
                    );
                break;
            }
        }

        foreach ($extraJoin as $att) {
            if (!array_key_exists($att, $searchData)) {
                $suppress = $suppressSelect;
                if ($suppressSelect && array_key_exists('extra_join_att', $options)) {
                    $suppress = !in_array($att, $options['extra_join_att']);
                }
                $this->addJoin($select, $att, $suppress, $entityTypeId, $prefix);
            }
        }

        if (array_key_exists('limit', $options)) {
            $select->limit(intval($options['limit']));
        }
        if (array_key_exists('offset', $options)) {
            $select->offset(intval($options['offset']));
        }
        if (array_key_exists('order', $options)) {
            $orders = array();
            foreach ($options['order'] as $att => $order) {
                if ($att == 'ENTITY_ID') {
                    $orders[$prefix.'e.entity_id'] = $order;
                }elseif ($att == 'UNIQUE_ID') {
                    $orders[$prefix.'e.unique_id'] = $order;
                }elseif ($att == 'UPDATED_AT') {
                    $orders[$prefix.'e.updated_at'] = $order;
                }elseif ($att == 'PARENT_ID') {
                    $orders[$prefix.'e.parent_id'] = $order;
                }else{
                    $orders[$prefix.'val_'.$att.'.value'] = $order;
                }
            }
            $select->order($orders);
        }

        return $select;
    }

    protected function addJoin(\Zend\Db\Sql\Select &$select, $att, $suppress, $entityTypeId, $prefix)
    {
        // Need to join
        $attribute = $this->getAttribute($att, $entityTypeId);
        $table = $prefix.'val_'.$att;
        $condition = $table.'.entity_id = '.$prefix.'e.entity_id AND '.$table.'.attribute_id = '.$attribute['attribute_id'];
        $select->join(
            array($table => 'entity_value_'.$attribute['type']),
            new \Zend\Db\Sql\Expression($condition),
            ($suppress ? array() : array($table.'_v'=>new \Zend\Db\Sql\Expression($table.'.value'))),
            $select::JOIN_LEFT
        );
        return $select;
    }

    protected function addFkeyJoin(\Zend\Db\Sql\Select &$select, array &$fkey_type_ids, $fkey_field, $fkey_type, $prefix)
    {
        if (strtoupper($fkey_field) === $fkey_field) {
            $srcField = $prefix.'e.'.strtolower($fkey_field);
        }elseif (strpos($fkey_field, '.') !== false) {
            $o_field = substr($fkey_field, 0, strpos($fkey_field, '.'));
            $o_att = substr($fkey_field, strpos($fkey_field, '.')+1);
            if (strtoupper($o_field) === $fkey_field) {
                $srcField = $prefix.'fkey_'.$o_field.'.'.strtolower($o_att);
            }else{
                $srcField = $prefix.'fkey_'.$o_field.'_'.$o_att.'.value';
            }
        }else{
            $srcField = $prefix.'val_'.$fkey_field.'.value';
        }
        $joinTable = $prefix.'fkey_'.strtolower($fkey_field);
        $fkey_type_id = $this->getServiceLocator()->get('entityConfigService')->parseEntityType($fkey_type);
        $fkey_type_ids[strtolower($fkey_field)] = $fkey_type_id;
        $select->join(
            array($joinTable=>'entity'),
            new Expression($srcField.' = '.$joinTable.'.entity_id AND '.$joinTable.'.type_id = '.$fkey_type_id),
            array(),
            $select::JOIN_LEFT
        );
        return $select;
    }

    /**
     * Automatically populate default search types, and sanity-check values.
     * @param array $searchData
     * @param array $searchType
     * @return array
     * @throws MagelinkException
     */
    protected function preprocessSearchType($searchData, &$searchType)
    {
        foreach ($searchType as $k=>$v) {
            if (!array_key_exists($k, $searchData)) {
                throw new NodeException('Search type specified but no search data for '.$k);
            }
        }
        foreach ($searchData as $k=>$v) {
            if (!array_key_exists($k, $searchType)) {
                if (is_array($v)) {
                    $numeric = true;
                    foreach ($v as $k2=>$v2) {
                        if (!is_int($k2)) {
                            $numeric = false;
                            break;
                        }
                    }
                    if ($numeric) {
                        $searchType[$k] = 'in';
                    }else{
                        throw new NodeException('Associative array search is not yet supported');
                    }
                }elseif (is_scalar($v)) {
                    $searchType[$k] = 'eq';
                }elseif (is_null($v)) {
                    $searchType[$k] = 'eq';
                }elseif (is_object($v) && $v instanceof \Entity\Entity) {
                    $searchType[$k] = 'eq';
                }else{
                    throw new NodeException('Unknown object type '.(is_object($v) ? get_class($v) : gettype($v)));
                }
            }
        }
        return $searchType;
    }

    /**
     * Generates the SQL to return all attribute values for the given entities and attributes.
     * @param array $entityIds
     * @param array $attributes
     * @return string The generated SQL
     */
    protected function getAttributeFillSql($entityIds, $attributes)
    {
        $attributesByType = array();
        foreach ($attributes as $k=>$att) {
            if (!$k) {
                continue;
            }
            if (!$att || !$att['type']) {
                throw new NodeException('Error getting attribute data for '.$k);
            }
            if (!array_key_exists($att['type'], $attributesByType)) {
                $attributesByType[$att['type']] = array();
            }
            $attributesByType[$att['type']][] = $att['attribute_id'];
        }

        $sql = array();

        foreach ($attributesByType as $type=>$atts) {
            $sql[] = 'SELECT v.entity_id as ent_id, v.attribute_id AS att_id, v.value AS value, '.($type == 'multi' ? '`v`.`key` AS `key`' : 'NULL AS `key`').' FROM entity_value_'.$type.' AS v WHERE v.entity_id IN ('.implode(', ', $entityIds).') AND v.attribute_id IN ('.implode(', ', $atts).')';
        }

        $fullSql = implode(' UNION ALL ', $sql);

        return $fullSql;
    }

}

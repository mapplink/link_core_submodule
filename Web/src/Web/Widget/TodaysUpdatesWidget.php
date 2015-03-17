<?php

namespace Web\Widget;

class TodaysUpdatesWidget extends BarWidget {

    /**
     * Should be overridden by child classes to implement data loading.
     *
     * @param array $options
     * @return mixed The loaded data
     */
    protected function _load($options=array()) {
        $exclude = (isset($options['exclude_type']) ? $options['exclude_type'] : array());

        $types = array();
        $etypeRes = $this->getAdapter()->query('SELECT entity_type_id, name_human, name FROM entity_type', \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        foreach($etypeRes as $row){
            if(in_array($row['name'], $exclude)){
                continue;
            }
            $types[$row['entity_type_id']] = $row['name_human'];
        }

        $res = $this->getAdapter()->query('SELECT e.type_id AS type_id, COUNT(*) AS count FROM entity_update AS eu JOIN entity AS e ON e.entity_id = eu.entity_id JOIN entity_update_log AS eul ON eul.log_id = eu.log_id AND eul.timestamp > DATE_SUB(NOW(), INTERVAL 1 DAY) GROUP BY e.type_id', \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        $data = array();
        foreach($res as $row){
            $id = $row['type_id'];
            if(!isset($types[$id])){
                continue;
            }
            $data[$types[$id]] = $row['count'];
        }
        return $data;
    }

    function getTitle() {
        return 'Entites Updated Today';
    }
}
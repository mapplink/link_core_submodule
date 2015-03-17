<?php

namespace Web\Widget;

class EntityTypeWidget extends BarWidget {

    /**
     * Should be overridden by child classes to implement data loading.
     *
     * @return mixed The loaded data
     */
    protected function _load($options=array()) {
        $exclude = (isset($options['exclude']) ? $options['exclude'] : array());

        $types = array();
        $etypeRes = $this->getAdapter()->query('SELECT entity_type_id, name_human, name FROM entity_type', \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        foreach($etypeRes as $row){
            if(in_array($row['name'], $exclude)){
                continue;
            }
            $types[$row['entity_type_id']] = $row['name_human'];
        }

        $res = $this->getAdapter()->query('SELECT type_id, COUNT(*) AS count FROM entity GROUP BY type_id', \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
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
        return 'Entities';
    }
}
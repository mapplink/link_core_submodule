<?php

namespace Magelink\Entity;

use Doctrine\ORM\Mapping as ORM;
use Zend\Db\TableGateway\TableGateway;

abstract class DatadBaseEntity extends DoctrineBaseEntity implements DatadInterface
{

    /**
     * @var string The name of the ID column in the main table
     */
    protected $_simpleDataKey = null;

    /**
     *
     * @var array
     */
    private $_simpleData = null;
    /**
     *
     * @var \Zend\Db\TableGateway
     */
    protected $_simpledataTableGateway = null;

    public function getSimpleData($key = null) {
        if($this->_simpleData === null){
            throw new \Magelink\Exception\MagelinkException('SimpleData not yet loaded!');
        }
        if($key === null){
            return $this->_simpleData;
        }else if(isset($this->_simpleData[$key])){
            return $this->_simpleData[$key];
        }else{
            return null;
        }

    }

    private function convertToCamel($input) {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }

    protected function prepareSimpledataTableGateway(){
        if($this->_simpledataTableGateway != null){
            return;
        }
        $baseTable = $this->convertToCamel(join('', array_slice(explode('\\', get_class($this)), -1)));

        $this->_simpledataTableGateway = new TableGateway($baseTable . '_data', \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter());
    }

    public function loadSimpleData($key=null) {
        if($key == null && $this->_simpleDataKey == null){
            throw new \Magelink\Exception\MagelinkException('No key provided for simple data!');
        }elseif($key == null){
            $key = $this->_simpleDataKey;
        }
        $this->_simpleDataKey = $key;
        $this->prepareSimpledataTableGateway();
        $rs = $this->_simpledataTableGateway->select(array('parent_id'=>$this->{$key}()));
        $this->_simpleData = array();
        foreach($rs as $row){
            $this->_simpleData[$row['key']] = unserialize($row['value']);
        }
    }

    public function saveSimpleData() {
        $this->prepareSimpledataTableGateway();
        $this->_simpledataTableGateway->delete(array('parent_id'=>$this->{$this->_simpleDataKey}()));
        foreach($this->_simpleData as $key=>$val){
            $this->_simpledataTableGateway->insert(array('parent_id'=>$this->{$this->_simpleDataKey}(), 'key'=>$key, 'value'=>serialize($val)));
        }
    }

    public function setAllSimpleData(array $data, $replace = true)
    {
        if($replace || $this->_simpleData === null){
            $this->_simpleData = $data;
        }else{
            $this->_simpleData = array_merge($this->_simpleData, $data);
        }

    }

    public function setSimpleData($key, $value) {
        if($this->_simpleData === null){
            throw new \Magelink\Exception\MagelinkException('Attempting to set simple data on non-loaded model.');
        }
        $this->_simpleData[$key] = $value;

    }

}
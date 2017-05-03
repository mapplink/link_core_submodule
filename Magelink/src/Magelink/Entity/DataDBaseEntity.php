<?php
/**
 * @package Magelink\Entity
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Magelink\Entity;

use Doctrine\ORM\Mapping as ORM;
use Zend\Db\TableGateway\TableGateway;

abstract class DataDBaseEntity extends DoctrineBaseEntity implements DataDInterface
{
    /** * @var string The name of the ID column in the main table */
    protected $_simpleDataKey = NULL;

    /** @var array */
    private $_simpleData = NULL;

    /** @var \Zend\Db\TableGateway */
    protected $_simpledataTableGateway = NULL;


    /**
     * @param null $key
     * @return array|null
     * @throws \Magelink\Exception\MagelinkException
     */
    public function getSimpleData($key = NULL)
    {
        $simpleData = NULL;

        if ($this->_simpleData === NULL) {
            throw new \Magelink\Exception\MagelinkException('SimpleData not yet loaded!');

        }elseif ($key === NULL) {
            $simpleData = $this->_simpleData;

        }elseif (isset($this->_simpleData[$key])) {
            $simpleData = $this->_simpleData[$key];
        }

        return $simpleData;
    }

    /**
     * Convert to camel case
     * @param $input
     * @return string
     */
    private function convertToCamel($input)
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $camelCase = $matches[0];

        foreach ($camelCase as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }

        return implode('_', $camelCase);
    }

    /**
     * Prepares simpledata table gateway
     */
    protected function prepareSimpledataTableGateway()
    {
        if ($this->_simpledataTableGateway == NULL) {
            $baseTable = $this->convertToCamel(join('', array_slice(explode('\\', get_class($this)), -1)));
            $this->_simpledataTableGateway = new TableGateway(
                $baseTable . '_data', \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter()
            );
        }

        return $this->_simpledataTableGateway;
    }

    /**
     * Load simple data in class property
     * @param string|NULL $key
     * @throws \Magelink\Exception\MagelinkException
     */
    public function loadSimpleData($key = NULL)
    {
        if ($key == NULL && $this->_simpleDataKey == NULL) {
            throw new \Magelink\Exception\MagelinkException('No key provided for simple data!');
        }else{
            if ($key == NULL) {
                $key = $this->_simpleDataKey;
            }

            $this->_simpleDataKey = $key;
            $this->prepareSimpledataTableGateway();

            $rows = $this->_simpledataTableGateway->select(array('parent_id'=>$this->{$key}()));
            $this->_simpleData = array();

            foreach ($rows as $row) {
                $this->_simpleData[$row['key']] = unserialize($row['value']);
                // Prevents "Notice: unserialize(): Error at offset"
                //$this->_simpleData[$row['key']] = unserialize(base64_decode($row['value']));
            }
        }
    }

    /**
     * Save simple data stored in simple data class property
     */
    public function saveSimpleData()
    {
        $this->prepareSimpledataTableGateway();
        $this->_simpledataTableGateway->delete(array('parent_id'=>$this->{$this->_simpleDataKey}()));

        foreach ($this->_simpleData as $key=>$value) {
            $this->_simpledataTableGateway->insert(array(
                'parent_id'=>$this->{$this->_simpleDataKey}(),
                'key'=>$key,
                'value'=>serialize($value)
                //'value'=>base64_encode(serialize($value)) // Prevents "Notice: unserialize(): Error at offset"
            ));
        }
    }

    /**
     * @param array $data
     * @param bool  $replace
     */
    public function setAllSimpleData(array $data, $replace = true)
    {
        if($replace || $this->_simpleData === null){
            $this->_simpleData = $data;
        }else{
            $this->_simpleData = array_merge($this->_simpleData, $data);
        }
    }

    /**
     * @param string $key
     * @param string $value
     * @throws \Magelink\Exception\MagelinkException
     */
    public function setSimpleData($key, $value)
    {
        if ($this->_simpleData === NULL) {
            throw new \Magelink\Exception\MagelinkException('Attempting to set simple data on non-loaded model.');
        }
        $this->_simpleData[$key] = $value;

    }

}
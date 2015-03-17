<?php
/**
 * Magelink\Controller
 *
 * @category    Magelink
 * @package     Magelink\Controller
 * @author      Sean Yao <sean@lero9.com>
 * @copyright   Copyright (c) 2014 LERO9 Ltd.
 * @license     Commercial - All Rights Reserved
 */

namespace Web\Helper;


/**
 * ListViewSorter  Class to manage sorting
 */
class ListViewSorter
{ 
    protected
        $config,         // List view config
        $sortedField,    // Sorted field
        $sortedDirection // ASC or DESC
    ;

    /**
     * Constructor
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Get sorted field
     */
    public function getSortedField()
    {
        return $this->sortedField;
    }

    /**
     * Get sorted direction
     * @return [type] [description]
     */
    public function getSortedDirection()
    {
        return $this->sortedDirection;
    }

    /**
     * Check if sorting value valid
     * @param  array $sorting 
     * @return boolean        
     */
    protected function isSortingValueValid($sorting)
    {
        if (
            is_array($sorting)
            && isset($sorting['field'])
            && isset($sorting['direction'])
            && in_array($sorting['direction'], array('asc', 'desc'))
            && array_key_exists($sorting['field'], $this->config)
            && array_key_exists('sortable', $this->config[$sorting['field']])
            && $this->config[$sorting['field']]['sortable']
        ) {
            return true;
        }

        return false;
    }

    /**
     * Process sorting
     * @param  \Doctrine\ORM\QueryBuilder $queryBuilder
     * @param  \Zend\Http\Request $request
     */
    public function processQueryBuilder($queryBuilder, $request)
    {
        if (
            ($sorting = $request->getQuery()->get('sorting'))
            && $this->isSortingValueValid($sorting)

        ) {
            $queryBuilder->orderBy('a.' . lcfirst($sorting['field']) , strtoupper($sorting['direction']));
            $this->sortedField = $sorting['field'];
            $this->sortedDirection = $sorting['direction'];
        }
    }


    /**
     * Process sorting
     * @param  \HOPS\Controller\EAVEntityServiceQuery $query
     * @param  \Zend\Http\Reqeust $request
     */
    public function processEntity($query, $request)
    {
        if (
            ($sorting = $request->getQuery()->get('sorting'))
            && $this->isSortingValueValid($sorting)
        ) {
            $query->set('order', array($sorting['field'] => $sorting['direction']));
            $this->sortedField = $sorting['field'];
            $this->sortedDirection = $sorting['direction'];
        }
    }
}
<?php
/**
 * Class to manage sorting
 * @package Web\Helper
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Web\Helper;


class ListViewSorter
{

    /** @var array $this->config  List view config */
    protected $config;
    /** @var string $this->sortedField  Sorted field */
    protected $sortedField;
    /** @var string $this->sortedDirection  ASC or DESC */
    protected $sortedDirection;


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
     * @param $query
     * @param \Zend\Http\Request $request
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

<?php

namespace Web\Helper;

use Doctrine\ORM\Tools\Pagination\Paginator as BasePaginator;

/**
 * Custom paginator for doctrine
 */
class Paginator extends BasePaginator
{  
    protected 
        $page         = null,   // Current page
        $routeName    = null,   // Route name
        $routeParams  = array(), // Route params
        $routeQueries = array() // Route query
    ;

    /**
     * Constructor
     * @param \Doctrine\ORM\Query|\Doctrine\ORM\QueryBuilder $query               A Doctrine ORM query or query builder.
     * @param int                $maxResultsPerPage
     * @param boolean            $fetchJoinCollection Whether the query joins a collection (true by default).
     */
    public function __construct($query, $maxResultsPerPage, $fetchJoinCollection = true)
    {
        $this->maxResultsPerPage = $maxResultsPerPage;
        $query->setMaxResults($maxResultsPerPage);
        return parent::__construct($query, $fetchJoinCollection);
    }

    /**
     * Set current page
     *
     * @param int $page
     * @return $this
     */
    public function setPage($page)
    {
        $this->page = max(1, (int) $page);
        $this->getQuery()->setFirstResult($this->getQuery()->getMaxResults() * ($this->page - 1));

        return $this;
    }

    /**
     * Get current page
     * @return int
     */
    public function getPage() 
    {
        return $this->page;
    }

    /**
     * Get total number of pages
     * @return int
     */
    public function getPageTotal()
    {
        return (int) ceil($this->count() / $this->getQuery()->getMaxResults());
    }

    /**
     * Merge route params
     * @param array $params
     * @return $this
     */
    public function mergeRouteParams(array $params) 
    {
        $this->routeParams = array_merge($this->routeParams, $params);

        return $this;
    }

    /**
     * Get route params
     * @return array
     */
    public function getRouteParams() 
    {
        return $this->routeParams;
    }

    /**
     * Set route name
     * @param string $routeName
     * @return $this
     */
    public function setRouteName($routeName)
    {
        $this->routeName = $routeName;

        return $this;
    }

    /**
     * Get route name
     * @return string
     */
    public function getRouteName()
    {
        return $this->routeName;
    }

    /**
     * Set route parameter
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function setRouteParam($key, $value)
    {
        $this->routeParams[$key] = $value;

        return $this;
    }

    /**
     * Set route query
     * @param array $query
     * @return $this
     */
    public function setRouteQueries(array $queries)
    {
        $this->routeQueries = $queries;
        
        return $this;
    }

    /**
     * Get route query
     * @return $this
     */
    public function getRouteQueries()
    {
        return $this->routeQueries;
    }
}
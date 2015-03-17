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

use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Web\Form\BaseSearchFilterForm;

/**
 * CRUDRouteControl  Class to manage crude routes
 */
class CRUDSearchFilter extends BaseSearchFilter 
{   

    /**
     * Constructor
     * @param array  $config          
     * @param string $namespaceSuffix -- session name suffix
     */
    public function __construct($config, $namespaceSuffix)
    {
        $this->config           = $config;
        $this->sessionNamespace = 'CRUDSearchFilter\\Config\\' . $namespaceSuffix;
        $this->form = new BaseSearchFilterForm($config);

    }

    protected function addToQuery($query, $field, $operator, $value, $count)
    {
        switch ($operator) {
            case 'equals' :
            case '=' :
                $operatorSymbol = '=';
                break;
            case 'contains' :
                $operatorSymbol = 'like';
                $value          = '%' . $value . '%';
                break;
            case 'Yes' :
                $operatorSymbol = '=';
                $value          = '1';
                break;
            case 'No' :
                $operatorSymbol = '=';
                $value          = '0';
                break;
            case '>' :
                $operatorSymbol = '>';
                break;
            case '<' :
                $operatorSymbol = '<';
                break;
            default:
                $operatorSymbol = null;
                break;
        }

        if ($operatorSymbol) {
            $query->andWhere('a.' . $field . ' ' . $operatorSymbol . ' ?' . $count)
                ->setParameter($count, $value);
        }
        
    }

  


    /**
     * Build the view of the filter box based on config and submitted data
     * @param  boolean $isFilterOn -- indicate if the filter is in use
     * @return \Zend\View\Model\ViewModel
     */
    public function buildView($isFilterOn)
    {
        $sidebarBlockView = new ViewModel(array(
            'form'        => $this->form,
            'isFilterOn'  => $isFilterOn,
        ));
        $sidebarBlockView->setTemplate('web/admin/filter/box');

        return $sidebarBlockView;
    }
}
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

/**
 * BaseSearchFilter  Class to manage the searches
 */
class BaseSearchFilter 
{
    /** @var array  */
    protected $config = array();

    /** @var bool */
    protected $isFilterOn = false;

    /** @var string The name to be used for the session namespace */
    protected $sessionNamespace;

    /** @var object */
    protected $form;

    protected function getSesstion()
    {
        return $session = new Container($this->sessionNamespace);
    }

    /**
     * @return array|null Retrieves the data from the session
     */
    public function getData()
    {
        $session = $this->getSesstion();
        
        return $session->offsetGet('data');
    }

    /**
     * Process filters data
     * @param  object $query
     * @param  \Zend\Http\Request $request
     */
    public function process($query, $request)
    {
        $isFilterOn = false;

        // Get the post data and store in session
        $session = $this->getSesstion();

        $data = $request->getQuery();

        if ($data->get('searchForm')) {
            if ($data->get('submit') == 'Reset') {
                $session->offsetSet('data', null);
            } else {
                $session->offsetSet('data', $data);
            }
        }


        // Loop through the data and apply the filters
        if ($data = $session->offsetGet('data')) {
            $this->form->updateFormValues($data);

            $count = 1;
            foreach ($this->config as $name => $config) {


                if (
                    ($field = $this->form->get($name . '[value]'))
                    && (
                        (($value = $field->getValue()) && ($value = trim($value)))
                        || (isset($config['valuetype']) && ($config['valuetype'] == 'Hidden'))
                    )
                    && ($operatorField = $this->form->get($name . '[operator]'))
                    && ($operator = $operatorField->getValue())
                ) {

                    $matches = array();
                    if (isset($config['valuetype']) && ($config['valuetype'] == 'Datetime')) {
                        preg_match('%(\d{2})/(\d{2})/(\d{4}) (\d{2}:\d{2}:\d{2})%', $value, $matches);
                        if (count($matches) == 5) {
                            list($whole, $day, $month, $year, $time) = $matches;
                        } else {
                            continue;
                        }

                        $value = sprintf('%s-%s-%s %s', $year, $month, $day, $time);
                    }

                    if (isset($config['isOperatorValue']) && $config['isOperatorValue']) {
                        if (in_array($operator, $this->getOperatorValues($config))) {
                            $value    = $operator;
                            $operator = 'equals';
                        }
                    }

                    $this->addToQuery($query, $config['field'], $operator, $value, $count);
                    if (isset($config['fkey'])) {
                        $query->set('fkey', $config['fkey']);
                    }

                    $isFilterOn = true;
                }
                
                $count++;
            }
        }
        
        $this->isFilterOn = $isFilterOn;
    }

    /**
     * Get operator values base on config
     * @param  array $config
     * @return array
     */
    protected function getOperatorValues($config)
    {
        if (isset($config['operatorsKeyAsValue']) && $config['operatorsKeyAsValue']) {
            return array_keys($config['operators']);
        } else {
            return $config['operators'];
        }
    }

    /**
     * Check if filter is on
     * @return boolean
     */
    public function isFilterOn()
    {
        return $this->isFilterOn;
    }

}
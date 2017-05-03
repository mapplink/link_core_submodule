<?php
/**
 * @package Web\Controller
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Web\Controller;

use Zend\View\Model\ViewModel;
use Magelink\Exception\MagelinkException;


class QueryController extends BaseController
{

    /**
     * Perform some very basic checks to ensure that query does not contain invalid contents.
     * @param string $query
     * @return bool $isValidQuery
     */
    protected function checkQuery($query)
    {
        $pattern = '/INSERT |UPDATE |DELETE | user |REPLACE |SET |GRANT |--|; |EXECUTE |RUN |CREATE |USE | log_/i';
        $isValidQuery = !preg_match($pattern, $query);

        return $isValidQuery;
    }

    /**
     * Executes an EXPLAIN on the provided query
     * @param $query
     * @return \Zend\Db\Adapter\Driver\StatementInterface|\Zend\Db\ResultSet\ResultSet
     */
    protected function explainQuery($query){
        try{
            return $this->getAdapter()->query('EXPLAIN ' . $query, \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        }catch(\Exception $e){
            return array();
        }
    }

    public function indexAction()
    {
        $query = trim($this->params()->fromPost('query', ''));

        $headers = false;
        $result = false;
        $parsedQuery = false;
        $error = false;
        $explain = false;
        $explainHeaders = false;

        if(strlen($query)){
            /** @var \Entity\Service\EntityService $entityService */
            $entityService = $this->getServiceLocator()->get('entityService');

            try{
                $parsedQuery = $entityService->parseQuery($query);
            }catch(\Exception $e){
                $error = $e->getMessage();
            }

            if(!$error && (!$this->checkQuery($parsedQuery) || !$this->checkQuery($query))){
                $error = 'Invalid query passed';
            }

            /** @var \Zend\Db\ResultSet\ResultSet $explain */
            $explain = $this->explainQuery($parsedQuery);
            if($explain){
                $explain = $explain->toArray();
                if(count($explain)){
                    $explainHeaders = array_keys($explain[0]);
                }
            }

            if(!$error){
                try{
                    $result = $entityService->executeQuery($query);
                    if(!count($result)){
                        $headers = array();
                    }else{
                        $row1 = $result[0];
                        $headers = array_keys($row1);
                    }
                }catch(\Exception $e){
                    $error = $e->getMessage();
                    $result = array();
                    $headers = array();
                }
            }

        }

        return new ViewModel(array(
            'query'=>$query,
            'headers'=>$headers,
            'result'=>$result,
            'parsedQuery'=>$parsedQuery,
            'explain'=>$explain,
            'explainHeaders'=>$explainHeaders,
            'error'=>$error,
        ));
    }

}

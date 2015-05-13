<?php
/**
 * Web\Controller\CRUD
 * @category    Web
 * @package     Web\Controller
 * @author      Andreas Gerhards <andreas@lero9.com>
 * @copyright   Copyright (c) 2014 LERO9 Ltd.
 * @license     Commercial - All Rights Reserved
 */

namespace Web\Controller\CRUD;

use Application\CronRunnable;
use Application\Controller\Cron;
use Application\Helper\ErrorHandler;
use Application\Service\ApplicationConfigService;
use Magelink\Entity\Config;
use Magelink\Exception\MagelinkException;
use Web\Controller\CRUD\AbstractCRUDController;
use Web\Form\ConfigForm;
use Zend\View\Model\ViewModel;


class CronjobAdminController extends AbstractCRUDController
{

    /**
     * Child classes should override to return the Entity class name that this CRUD controller works on.
     * @return string
     */
    protected function getEntityClass()
    {
        return NULL;
    }


    protected function setDefaultName()
    {
        $this->name = 'Cronjob monitor dashboard - Please use only in an important emergency situation -';
    }

    /**
     * Child classes can override to return whether or not this CRUD controller supports creating entities
     * @return boolean
     */
    protected function getEnableCreate()
    {
        return FALSE;
    }

    /**
     * Child classes can override to return whether or not this CRUD controller supports deleting entities
     * @return boolean
     */
    protected function getEnableDelete()
    {
        return FALSE;
    }

    /**
     * Get paginator for list view
     * @return object
     */
    protected function getPaginator()
    {
        /** @var ApplicationConfigService $applicationConfigService */
        $applicationConfigService = $this->getServiceLocator()->get('applicationConfigService');

        $paginator = array();
        /** @var Cronrunnable $cron */
        foreach ($applicationConfigService->getCronjobs() as $name=>$cron) {
            if (strpos($name, '_tester') === FALSE) {
                $interval = $cron->getInterval();
                if (is_int($interval)) {
                   $interval = 'every '.$interval.' minutes';
                }elseif (!is_string($interval)) {
                    $interval = 'undefined';
                }

                if ($cron->checkIfUnlocked()) {
                    $since = $unlockAction = '-';
                    $status = 'unlocked';
                }else{
                    $since = date('d/m, H:i:s', $cron->lockedSince());
                    $status = 'LOCKED';

                    if ($cron->canAdminUnlock()) {
                        $unlockAction = '<form method="POST" action="#" class="form-inline">'
                            .'<a href="/cronjob-admin/edit/'.$name.'" class="btn btn-danger">Unlock now</a>'
                            .'</form>';
                    }else{
                        $lockedMinutes = ceil($cron->getAdminLockedSeconds() / 60);
                        $unlockAction = 'Cannot be manually unlocked for the next '.$lockedMinutes.' minute'
                            .($lockedMinutes > 1 ? 's' : '');
                    }
                }

                $entry = new \stdClass();
                $entry->name = $name;
                $entry->interval = $interval;
                $entry->status = $status;
                $entry->since = $since;
                $entry->unlockAction = $unlockAction;
                $paginator[$name] = $entry;
            }
        }

        return $paginator;
    }

    /**
     * Set list view config
     * @return array
     */
    protected function getListViewConfig()
    {
        return array(
            'Synchronisation process'=>array('getValue'=>'name'),
            'Interval'=>array('getValue'=>'interval'),
            'Status'=>array('getValue'=>'status'),
            'Since'=>array('getValue'=>'since'),
            'File'=>array('getValue'=>'unlockAction', 'raw'=>TRUE)
        );
    }

    /**
     * Edit/unlock action
     * @return mixed|\Zend\Http\Response|ViewModel
     */
    public function editAction()
    {
        if (!$this->getEnableEdit()) {
            $this->getResponse()->setStatusCode(404);
            return;
        }else {
            /** @var ApplicationConfigService $applicationConfigService */
            $applicationConfigService = $this->getServiceLocator()->get('applicationConfigService');

            $code = $this->params('id');
            $cron = $applicationConfigService->getCronjob($code);

            if ($cron instanceof CronRunnable) {
                $success = $cron->adminReleaseLock();
            }else{
                $success = FALSE;
            }

            if ($success) {
                $message = 'Successfully unlocked cronjob '.$code.'.';
                $this->flashMessenger()->setNamespace('success')->addMessage($message);
            }else {
                $message = 'Failed to unlock cronjob '.$code.'. Please contact Lero9 for assistance.';
                $this->flashMessenger()->setNamespace('error')->addMessage($message);
            }

            return $this->redirect()->toRoute($this->getRouteGenerator()->getRouteName('list'));
        }
    }

}

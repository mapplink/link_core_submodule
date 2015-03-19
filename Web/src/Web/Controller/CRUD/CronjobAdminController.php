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
use Magelink\Entity\Config;
use Magelink\Exception\MagelinkException;
use Web\Controller\CRUD\AbstractCRUDController;
use Web\Form\ConfigForm;
use Zend\Mvc\Controller\AbstractActionController;
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
        $paginator = array();
        /** @var Cronrunnable $cronjob */
        foreach ($this->getCronjobs() as $name=>$cronjob) {
            if (strpos($name, '_tester') === FALSE) {
                $interval = $cronjob->getInterval();
                if (is_int($interval)) {
                   $interval = 'every '.$interval.' minutes';
                }elseif (!is_string($interval)) {
                    $interval = 'undefined';
                }

                $unlocked = Cron::checkIfUnlocked($name);
                if ($unlocked) {
                    $since = $unlockAction = '-';
                }else{
                    $lockTimestamp = Cron::lockedSince($name);
                    $since = date('H:i:s d-m-Y', $lockTimestamp);

                    $lockedSeconds = $lockTimestamp + $cronjob->getLockTime() * 60 - time();
                    if ($lockedSeconds <= 0) {
                        $unlockAction = '<form method="POST" action="#" class="form-inline">'
                            .'<a href="/cronjob-admin/edit/'.$name.'" class="btn btn-danger">Unlock now</a>'
                            .'</form>';
                    }else{
                        $lockedMinutes = ceil($lockedSeconds / 60);
                        $unlockAction = 'Cannot be manually unlocked for the next '.$lockedMinutes.' minute'
                            .($lockedMinutes > 1 ? 's' : '');
                    }
                }

                $entry = new \stdClass();
                $entry->name = $name;
                $entry->interval = $interval;
                $entry->status = $unlocked ? 'unlocked' : 'LOCKED';
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
        }

        $code = $this->params('id');
        $user = $this->getServiceLocator()->get('zfcuser_auth_service')->getIdentity();

        if (!is_writable(Cron::LOCKS_DIRECTORY)) {
            $message = 'Failed to unlock cronjob '.$code.'. Please contact Lero9 for assistance.';
            $this->flashMessenger()->setNamespace('error')->addMessage($message);
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_ERROR,
                    'cron_unlock_fail_'.$code,
                    'Unlock failed on cron job '.$code.'. Directory not writable.',
                    array('cron job'=>$code, 'directory'=>realpath(Cron::LOCKS_DIRECTORY), 'user id'=>$user->getId())
                );
        }else{
            $fileName = Cron::getLockFileName($code);
            unlink($fileName);
            $fileName = realpath($fileName);

            $message = 'Successfully unlocked cronjob '.$code.'.';
            $this->flashMessenger()->setNamespace('success')->addMessage($message);

            $subject = 'cron_unlock_'.$code;
            $message .= '('.$fileName.'). User '.$user->getId().'.';
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_DEBUG,
                    $subject,
                    $message,
                    array('cron job'=>$code, 'file name'=>$fileName, 'user id'=>$user->getId())
                );
            mail(ErrorHandler::ERROR_TO, $subject, $message, 'From: ' . ErrorHandler::ERROR_FROM);
        }

        return $this->redirect()->toRoute($this->getRouteGenerator()->getRouteName('list'));
    }

}

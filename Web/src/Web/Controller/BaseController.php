<?php
/**
 * BaseController
 * @category Application
 * @package Application\Controller
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.com>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Web\Controller;

use Zend\Mvc\Controller\AbstractActionController;


abstract class BaseController extends AbstractActionController
{

    /**
     * Get Doctrine repository
     * @param string $entityName
     * @return mixed
     */
    public function getRepo($entityName)
    {
        return $this->getEntityManager()
            ->getRepository($entityName);
    }

    /**
     * Get Doctrine EntityManager
     * @return object
     */
    public function getEntityManager()
    {
        return $this->getServiceLocator()
            ->get('Doctrine\ORM\EntityManager');
    }

    /**
     * Get current logged in user
     * @return \Magelink\Entity\User
     */
    public function getCurrentUser()
    {
        return $this->zfcUserAuthentication()->getIdentity();
    }

    /**
     * Persist an entity to DB
     * @param  Object $entity 
     * @return 
     */
    public function persistEntity($entity)
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($entity);
        $entityManager->flush($entity);
    }

    /**
     * Delete an entity to DB
     * @param  Object $entity 
     * @return 
     */
    public function removeEntity($entity)
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($entity);
        $entityManager->flush($entity);
    }

    /**
     * Load mailer
     * 
     * @param  string $name 
     * @param  array  $args 
     * @return 
     */
    public function loadMailer($name, $args = array())
    {
        return $this->getServiceLocator()
            ->get('Email\Service\MailService')
            ->loadMailer($name, $args);
    }

    /**
     * Return the database adapter to be used to communicate with Entity storage.
     * @return \Zend\Db\Adapter\Adapter
     */
    protected function getAdapter()
    {
        return $this->getServiceLocator()->get('zend_db');
    }
}
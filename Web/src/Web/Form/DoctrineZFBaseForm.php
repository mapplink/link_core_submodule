<?php
/**
 * Base form for Doctrine ORM
 * @category Magelink
 * @package Magelink\Form
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Web\Form;

use Zend\Form\Form;

use DoctrineModule\Stdlib\Hydrator\DoctrineObject;

class DoctrineZFBaseForm extends Form
{

    /** @var \Doctrine\ORM\EntityManager $this->entityManager */
    protected $entityManager;


    /**
     * Constructor
     * @param \Doctrine\ORM\EntityManager $entityManager
     * @param string $name
     */
    public function __construct(\Doctrine\ORM\EntityManager $entityManager,  $name = null)
    {
        parent::__construct($name);

        $this->entityManager = $entityManager;
        $hydrator = new DoctrineObject($entityManager, '\Magelink\Entity\User');
        $this->setHydrator($hydrator);

    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @return bool Always true
     */
    public function save()
    {
        $object = $this->getObject();
        $entityManager = $this->getEntityManager();
        $entityManager->persist($object);
        $entityManager->flush($object);

        return true;
    }

}

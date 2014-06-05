<?php

namespace Entity;

use \Zend\ServiceManager\ServiceLocatorAwareInterface;
use \Zend\ServiceManager\ServiceLocatorInterface;
use \Entity\Service\EntityService;

class Comment implements ServiceLocatorAwareInterface {

    /** @var Entity */
    protected $_entity;
    /** @var int */
    protected $_commentId;
    /** @var string */
    protected $_referenceId;
    /** @var string */
    protected $_timestamp;
    /** @var string */
    protected $_source;
    /** @var string */
    protected $_title;
    /** @var string */
    protected $_body;
    /** @var boolean */
    protected $_customerVisible;

    /**
     * Construct a new Comment object from DB data
     * @param Entity $entity
     * @param array $row
     */
    public function __construct(Entity $entity, array $row){
        $this->_entity = $entity;
        $this->_commentId = $row['comment_id'];
        $this->_referenceId = $row['reference_id'];
        $this->_timestamp = $row['timestamp'];
        $this->_source = $row['source'];
        $this->_title = $row['title'];
        $this->_body = $row['body'];
        $this->_customerVisible = ($row['customer_visible'] == 1 ? true : false);
    }

    /**
     * Get the Entity this comment is attached to
     * @return Entity
     */
    public function getEntity(){
        return $this->_entity;
    }

    /**
     * Get the internal identifier of the comment
     * @return int
     */
    public function getCommentId(){
        return $this->_commentId;
    }

    /**
     * Get the reference ID of the comment
     * @return string
     */
    public function getReferenceId(){
        return $this->_referenceId;
    }

    /**
     * Get the timestamp this comment was initially added
     * @return string
     */
    public function getTimestamp(){
        return $this->_timestamp;
    }

    /**
     * Get the source description of the comment (i.e. user name, automated process, etc)
     * @return string
     */
    public function getSource(){
        return $this->_source;
    }

    /**
     * Get the comment title
     * @return string
     */
    public function getTitle(){
        return $this->_title;
    }

    /**
     * Get the comment body
     * @return string
     */
    public function getBody(){
        return $this->_body;
    }

    /**
     * Get whether this comment is intended to be visible to customers
     * @return bool
     */
    public function getCustomerVisible(){
        return $this->_customerVisible;
    }

    /**
     * @var ServiceLocatorInterface The service locator
     */
    protected $_serviceLocator;

    /**
     * Set service locator
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->_serviceLocator = $serviceLocator;
    }

    /**
     * Get service locator
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->_serviceLocator;
    }

}
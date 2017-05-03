<?php
/**
 * @package Log\Entity
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Log\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LogEntry
 *
 * @ORM\Table(name="log_entry", indexes={@ORM\Index(name="node_id_idx", columns={"node_id"}), @ORM\Index(name="entity_id_idx", columns={"entity_id"}), @ORM\Index(name="router_filter_id_idx", columns={"router_filter_id"}), @ORM\Index(name="user_id_idx", columns={"user_id"}), @ORM\Index(name="module_class", columns={"module", "class"}), @ORM\Index(name="timestamp_idx", columns={"timestamp"})})
 * @ORM\Entity(repositoryClass="Log\Repository\LogEntryRepository")
 */
class LogEntry extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="log_id", type="bigint", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $logId;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="timestamp", type="datetime", nullable=false)
     */
    private $timestamp = 'CURRENT_TIMESTAMP';

    /**
     * @var string
     *
     * @ORM\Column(name="level", type="string", length=10, nullable=false)
     */
    private $level;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=45, nullable=false)
     */
    private $code;

    /**
     * @var string
     *
     * @ORM\Column(name="module", type="string", length=45, nullable=false)
     */
    private $module;

    /**
     * @var string
     *
     * @ORM\Column(name="class", type="string", length=254, nullable=false)
     */
    private $class;

    /**
     * @var string
     *
     * @ORM\Column(name="message", type="string", length=254, nullable=false)
     */
    private $message;

    /**
     * @var string
     *
     * @ORM\Column(name="data", type="string", length=1024, nullable=true)
     */
    private $data;

    /**
     * @var integer
     *
     * @ORM\Column(name="entity_id", type="integer", nullable=true)
     **/
    private $entityId;

    /**
     * @var \Node\Entity\Node
     *
     * @ORM\ManyToOne(targetEntity="Node\Entity\Node")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="node_id", referencedColumnName="node_id")
     * })
     */
    private $node;

    /**
     * @var \Router\Entity\RouterFilter
     *
     * @ORM\ManyToOne(targetEntity="Router\Entity\RouterFilter")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="router_filter_id", referencedColumnName="filter_id")
     * })
     */
    private $routerFilter;

    /**
     * @var \Magelink\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="Magelink\Entity\User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="user_id", referencedColumnName="user_id")
     * })
     */
    private $user;


    /**
     * Get Id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->getLogId();
    }

    /**
     * Get logId
     *
     * @return integer 
     */
    public function getLogId()
    {
        return $this->logId;
    }

    /**
     * Set timestamp
     *
     * @param \DateTime $timestamp
     * @return LogEntry
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Get timestamp
     *
     * @return \DateTime 
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Set level
     *
     * @param string $level
     * @return LogEntry
     */
    public function setLevel($level)
    {
        $this->level = $level;

        return $this;
    }

    /**
     * Get level
     *
     * @return string 
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * Set code
     *
     * @param string $code
     * @return LogEntry
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code
     *
     * @return string 
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set module
     *
     * @param string $module
     * @return LogEntry
     */
    public function setModule($module)
    {
        $this->module = $module;

        return $this;
    }

    /**
     * Get module
     *
     * @return string 
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * Set class
     *
     * @param string $class
     * @return LogEntry
     */
    public function setClass($class)
    {
        $this->class = $class;

        return $this;
    }

    /**
     * Get class
     *
     * @return string 
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Set message
     *
     * @param string $message
     * @return LogEntry
     */
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Get message
     *
     * @return string 
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set data
     *
     * @param string $data
     * @return LogEntry
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get data
     *
     * @return string 
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set entityId
     *
     * @param integer $entityId
     * @return LogEntry
     */
    public function setEntityId($entityId)
    {
        $this->entityId = $entityId;

        return $this;
    }

    /**
     * Get entityId
     *
     * @return integer 
     */
    public function getEntityId()
    {
        return $this->entityId;
    }

    /**
     * Set node
     *
     * @param \Node\Entity\Node $node
     * @return LogEntry
     */
    public function setNode(\Node\Entity\Node $node = null)
    {
        $this->node = $node;

        return $this;
    }

    /**
     * Get node
     *
     * @return \Node\Entity\Node 
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * Set routerFilter
     *
     * @param \Router\Entity\RouterFilter $routerFilter
     * @return LogEntry
     */
    public function setRouterFilter(\Router\Entity\RouterFilter $routerFilter = null)
    {
        $this->routerFilter = $routerFilter;

        return $this;
    }

    /**
     * Get routerFilter
     *
     * @return \Router\Entity\RouterFilter 
     */
    public function getRouterFilter()
    {
        return $this->routerFilter;
    }

    /**
     * Set user
     *
     * @param \Magelink\Entity\User $user
     * @return LogEntry
     */
    public function setUser(\Magelink\Entity\User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \Magelink\Entity\User 
     */
    public function getUser()
    {
        return $this->user;
    }
}

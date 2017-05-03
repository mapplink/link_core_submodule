<?php
/**
 * @package Magelink\Entity
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Magelink\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Config
 *
 * @ORM\Table(name="config")
 * @ORM\Entity(repositoryClass="Magelink\Repository\ConfigRepository")
 */
class Config extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="config_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $configId;

    /**
     * @var string
     *
     * @ORM\Column(name="module", type="string", length=45, nullable=true)
     */
    private $module;

    /**
     * @var string
     *
     * @ORM\Column(name="human_name", type="string", length=254, nullable=true)
     */
    private $humanName;

    /**
     * @var string
     *
     * @ORM\Column(name="`key`", type="string", length=254, nullable=false)
     */
    private $key;

    /**
     * @var string
     *
     * @ORM\Column(name="value", type="string", length=254, nullable=false)
     */
    private $value;

    /**
     * @var string
     *
     * @ORM\Column(name="default_value", type="string", length=254, nullable=false)
     */
    private $defaultValue;



    /**
     * Get configId
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->getConfigId();
    }

    public function getConfigId()
    {
        return $this->configId;
    }

    /**
     * Set module
     *
     * @param string $module
     * @return Config
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
     * Set humanName
     *
     * @param string $humanName
     * @return Config
     */
    public function setName($humanName)
    {
        return $this->setHumanName($humanName);
    }

    public function setHumanName($humanName)
    {
        $this->humanName = $humanName;

        return $this;
    }

    /**
     * Get humanName
     *
     * @return string 
     */
    public function getName()
    {
        return $this->getHumanName();
    }

    public function getHumanName()
    {
        return $this->humanName;
    }

    /**
     * Set key
     *
     * @param string $key
     * @return Config
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Get key
     *
     * @return string 
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Set value
     *
     * @param string $value
     * @return Config
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value
     *
     * @return string 
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set defaultValue
     *
     * @param string $defaultValue
     * @return Config
     */
    public function setDefault($defaultValue)
    {
        return $this->setDefaultValue($defaultValue);
    }

    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    /**
     * Get defaultValue
     *
     * @return string 
     */
    public function getDefault()
    {
        return $this->getDefaultValue();
    }

    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

}

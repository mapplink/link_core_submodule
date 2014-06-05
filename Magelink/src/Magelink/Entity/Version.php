<?php

namespace Magelink\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Version
 *
 * @ORM\Table(name="version")
 * @ORM\Entity(repositoryClass="Magelink\Repository\VersionRepository")
 *
 */
class Version extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var string
     *
     * @ORM\Column(name="module", type="string", length=45, nullable=false)
     * @ORM\Id
     */
    private $module;

    /**
     * @var string
     *
     * @ORM\Column(name="version", type="decimal", precision=10, scale=0, nullable=false)
     */
    private $version;


    /**
     * Set module
     *
     * @return Version 
     */
    public function setModule($module)
    {
        $this->module = $module;
        
        return;  
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
     * Set version
     *
     * @param string $version
     * @return Version
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Get version
     *
     * @return string 
     */
    public function getVersion()
    {
        return $this->version;
    }
}

<?php

namespace Report\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ReportType
 *
 * @ORM\Table(name="report_type")
 * @ORM\Entity(repositoryClass="Report\Repository\ReportTypeRepository")
 */
class ReportType extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="type_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $typeId;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=254, nullable=false)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="backend_class", type="string", length=254, nullable=false)
     */
    private $backendClass;

    /**
     * @var string
     *
     * @ORM\Column(name="frontend_class", type="string", length=254, nullable=false)
     */
    private $frontendClass;

    /**
     * @var string
     *
     * @ORM\Column(name="generator_backend_class", type="string", length=254, nullable=false)
     */
    private $generatorBackendClass;

    /**
     * @var string
     *
     * @ORM\Column(name="generator_frontend_class", type="string", length=254, nullable=false)
     */
    private $generatorFrontendClass;



    /**
     * Get typeId
     *
     * @return integer 
     */
    public function getTypeId()
    {
        return $this->typeId;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return ReportType
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set backendClass
     *
     * @param string $backendClass
     * @return ReportType
     */
    public function setBackendClass($backendClass)
    {
        $this->backendClass = $backendClass;

        return $this;
    }

    /**
     * Get backendClass
     *
     * @return string 
     */
    public function getBackendClass()
    {
        return $this->backendClass;
    }

    /**
     * Set frontendClass
     *
     * @param string $frontendClass
     * @return ReportType
     */
    public function setFrontendClass($frontendClass)
    {
        $this->frontendClass = $frontendClass;

        return $this;
    }

    /**
     * Get frontendClass
     *
     * @return string 
     */
    public function getFrontendClass()
    {
        return $this->frontendClass;
    }

    /**
     * Set generatorBackendClass
     *
     * @param string $generatorBackendClass
     * @return ReportType
     */
    public function setGeneratorBackendClass($generatorBackendClass)
    {
        $this->generatorBackendClass = $generatorBackendClass;

        return $this;
    }

    /**
     * Get generatorBackendClass
     *
     * @return string 
     */
    public function getGeneratorBackendClass()
    {
        return $this->generatorBackendClass;
    }

    /**
     * Set generatorFrontendClass
     *
     * @param string $generatorFrontendClass
     * @return ReportType
     */
    public function setGeneratorFrontendClass($generatorFrontendClass)
    {
        $this->generatorFrontendClass = $generatorFrontendClass;

        return $this;
    }

    /**
     * Get generatorFrontendClass
     *
     * @return string 
     */
    public function getGeneratorFrontendClass()
    {
        return $this->generatorFrontendClass;
    }
}

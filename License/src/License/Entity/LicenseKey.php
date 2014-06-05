<?php

namespace License\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LicenseKey
 *
 * @ORM\Table(name="license_key", indexes={@ORM\Index(name="code_id_idx", columns={"code_id"})})
 * @ORM\Entity(repositoryClass="License\Repository\LicenseKeyRepository")
 */
class LicenseKey extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="key_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $keyId;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=45, nullable=false)
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(name="value", type="text", nullable=false)
     */
    private $value;

    /**
     * @var \License\Entity\LicenseCode
     *
     * @ORM\ManyToOne(targetEntity="License\Entity\LicenseCode")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="code_id", referencedColumnName="code_id")
     * })
     */
    private $code;



    /**
     * Get keyId
     *
     * @return integer 
     */
    public function getKeyId()
    {
        return $this->keyId;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return LicenseKey
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string 
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set value
     *
     * @param string $value
     * @return LicenseKey
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
     * Set code
     *
     * @param \License\Entity\LicenseCode $code
     * @return LicenseKey
     */
    public function setCode(\License\Entity\LicenseCode $code = null)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code
     *
     * @return \License\Entity\LicenseCode 
     */
    public function getCode()
    {
        return $this->code;
    }
}

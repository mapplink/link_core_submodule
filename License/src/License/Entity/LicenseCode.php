<?php
/**
 * @package License\Entity
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace License\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LicenseCode
 *
 * @ORM\Table(name="license_code")
 * @ORM\Entity(repositoryClass="License\Repository\LicenseCodeRepository")
 */
class LicenseCode extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="code_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $codeId;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="text", nullable=true)
     */
    private $code;

    /**
     * @var boolean
     *
     * @ORM\Column(name="verified", type="boolean", nullable=false)
     */
    private $verified = '0';

    /**
     * @var string
     *
     * @ORM\Column(name="signature", type="string", length=254, nullable=false)
     */
    private $signature;



    /**
     * Get codeId
     *
     * @return integer 
     */
    public function getCodeId()
    {
        return $this->codeId;
    }

    /**
     * Set code
     *
     * @param string $code
     * @return LicenseCode
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
     * Set verified
     *
     * @param boolean $verified
     * @return LicenseCode
     */
    public function setVerified($verified)
    {
        $this->verified = $verified;

        return $this;
    }

    /**
     * Get verified
     *
     * @return boolean 
     */
    public function getVerified()
    {
        return $this->verified;
    }

    /**
     * Set signature
     *
     * @param string $signature
     * @return LicenseCode
     */
    public function setSignature($signature)
    {
        $this->signature = $signature;

        return $this;
    }

    /**
     * Get signature
     *
     * @return string 
     */
    public function getSignature()
    {
        return $this->signature;
    }

}

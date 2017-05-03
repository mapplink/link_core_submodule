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
 * LicenseAbility
 *
 * @ORM\Table(name="license_ability", indexes={@ORM\Index(name="code_id_idx", columns={"code_id"})})
 * @ORM\Entity(repositoryClass="License\Repository\LicenseAbilityRepository")
 */
class LicenseAbility extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="ability_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $abilityId;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=254, nullable=false)
     */
    private $type;

    /**
     * @var integer
     *
     * @ORM\Column(name="limit", type="bigint", nullable=false)
     */
    private $limit;

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
     * Get abilityId
     *
     * @return integer 
     */
    public function getAbilityId()
    {
        return $this->abilityId;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return LicenseAbility
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
     * Set limit
     *
     * @param integer $limit
     * @return LicenseAbility
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Get limit
     *
     * @return integer 
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Set code
     *
     * @param \License\Entity\LicenseCode $code
     * @return LicenseAbility
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

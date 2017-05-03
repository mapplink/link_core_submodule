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
 * LicenseBlock
 *
 * @ORM\Table(name="license_block", indexes={@ORM\Index(name="key_id_idx", columns={"key_id"})})
 * @ORM\Entity(repositoryClass="License\Repository\LicenseBlockRepository")
 */
class LicenseBlock extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="block_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $blockId;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=45, nullable=false)
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(name="encrypted", type="blob", nullable=false)
     */
    private $encrypted;

    /**
     * @var \License\Entity\LicenseKey
     *
     * @ORM\ManyToOne(targetEntity="License\Entity\LicenseKey")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="key_id", referencedColumnName="key_id")
     * })
     */
    private $key;



    /**
     * Get blockId
     *
     * @return integer 
     */
    public function getBlockId()
    {
        return $this->blockId;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return LicenseBlock
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
     * Set encrypted
     *
     * @param string $encrypted
     * @return LicenseBlock
     */
    public function setEncrypted($encrypted)
    {
        $this->encrypted = $encrypted;

        return $this;
    }

    /**
     * Get encrypted
     *
     * @return string 
     */
    public function getEncrypted()
    {
        return $this->encrypted;
    }

    /**
     * Set key
     *
     * @param \License\Entity\LicenseKey $key
     * @return LicenseBlock
     */
    public function setKey(\License\Entity\LicenseKey $key = null)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Get key
     *
     * @return \License\Entity\LicenseKey 
     */
    public function getKey()
    {
        return $this->key;
    }

}

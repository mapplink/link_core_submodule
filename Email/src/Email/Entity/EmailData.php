<?php

namespace Email\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * EmailData
 *
 * @ORM\Table(name="email_data", indexes={@ORM\Index(name="email_id_idx", columns={"parent_id"})})
 * @ORM\Entity(repositoryClass="Email\Repository\EmailDataRepository")
 */
class EmailData extends \Magelink\Entity\DoctrineBaseEntity
{
    /**
     * @var string
     *
     * @ORM\Column(name="key", type="string", length=45, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $key;

    /**
     * @var string
     *
     * @ORM\Column(name="value", type="string", length=254, nullable=false)
     */
    private $value;

    /**
     * @var \Email\Entity\Email
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\OneToOne(targetEntity="Email\Entity\Email")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="parent_id", referencedColumnName="email_id")
     * })
     */
    private $parent;



    /**
     * Set key
     *
     * @param string $key
     * @return EmailData
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
     * @return EmailData
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
     * Set parent
     *
     * @param \Email\Entity\Email $parent
     * @return EmailData
     */
    public function setParent(\Email\Entity\Email $parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return \Email\Entity\Email 
     */
    public function getParent()
    {
        return $this->parent;
    }
}

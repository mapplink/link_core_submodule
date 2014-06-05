<?php

namespace Magelink\Entity;

use Doctrine\ORM\Mapping as ORM;
use BjyAuthorize\Acl\HierarchicalRoleInterface;

/**
 * UserRole
 *
 * @ORM\Table(name="user_role", uniqueConstraints={@ORM\UniqueConstraint(name="unique_role", columns={"role_id"})}, indexes={@ORM\Index(name="idx_parent_id", columns={"parent_id"})})
 * @ORM\Entity(repositoryClass="Magelink\Repository\UserRoleRepository")
 */
class UserRole extends \Magelink\Entity\DoctrineBaseEntity implements HierarchicalRoleInterface
{
    //User roles 
    const 
        ROLE_GUEST             = 1,
        ROLE_PICKER_PACKER     = 2,
        ROLE_CUSTOMER_SERVICE  = 3,
        ROLE_SUPERVISOR        = 4,
        ROLE_MANAGER           = 5,
        ROLE_ADMINISTRATOR     = 6
    ;
    
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="role_id", type="string", length=255, nullable=false)
     */
    private $roleId;

    /**
     * @var boolean
     *
     * @ORM\Column(name="is_default", type="boolean", nullable=false)
     */
    private $isDefault = '0';

    /**
     * @var \Magelink\Entity\UserRole
     *
     * @ORM\ManyToOne(targetEntity="Magelink\Entity\UserRole")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     * })
     */
    private $parent;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="Magelink\Entity\User", mappedBy="role")
     */
    private $user;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->user = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * __toString()
     */
    public function __toString()
    {
        return ucwords(str_replace('-', ' ', $this->getRoleId()));
    }


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set roleId
     *
     * @param string $roleId
     * @return UserRole
     */
    public function setRoleId($roleId)
    {
        $this->roleId = $roleId;

        return $this;
    }

    /**
     * Get roleId
     *
     * @return string 
     */
    public function getRoleId()
    {
        return $this->roleId;
    }

    /**
     * Set isDefault
     *
     * @param boolean $isDefault
     * @return UserRole
     */
    public function setIsDefault($isDefault)
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    /**
     * Get isDefault
     *
     * @return boolean 
     */
    public function getIsDefault()
    {
        return $this->isDefault;
    }

    /**
     * Set parent
     *
     * @param \Magelink\Entity\UserRole $parent
     * @return UserRole
     */
    public function setParent(\Magelink\Entity\UserRole $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return \Magelink\Entity\UserRole 
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Add user
     *
     * @param \Magelink\Entity\User $user
     * @return UserRole
     */
    public function addUser(\Magelink\Entity\User $user)
    {
        $this->user[] = $user;

        return $this;
    }

    /**
     * Remove user
     *
     * @param \Magelink\Entity\User $user
     */
    public function removeUser(\Magelink\Entity\User $user)
    {
        $this->user->removeElement($user);
    }

    /**
     * Get user
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getUser()
    {
        return $this->user;
    }
}

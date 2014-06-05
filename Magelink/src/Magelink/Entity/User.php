<?php

namespace Magelink\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use BjyAuthorize\Provider\Role\ProviderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use ZfcUser\Entity\UserInterface;
use Zend\Math\Rand;

/**
 * User
 *
 * @ORM\Table(name="user", uniqueConstraints={@ORM\UniqueConstraint(name="username_UNIQUE", columns={"username"}), @ORM\UniqueConstraint(name="email_UNIQUE", columns={"email"}), @ORM\UniqueConstraint(name="user_hash_UNIQUE", columns={"user_hash"})})
 * @ORM\Entity(repositoryClass="Magelink\Repository\UserRepository")
 */
class User extends \Magelink\Entity\DoctrineBaseEntity implements UserInterface, ProviderInterface
{
    /**
     * @var integer
     *
     * @ORM\Column(name="user_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $userId;

    /**
     * @var string
     *
     * @ORM\Column(name="username", type="string", length=254, nullable=true)
     */
    private $username;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=254, nullable=true)
     */
    private $email;

    /**
     * @var string
     *
     * @ORM\Column(name="display_name", type="string", length=50, nullable=true)
     */
    private $displayName;

    /**
     * @var string
     *
     * @ORM\Column(name="password", type="string", length=128, nullable=false)
     */
    private $password;

    /**
     * @var integer
     *
     * @ORM\Column(name="state", type="smallint", nullable=true)
     */
    private $state;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="Report\Entity\Report", mappedBy="user")
     */
    private $report;

    /**
     * @var string
     *
     * @ORM\Column(name="user_hash", type="string", length=255, nullable=true)
     */
    private $userHash;

    /**
     * @var string
     *
     * @ORM\Column(name="user_hash_generated_at", type="datetime", nullable=true)
     */
    private $userHashGeneratedAt;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="Magelink\Entity\UserRole", inversedBy="user")
     * @ORM\JoinTable(name="user_role_linker",
     *   joinColumns={
     *     @ORM\JoinColumn(name="user_id", referencedColumnName="user_id")
     *   },
     *   inverseJoinColumns={
     *     @ORM\JoinColumn(name="role_id", referencedColumnName="id")
     *   }
     * )
     */
    private $role;

    /**
     * @var datetime $created
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @var datetime $updated
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(name="updated_at", type="datetime")
     */
    private $updatedAt;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->report = new \Doctrine\Common\Collections\ArrayCollection();
        $this->role = new \Doctrine\Common\Collections\ArrayCollection();
    }


    /**
     * Get userId
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->getUserId();
    }

    /**
     * Set userId
     *
     * @return integer 
     */
    public function setId($userId)
    {
        return $this->setUserId($userId);
    }

    /**
     * Get userId
     *
     * @return integer 
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * Set userId
     *
     * @return integer 
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Set username
     *
     * @param string $username
     * @return User
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get username
     *
     * @return string 
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set email
     *
     * @param string $email
     * @return User
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string 
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set displayName
     *
     * @param string $displayName
     * @return User
     */
    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;

        return $this;
    }

    /**
     * Get displayName
     *
     * @return string 
     */
    public function getDisplayName($fallbackStrategy = true)
    {
        if ($fallbackStrategy) {
            if ($this->displayName) {
                return $this->displayName;
            } elseif ($this->getUsername()) {
                return $this->getUsername();
            } elseif ($this->getEmail()) {
                $email = $this->getEmail();

                return preg_replace('/@.*$/', '', $email);
            }
        }
        return $this->displayName;
    }

    /**
     * Set password
     *
     * @param string $password
     * @return User
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Get password
     *
     * @return string 
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Set state
     *
     * @param integer $state
     * @return User
     */
    public function setState($state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * Get state
     *
     * @return integer 
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Add report
     *
     * @param \Magelink\Entity\Report $report
     * @return User
     */
    public function addReport(\Report\Entity\Report $report)
    {
        $this->report[] = $report;

        return $this;
    }

    /**
     * Remove report
     *
     * @param \Magelink\Entity\Report $report
     */
    public function removeReport(\Report\Entity\Report $report)
    {
        $this->report->removeElement($report);
    }

    /**
     * Get report
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getReport()
    {
        return $this->report;
    }

    /**
     * Add role
     *
     * @param mixed $role
     * @return User
     */
    public function addRole($role)
    {   
        if ($role instanceof ArrayCollection) {
            foreach ($role as $oneRole) {
                $this->role[] = $oneRole;
            }
        } else {
            $this->role[] = $role;
        }

        return $this;
    }

    /**
     * Remove role
     *
     * @param \Magelink\Entity\UserRole $role
     */
    public function removeRole($role)
    {
        if ($role instanceof ArrayCollection) {
            foreach ($role as $oneRole) {
                $this->removeRoleById($oneRole);
            }
        } else {
            $this->removeRoleById($role);
        }
                
    }

    public function removeRoleById($role)
    {
        foreach ($this->getRole() as $key => $oneRole) {
            if ($oneRole->getId() == $role->getId()) {
                $this->role->remove($key);
            }
        }
    }

    /**
     * Get role
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * Get role
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getRoles()
    {
        return $this->getRole();
    }

    /**
     * Check if user is admin
     * @return boolean
     */
    public function isAdmin()
    {
        foreach ($this->getRoles() as $role) {

            if ($role->getId() == UserRole::ROLE_ADMINISTRATOR) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set userHash
     *
     * @param string $userHash
     * @return User
     */
    public function setUserHash($userHash)
    {
        $this->userHash = $userHash;

        return $this;
    }

    /**
     * Get userHash
     *
     * @return string 
     */
    public function getUserHash()
    {
        return $this->userHash;
    }

    /**
     * Set userHashGeneratedAt
     *
     * @param \DateTime $userHashGeneratedAt
     * @return User
     */
    public function setUserHashGeneratedAt($userHashGeneratedAt)
    {
        $this->userHashGeneratedAt = $userHashGeneratedAt;

        return $this;
    }

    /**
     * Get userHashGeneratedAt
     *
     * @return \DateTime 
     */
    public function getUserHashGeneratedAt()
    {
        return $this->userHashGeneratedAt;
    }

    /**
     * Generate rand hash and record the time it was created
     * @return $this
     */
    public function generateHash()
    {
        $this->setUserHash(md5(Rand::getString(32, null, true)));
        $this->setUserHashGeneratedAt(new \DateTime('now'));

        return $this;
    }

    /**
     * Remove the hash
     * @return $this
     */
    public function cleanHash()
    {
        $this->setUserHash(null);
        $this->setUserHashGeneratedAt(null);

        return $this;
    }
    
    /**
     * Get the Regex pattern for check the password strength
     * @return 
     */
    public static function getPasswordCheckRegex()
    {
        return '/^[a-zA-Z0-9-_\.]{6,25}$/';
    }

    /**
     * Get CreatedAt
     * @return DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Get UpdatedAt
     * @return DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return User
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     * @return User
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}

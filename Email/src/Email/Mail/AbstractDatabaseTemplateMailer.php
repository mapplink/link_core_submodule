<?php
/**
 * Email\Mail
 *
 * @category Email
 * @package Email\Service
 * @author Seo Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Email\Mail;

use Entity\Entity;
use Magelink\Exception\MagelinkException;


abstract class AbstractDatabaseTemplateMailer extends BaseMailer
{
    /** @var \Email\Entity\EmailTemplate */
    protected $template = NULL;

    /** @var array $subjectParams */
    protected $subjectParams = array();

    /** @var array $templateParams */
    protected $templateParams = array();

    /** @var \Entity\Entity $entity */
    protected $entity;

    /** @var array $accessibleEntityTypes */
    protected $accessibleEntityTypes = array();


    /**
     * Get EmailTemplate Repository
     */
    protected function getEmailTemplateRepo()
    {
        return $this->getRepo('\Email\Entity\EmailTemplate');
    }

    /**
     * Get Doctrine repository
     * @param  string $EntityNmae 
     * @return mixed
     */
    protected function getRepo($entityName)
    {
        return $this->getEntityManager()
            ->getRepository($entityName);
    }

    /**
     * Get Doctrine EntityManager
     * @return object
     */
    protected function getEntityManager()
    {
        return $this->getServiceLocator()
            ->get('Doctrine\ORM\EntityManager');
    } 

    /**
     * Get template entity by sectionid and code
     * @param  integer $sectionId
     * @param  string  $code     
     * @return \Email\Entity\EmailTemplate
     */
    protected function getTemplate($sectionId, $code = null)
    {
        return $this->getEmailTemplateRepo()->getTemplate($sectionId, $code);
    }

    /**
     * Get templatess by sectionid
     * @param  integer $sectionId  
     * @return array
     */
    protected function getTemplatesBySection($sectionId)
    {
        return $this->getEmailTemplateRepo()->getTemplatesBySection($sectionId);
    }

    /**
     * Set up template
     */
    protected function setupTemplate(){}

    /**
     * Init before sending
     */
    protected function init()
    {
        $this->setupTemplate();

        if ($this->template) {
            $this->getMessage()->setFrom($this->template->getSenderEmail(), $this->template->getSenderName());
            $this->loadSubject();
            $this->loadBody();
        }else{
            throw new MagelinkException('Template is not assigned.');
        }
    }

    /**
     * Get replacement code
     * @param $entityType
     * @param $attributeCode
     * @return string
     */
    protected function getReplacementCode($entityType, $attributeCode)
    {
        return $entityType.'.'.$attributeCode;
    }

    /**
     * Get entity type and attribute code in an array
     * @param $replacementCode
     * @return array
     */
    protected function getEntityTypeAndAttributeCodeArray($replacementCode)
    {
        return explode('.', $replacementCode, 2);
    }

    /**
     * Get all entity replacement codes
     * @return array $replacementParamsCodes
     */
    protected function getAllRawEntityReplacementCodes()
    {
        $replacementParamsCodes = array();
        $entityTypes = $this->getServiceLocator()->get('entityConfigService')
            ->getEntityTypesCode();

        foreach ($entityTypes as $entityTypeId=>$entityType) {
            $replacementParamsCodes[$entityType] = $this->getServiceLocator()->get('entityConfigService')
                ->getAttributesCode($entityType);
        }

        return $replacementParamsCodes;
    }

    /**
     * Load email subject
     * @return string
     */
    protected function loadSubject()
    {   
        $subject = $this->template->getTitle();
        $subject = self::applyParams($subject, $this->subjectParams);
        $this->setTitle($subject);

        return $subject;
    }

    /**
     * Load email body
     * @return string
     */
    protected function loadBody()
    {   
        $body = $this->template->getBody();
        $body = self::applyParams($body, $this->templateParams);
        $this->setBody($body, $this->template->getMimeTypeForEmail());

        return $body;
    }

    /**
     * Get all entity replacement codes
     * @return array $replacementParamsCodes
     */
    protected function getAllEntityReplacementCodes()
    {
        $rawCodes = $this->getAllRawEntityReplacementCodes();
        $replacementParamsCodes = array();
        foreach ($rawCodes as $entityType=>$attributes) {
            if (in_array($entityType, array_keys($this->accessibleEntityTypes))) {
                $accessInformation = $this->accessibleEntityTypes[$entityType];

                if ($accessInformation === NULL) {
                    $alias = $entityType.'.';
                    $pathOrMethod = '';
                }elseif (is_array($accessInformation)) {
                    $alias = key($accessInformation);
                    $pathOrMethod = current($accessInformation);

                }else{
                    $alias = FALSE;
                }

                if ($alias !== FALSE) {
                    $replacementParamsCodes[$entityType][$alias] = $pathOrMethod;
                }
            }
        }

        return $replacementParamsCodes;
    }

    /**
     * Get all (allowed) entity replacement values/params
     * @return array $params
     */
    protected function getAllEntityReplacementValues()
    {
        $allParams = array();
        foreach ($this->getAllEntityReplacementCodes() as $entityType=>$paramsInfo) {
            $alias = key($paramsInfo);
            $pathOrMethod = current($paramsInfo);

            if (substr($alias, -1) == '.' && substr($pathOrMethod, -2) !== '()') {

                if ($this->entity->getTypeStr() == $entityType && $pathOrMethod === NULL) {
                    // Base entity
                    $newParams = $this->getSpecficEntityReplacementValues($this->entity, $alias);
                }elseif ($this->entity->getTypeStr() != $entityType) {
                    // Linked entity
                    $entityChainArray = explode('@', $pathOrMethod);
                    if ($entityChainArray[0] !== '') {
                        array_shift($entityChainArray);
                    }
                    // Reverse chain order to start from right to left (see definition of $this->accessibleEntityTypes)
                    $entityChainArray = array_reverse($entityChainArray);

                    $newEntity = $this->entity;
                    while ($newEntity && ($entityCode = each($entityChainArray))) {
                        list($entityType, $code) = explode('.', $entityCode['value'], 2);
                        if ($newEntity->getTypeStr() == $entityType) {
                            $newEntity = $this->getServiceLocator()->get('entityService')
                                ->loadEntityId(0, $newEntity->getData($code));
                        }else{
                            $newEntity = NULL;
                        }
                    }

                    if ($newEntity !== NULL) {
                        $newParams = $this->getSpecficEntityReplacementValues($newEntity, $alias);
                    }
                }else{
                    $newParams = array();
                }
            }elseif (substr($pathOrMethod, -2) == '()' && method_exists($this, $pathOrMethod)) {
                // Method
                $newParams = array($alias=>$this->$pathOrMethod());
            }else{
                $newParams = array();
            }

            $allParams = array_merge($allParams, $newParams);
        }

        $allParams['today'] = date('d M Y');

        return $allParams;
    }

    /**
     * Get specific entity replacement values/params
     * @param \Entity\Entity $entity
     * @param string $alias
     * @return array $params
     */
    protected function getSpecficEntityReplacementValues(\Entity\Entity $entity, $alias)
    {
        $params = array();
        foreach ($entity->getAllData() as $code=>$value) {
            $method = 'get'.str_replace('_', '', ucfirst($code));
            if (method_exists($entity, $method)) {
                $value = $entity->$method();
            }
            $params[$alias.$code] = $value;
        }

        return $params;
    }

    /**
     * Apply params to content
     * @param string $content
     * @param array $params
     * @return string
     */
    protected static function applyParams($content, array $params) 
    {
        foreach ($params as $search => $replace) {
            $content = str_replace('{{'.$search.'}}', $replace, $content);
        }
        //$content = preg_replace('#\{\{.*?\}\}#ism', '', $content);

        return $content;
    }
}
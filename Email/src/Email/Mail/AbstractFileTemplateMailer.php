<?php
/**
 * Email\Mail
 *
 * @category    Email
 * @package     Email\Service
 * @author      Seo Yao
 * @copyright   Copyright (c) 2014 LERO9 Ltd.
 * @license     Commercial - All Rights Reserved
 */

namespace Email\Mail;

use Zend\View\Renderer\PhpRenderer;
use Zend\View\Resolver\TemplateMapResolver;
use Zend\View\Model\ViewModel;
use Zend\Filter\Word\CamelCaseToDash;


abstract class AbstractFileTemplateMailer extends BaseMailer
{   

    /**
     * Set the default template based on the name of the class
     */
    protected function setupTemplate()
    {   
        $classRelection = new \ReflectionClass(get_called_class());
        $templateName = $classRelection->getShortName();
        $camelCaseToDashFilter = new CamelCaseToDash();
        $templateName = $camelCaseToDashFilter->filter($templateName);
        $templateName = strtolower($templateName);
        $templateName = preg_replace('/-mailer$/i', '', $templateName);
        $this->setTemplateName($templateName);
    }

    /**
     * Render the template and return the rendered content
     * 
     * @return string
     */
    public function renderTemplate($parameters = array())
    {   
        $this->setupTemplate();
        
        $view = new PhpRenderer();
        $resolver = new TemplateMapResolver();
        $resolver->setMap(array(
            'mailLayout' => __DIR__ . '/template/layout.phtml',
            'mailTemplate' => __DIR__ . '/template/' . $this->templateName . '.phtml'
        ));

        $view->setResolver($resolver);

        $viewModel = new ViewModel();
        $viewModel->setTemplate('mailTemplate')
            ->setVariables($parameters);

        $content = $view->render($viewModel);

        $viewLayout = new ViewModel();

        $viewLayout->setTemplate('mailLayout')
             ->setVariables(array('content' => $content));

        return $view->render($viewLayout);
    }

    /**
     * init before sending
     * @return [type] [description]
     */
    protected function init()
    {
        //@TODO put this into config file
        $this->message->setFrom('support@lero9.com', 'System Admin');
    }

    /**
     * Get template name
     * @return string
     */
    public function getTemplateName()
    {
        return $this->templateName;
    }

    /**
     * Set template name
     * @param string $templateName 
     */
    public function setTemplateName($templateName)
    {
        $this->templateName = $templateName;

        return $this;
    }
}
<?php
/*
 * @package Email\Mail
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Email\Mail;

use Email\Entity\EmailTemplateSection;


class OrderShipmentMailer extends AbstractOrderMailer
{

    /** @var $additionalNote */
    protected $additionalNote;


    /**
     * Set additional note
     * @param $note
     * @return $this
     */
    public function setAdditionalNote($note)
    {
        $this->additionalNote = $note;
        return $this;
    }

    /**
     * Set up template
     */
    protected function _setupTemplate()
    {
        $this->template = $this->getTemplate(
            EmailTemplateSection::SECTION_SHIPPING_NOTIFICATION,
            $this->entity->getData('shipping_method')
        );

        if (!$this->template) {
            $this->template = $this->getTemplate(EmailTemplateSection::SECTION_SHIPPING_NOTIFICATION, 'default');
        }
    }

    /**
     * @return bool $isSendEmail
     */
    protected function isSendEmail()
    {
        /** @var ApplicationConfigService $applicationConfigService */
        $applicationConfigService = $this->getServiceLocator()->get('applicationConfigService');

        if ($this->entity instanceof \HOPS\Wrapper\Order) {
            $sendEmail = FALSE;
            $gatewaysToCheck = array(
                'Magento'=>'\Magento\Gateway\OrderGateway',
                'Magento2'=>'\Magento2\Gateway\OrderGateway'
            );
            foreach ($gatewaysToCheck as $module=>$gateway) {
                if ($applicationConfigService->isModuleEnabled($module)) {
                    $sendEmail |= $gateway::isOrderToBeWritten($this->entity);
                }
            }
        }else{
            $sendEmail = TRUE;
        }

        return (bool) $sendEmail;
    }

    /**
     * Set up body parameters
     */
    protected function setBodyParameters(array $sharedParameters)
    {
        parent::setBodyParameters($sharedParameters);
        $this->bodyParameters['AdditionalNote'] = $this->additionalNote;
    }

}

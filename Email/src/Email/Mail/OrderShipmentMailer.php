<?php
/**
 * Order shipment notification mailer
 *
 * @category Email
 * @package Email\Mail
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
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
    public function setupTemplate()
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
     * Set up body parameters
     */
    protected function setBodyParameters(array $sharedParameters)
    {
        parent::setBodyParameters($sharedParameters);
        $this->bodyParameters['AdditionalNote'] = $this->additionalNote;
    }

}
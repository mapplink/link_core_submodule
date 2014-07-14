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
     * Set up order
     * @param \HOPS\Order $order
     */
    public function setOrder($order)
    {
        $this->entity = $order;
        $this->subjectParams['orderId'] = $order->getUniqueId();
        $this->setAllRecipients(
            array($order->getData('customer_email') => $order->getData('customer_name'))
        );

        return $this;
    }

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
            $this->order->getData('shipping_method')
        );

        if (!$this->template) {
            $this->template = $this->getTemplate(EmailTemplateSection::SECTION_SHIPPING_NOTIFICATION, 'default');
        }
    }

    /**
     * Set up body parameters
     */
    protected function setBodyParams()
    {     
        $this->templateParams = array_merge(
            $this->getAllEntityReplacementValues($this->entity),
            array('additionalNote' => $this->additionalNote)
        );
    }

    /**
     * Get full shipping address
     * @return string
     */
    protected function getShippingAddress()
    {
        $address = $this->order->getShippingAddressEntity();
        if ($address) {
            $addressArray = $address->getAddressFullArray();

            if (!is_object($this->template)) {
                $this->getServiceLocator()->get('logService')
                    ->log(\Log\Service\LogService::LEVEL_INFO,
                        'email_no_template',
                        'No template is set for shipping address on order '.$this->order->getUniqueId(),
                        array(
                            'order id'=>$this->order->getId(), 'order unique id'=>$this->order->getUniqueId(),
                            'address id'=>$address->getId(), 'address unique id'=>$address->getUniqueId()
                        ),
                        array('order'=>$this->order, 'address'=>$address)
                    );

                return implode("\n", $addressArray);
            }elseif ($this->template->isHTML()) {
                return implode('<br/>', $addressArray);
            }else{
                return implode("\n", $addressArray);
            }
        }
    }

}
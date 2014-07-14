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
use Entity\Wrapper\Order;


class OrderShipmentMailer extends AbstractOrderMailer
{

    /** @var $additionalNote */
    protected $additionalNote;


    /**
     * Set up order
     * @param \Entity\Wrapper\Order $order
     */
    public function setOrder(\Entity\Wrapper\Order $order)
    {
        $this->entity = $order;

        $this->setAllRecipients(array($order->getData('customer_email')=>$order->getData('customer_name')));
        $this->subjectParams['orderId'] = $order->getUniqueId();
        $this->setBodyParams();


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
            $this->entity->getData('shipping_method')
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
        $address = $this->entity->getShippingAddressEntity();
        if ($address) {
            $addressArray = $address->getAddressFullArray();

            if (!is_object($this->template)) {
                $this->getServiceLocator()->get('logService')
                    ->log(\Log\Service\LogService::LEVEL_INFO,
                        'email_no_template',
                        'No template is set for shipping address on order '.$this->entity->getUniqueId(),
                        array(
                            'order id'=>$this->entity->getId(), 'order unique id'=>$this->entity->getUniqueId(),
                            'address id'=>$address->getId(), 'address unique id'=>$address->getUniqueId()
                        ),
                        array('order'=>$this->entity, 'address'=>$address)
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
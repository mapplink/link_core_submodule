<?php

namespace Email\Mail;

use Email\Entity\EmailTemplateSection;

/**
 * Order shipment notification mailer
 */
class OrderShipmentMailer extends AbstractOrderMailer
{   
    /**
     * order entity
     */
    protected 
        $order,
        $additionalNote
    ;

    /**
     * Set up order
     * @param \HOPS\Order $order
     */
    public function setOrder($order)
    {
        $this->order = $order;

        $this->subjectParams['orderId'] = $order->getUniqueId();

        $this->setAllRecipients(array($order->getData('customer_email') => $order->getData('customer_name')));

        return $this;
    }

    public function setAdditionalNote($note)
    {
        $this->additionalNote = $note;

        return $this;
    }

    /**
     * Set up template
     * @return
     */
    public function setupTemplate()
    {
        $this->template = $this->getTemplate(EmailTemplateSection::SECTION_SHIPPING_NOTIFICATION, $this->order->getData('shipping_method'));

        if (!$this->template) {
            $this->template = $this->getTemplate(EmailTemplateSection::SECTION_SHIPPING_NOTIFICATION, 'default');
        }
    }

    /**
     * Set up body parameters
     */
    protected function setBodyParams()
    {     
        $this->templateParams = array(
            'userName'        => $this->order->getData('customer_name'),
            'userEmail'       => $this->order->getData('customer_email'),
            'date'            => date('d M Y'),
            'shippingMethod'  => $this->order->getData('shipping_method'),
            'shippingAddress' => $this->getShippingAddress(),
            'trackingCode'    => $this->order->getData('tracking_code'),
            'userName'        => $this->order->getData('customer_name'),
            'orderId'         => $this->order->getUniqueId(),
            'orderItems'      => $this->renderOrderItems(),
            'additionalNote'  => $this->additionalNote,
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

            if ($this->template->isHTML()) {
                return implode('<br/>', $addressArray);
            } else {
                return implode("\n", $addressArray);
            }
        }
    }

   




    
}
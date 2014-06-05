<?php

namespace Email\Mail;

use Email\Entity\EmailTemplateSection;

/**
 * Order exception notification mailer
 */
class ExceptionNotificationMailer extends AbstractOrderMailer
{   
    protected $params = array();

    /**
     * Set template
     * @param [type] $template [description]
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * Set params
     */
    public function setParams($params)
    {
        $this->params = $params;

        return $this;
    }

     /**
     * Set up body parameters
     */
    protected function setBodyParams()
    {     
        $this->templateParams = array_merge($this->params, array(
            'userName'        => $this->order->getData('customer_name'),
            'userEmail'       => $this->order->getData('customer_email'),
            'date'            => date('d M Y'),
            'shippingMethod'  => $this->order->getData('shipping_method'),
            'shippingAddress' => $this->getShippingAddress(),
            'trackingCode'    => $this->order->getData('tracking_code'),
            'orderId'         => $this->order->getUniqueId(),
            'orderItems'      => $this->renderOrderItems()
        ));
    }

  

}
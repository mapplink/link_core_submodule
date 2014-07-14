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


abstract class AbstractOrderMailer extends AbstractDatabaseTemplateMailer 
{
    /**
     * @var array $accessibleEntityTypes
     *
     * <entity type> => NULL|FALSE|array(<entity type> => NULL|FALSE|array)
     *    NULL : no linking necessary
     *    array : array('<alias>.' => <path: @entityType.attributeCode[@entityType.attributeCode ...]>)
     *            || array(<alias> => '<method name>()')
     */
    protected $accessibleEntityTypes = array(
        'order'=>NULL,
        'orderitem'=>array(
            'orderitems'=>'renderOrderItems()'
        ),
        'customer'=>array(
            'customer.'=>'@order.customer'
        ),
        'address'=>array(
            'shipping_address.'=>'@customer.shipping_address@order.customer',
            'billing_address.'=>'@customer.billing_address@order.customer'
        )
    );

    /** @var \Entity\Wrapper\Order $order */
    protected $order;

    /**
     * Set Order
     * @param \Entity\Wrapper\Order $order
     * @return $this
     */
    public function setOrder($order)
    {
        $this->order = $order;
        $this->subjectParams['orderId'] = $order->getUniqueId();

        $this->setAllRecipients(array($order->getData('customer_email') => $order->getData('customer_name')));

        return $this;
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

    public function send()
    {
        parent::send();
         
        $fromAddrs = array();
        foreach ($this->getMessage()->getFrom() as $from) {
            $fromAddrs[$from->getEmail()] = $from->getName();
        }

        $toAddrs = array();
        foreach ($this->getMessage()->getTo() as $to) {
            $toAddrs[$to->getEmail()] = $to->getName();
        }

        /** @var \Entity\Service\EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');
        /** @var \Zend\Authentication\AuthenticationService $authService */
        $authService = $this->getServiceLocator()->get('zfcuser_auth_service');

        $entityService->createEntityComment(
            $this->order,
            $authService->getIdentity()->getDisplayName(),
            $this->getMessage()->getSubject(),
            $this->getMessage()->getBodyText(),
            '',
            FALSE
        );

    }

    /**
     * Render items info
     */
    protected function renderOrderItems()
    {
        $items = $this->order->getOrderItems();

        $content = '';

        foreach ($items as $item) {
            $content .= 'item : SKU#' . $item->getSku().' '.$item->getProductName()
                .' x '.((int) $item->getData('quantity'))."\n\n";
        }

        if ($this->template && $this->template->isHTML()) {
            $content = nl2br($content);
        }

        return $content;
    }

}

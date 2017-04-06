<?php
/**
 * @category Email
 * @package Email\Service
 * @author Seo Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Email\Mail;

use Application\Service\ApplicationConfigService;
use Email\Entity\EmailSender;
use Entity\Wrapper\Order;
use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Doctrine\Tests\Common\Annotations\Ticket\Doctrine\ORM\Entity;


abstract class AbstractOrderMailer extends AbstractDatabaseTemplateMailer
{
    /**
     * @var array $accessibleEntityTypes
     * <entity type> => NULL|FALSE|array(<entity type> => NULL|FALSE|array)
     *    NULL : no linking necessary
     *    array : array('<alias>.' => <path: @entityType.attributeCode[@entityType.attributeCode ...]>)
     *            || array(<alias> => '<method name>()')
     */
    protected $accessibleEntityTypes = array(
        'order'=>NULL,
        'orderitem'=>array('OrderItems'=>'renderOrderItems()'),
        'customer'=>array('customer.'=>'@order.customer'),
        'address'=>array('shipping_address.'=>'@order.shipping_address','billing_address.'=>'@order.billing_address')
    );


    /**
     * Set Order
     * @param Order $order
     * @return $this
     */
    public function setOrder(Order $order)
    {
        $this->entity = $order;

        $this->setAllRecipients(array($order->getData('customer_email') => $order->getData('customer_name')));
        $this->setParameters();

        if ($this->template && $this->entity instanceof Order) {
            $this->setSenderDetails();
        }

        return $this;
    }

    /**
     * Set up template
     * @throws MagelinkException
     */
    public function setupTemplate()
    {
        $this->_setupTemplate();

        if ($this->template && $this->entity instanceof Order) {
            $this->setSenderDetails();
        }
    }

    abstract protected function _setupTemplate();

    /**
     * @return bool $isSendEmail
     */
    protected function isSendEmail()
    {
        return TRUE;
    }

    /**
     * Set sender details
     * @throws MagelinkException
     */
    protected function setSenderDetails()
    {
        if ($this->template && $this->entity instanceof Order) {
            if (!$this->template->getSenderEmail()) {
                $defaultSender = NULL;
                /** @var EmailSender $defaultSender */
                $defaultSenders = $this->getRepo('\Email\Entity\EmailSender')
                    ->findBy(array('storeId'=>$this->entity->getStoreId()));
                foreach ($defaultSenders as $sender) {
                    if ($sender instanceof EmailSender) {
                        if ($sender->getCode() == $this->template->getCode()) {
                            $defaultSender = $sender;
                            break;
                        }elseif ($sender->getCode() === '' && is_null($defaultSender)) {
                            $defaultSender = $sender;
                        }
                    }
                }

                $message = 'No sender email defined, neither on the template '.$this->template->getHumanName()
                    .' nor as a default sender on store '.$this->entity->getStoreId().'.';

                if (!is_null($defaultSender)) {
                    $this->template->setSenderEmail($defaultSender->getSenderEmail());
                    $this->template->setSenderName($defaultSender->getSenderName());
                }elseif ($this->isSendEmail()) {
                    throw new MagelinkException($message);
                }else{
                    $this->getServiceLocator()->get('logService')
                        ->log(LogService::LEVEL_WARN, 'email_notmpl', $message, array());
                }
            }
        }
    }

    /**
     * Set up subject parameters
     */
    protected function setSubjectParameters(array $sharedParameters)
    {
        $this->subjectParameters = array_merge(
            $this->subjectParameters,
            $sharedParameters
        );

        return $this->subjectParameters;
    }


    /**
     * Set up body parameters
     */
    protected function setBodyParameters(array $sharedParameters)
    {
        $this->bodyParameters = array_merge(
            $this->bodyParameters,
            $this->getAllEntityReplacementValues(),
            $sharedParameters,
            array(
                'ShippingAddress'=>$this->renderShippingAddress()
            )
        );

        return $this->bodyParameters;
    }

    /**
     * Set up all parameters
     */
    protected function setParameters(array $sharedParameters = array())
    {
        $parameters = array();
        $sharedParameters['Today'] = date('d M Y');
        $sharedParameters['OrderId'] = $this->entity->getUniqueId();

        $parameters['subject'] = $this->setSubjectParameters($sharedParameters);
        $parameters['body'] = $this->setBodyParameters($sharedParameters);

        return $parameters;
    }


    /**
     * Send order emails
     */
    public function send()
    {
        if ($this->isSendEmail()) {
            parent::send();

            $fromAddress = array();
            foreach ($this->getMessage()->getFrom() as $from) {
                $fromAddress[$from->getEmail()] = $from->getName();
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
                $this->entity,
                $authService->getIdentity()->getDisplayName(),
                $this->getMessage()->getSubject(),
                $this->getMessage()->getBodyText(),
                '',
                FALSE
            );
        }
    }

    /**
     * Get full shipping address
     * @return string
     */
    protected function renderShippingAddress()
    {
        $address = $this->entity->getShippingAddressEntity();

        if ($address) {
            $addressArray = $address->getAddressFullArray();

            $glue = "\n";
            if (!is_object($this->template)) {
                $this->getServiceLocator()->get('logService')
                    ->log(LogService::LEVEL_INFO, 'email_add_notmpl',
                        'No template is set for shipping address on order '.$this->entity->getUniqueId(),
                        array(
                            'order id'=>$this->entity->getId(), 'order unique id'=>$this->entity->getUniqueId(),
                            'address id'=>$address->getId(), 'address unique id'=>$address->getUniqueId()
                        ),
                        array('order'=>$this->entity, 'address'=>$address)
                    );
            }elseif ($this->template->isHTML()) {
                $glue = '<br/>';
            }
            $renderedAddress = implode($glue, $addressArray);

            return $renderedAddress;
        }
    }

    /**
     * Render items info
     */
    protected function renderOrderItems()
    {
        $items = $this->entity->getOrderitems();

        $content = '';
        foreach ($items as $item) {
            $content .= 'item : SKU#'.$item->getSku().' '.$item->getProductName()
                .' x '.((int) $item->getData('quantity'))."\n\n";
        }
        $content = trim($content);

        if (!is_object($this->template)) {
            $this->getServiceLocator()->get('logService')
                ->log(LogService::LEVEL_INFO,
                    'email_oi_notmpl',
                    'No template is set for order items on order '.$this->entity->getUniqueId(),
                    array('order id'=>$this->entity->getId(), 'order unique id'=>$this->entity->getUniqueId()),
                    array('order'=>$this->entity, 'orderitems'=>$items)
                );
        }elseif ($this->template->isHTML()) {
            $content = nl2br($content);
        }

        return $content;
    }

}

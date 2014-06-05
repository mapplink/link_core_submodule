<?php


namespace Magelink\Auth;


use BjyAuthorize\View\UnauthorizedStrategy as BjyUnauthorizedStrategy;
use Zend\Http\Response as HttpResponse;
use Zend\Mvc\MvcEvent;
use BjyAuthorize\Exception\UnAuthorizedException;
use Zend\Console\Request as ConsoleRequest;



class UnauthorizedStrategy extends BjyUnauthorizedStrategy
{
    /**
     * Callback used when a dispatch error occurs. Modifies the
     * response object with an according error if the application
     * event contains an exception related with authorization.
     *
     * @param MvcEvent $event
     *
     * @return void
     */
    public function onDispatchError(MvcEvent $event)
    {
        // If unauthorized then check if user logged in 
        if (
            ($event->getParam('exception') instanceof UnAuthorizedException)
            && (!$event->getApplication()->getServiceManager()->get('zfcuser_auth_service')->getIdentity())
            && !($event->getRequest() instanceof ConsoleRequest)
        ) {
            $response = $event->getResponse() || new HttpResponse();
            $url      = $event->getRouter()->assemble(array(), array('name' => 'zfcuser/login'));
            $response = $event->getResponse();
            $response->getHeaders()->addHeaderLine('Location', $url);
            $response->setStatusCode(302);
            $event->setResponse($response);

            return ;
        }

        return parent::onDispatchError($event);
    }
}
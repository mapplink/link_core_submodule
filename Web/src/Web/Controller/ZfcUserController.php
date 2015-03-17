<?php
/**
 * Web\Controller
 *
 * @category    Web
 * @package     Web\Controller
 * @author      Sean Yao <sean@lero9.com>
 * @copyright   Copyright (c) 2014 LERO9 Ltd.
 * @license     Commercial - All Rights Reserved
 */

namespace Web\Controller;

use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Magelink\Entity\User;
use Zend\Crypt\Password\Bcrypt;

class ZfcUserController extends BaseController
{
    /**
     * Send a email contains the link to reset the password
     */
    public function sendResetPasswordLinkAction()
    {
        $usernameEmail = $this->params()->fromQuery('username');

        $user = $this->getRepo('Magelink\Entity\User')
            ->getUserByEmailOrUsername($usernameEmail);

        if ($user) {
            $user->generateHash();

            $this->persistEntity($user);

            $result = array (
                'message' => 'A password reset link has been send to your email address.',
                'success' => true,
            );

            $passwordRestUrl = $this->url()
                ->fromRoute(
                    'zfcuser/resetpwd', 
                    array('hash' => $user->getUserHash()),
                    array('force_canonical' => true)
                )
            ;

            $pvasswordResetMailer = $this->loadMailer('PasswordReset')
                ->setUserRecipient($user)
                ->setResetUrl($passwordRestUrl)
                ->send();
            ;

        } else {
            $result = array (
                'message' => 'The user dose not exist.',
                'success' => false,
            );
        }

        return new JsonModel($result);
    }

    /**
     * Reset Password
     * @return
     */
    public function resetPasswordWithUserHashAction()
    {
        if ($this->getCurrentUser()) {
            return $this->redirect()->toRoute('home');
        }

        $user = $this->getRepo('Magelink\Entity\User')
            ->getUserByHash($this->params('hash'));
        $errorMessage = null;

        if (!$user) {
           $errorMessage = 'Sorry, this page expired.'; 
        }

        $request = $this->getRequest();
        if ($user && $request->isPost()) {

            $validator = new \Zend\Validator\Regex(array(
                'pattern' => User::getPasswordCheckRegex(),
            ));

            if (!$validator->isValid($request->getPost()->get('password'))) {
                $errorMessage = 'Password needs to be 6 characters or more and only alphanumeric and -_. can be used.'; 
            } elseif ($request->getPost()->get('password') != $request->getPost()->get('passwordRetype')) {
                $errorMessage = 'Password and password retype don\'t match';
            }

            if (!$errorMessage) {
                $bcrypt = new Bcrypt;
                $bcrypt->setCost($this->getServiceLocator()->get('zfcuser_user_service')->getOptions()->getPasswordCost());
                $user->setPassword($bcrypt->create($request->getPost()->get('password')));
                $this->persistEntity($user);

                $this->flashMessenger()->setNamespace('success')->addMessage('The password has been successfully updated.');
                return $this->redirect()->toRoute('zfcuser/login');
            }

        }

        $view = new ViewModel(array( 'user' => $user, 'errorMessage' => $errorMessage ));
        $view->setTemplate('zfc-user/user/reset-password');

        return $view;
    }

}
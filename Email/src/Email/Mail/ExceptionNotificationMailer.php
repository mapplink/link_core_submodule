<?php
/*
 * @package Email\Mail
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Email\Mail;

use Email\Entity\EmailTemplateSection;


class ExceptionNotificationMailer extends AbstractOrderMailer
{
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
     * @return null
     */
    protected function _setupTemplate()
    {
        return NULL;
    }

    /**
     * Set up all parameters
     */
    public function setParameters(array $sharedParameters = array())
    {
        parent::setParameters($sharedParameters);
        return $this;
    }

}

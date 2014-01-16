<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Library\Configuration;

class PlatformConfiguration
{
    private $name;
    private $supportEmail;
    private $selfRegistration;
    private $localeLanguage;
    private $theme;
    private $footer;
    private $role;
    private $termsOfService;

    public function getSelfRegistration()
    {
        return $this->selfRegistration;
    }

    public function setSelfRegistration($selfRegistration)
    {
        $this->selfRegistration = $selfRegistration;
    }

    public function getLocalLanguage()
    {
        return $this->localeLanguage;
    }

    public function setLocalLanguage($localeLanguage)
    {
        $this->localeLanguage = $localeLanguage;
    }

    public function getTheme()
    {
        return $this->theme;
    }

    public function setTheme($theme)
    {
        $this->theme = $theme;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getSupportEmail()
    {
        return $this->supportEmail;
    }

    public function setSupportEmail($email)
    {
        $this->supportEmail = $email;
    }

    public function getFooter()
    {
        return $this->footer;
    }

    public function setFooter($footer)
    {
        $this->footer = $footer;
    }

    public function setDefaultRole($role)
    {
        $this->role = $role;
    }

    public function getDefaultRole()
    {
        return $this->role;
    }

    public function setTermsOfService($termsOfService)
    {
        $this->termsOfService = $termsOfService;
    }

    public function getTermsOfService()
    {
        return $this->termsOfService;
    }
}

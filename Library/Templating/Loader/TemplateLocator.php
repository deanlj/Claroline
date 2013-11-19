<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Library\Templating\Loader;

use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;
use Symfony\Bundle\FrameworkBundle\Templating\Loader\TemplateLocator as baseTemplateLocator;

/**
 * {@inheritDoc}
 */
class TemplateLocator extends baseTemplateLocator
{
    protected $locator;
    protected $cache;
    protected $configHandler;
    protected $themeService;

    /**
     * Constructor.
     *
     * @param FileLocatorInterface         $locator       A FileLocatorInterface instance
     * @param PlatformConfigurationHandler $configHandler Claroline platform configuration handler service
     * @param ThemeService                 $themeService  Claroline theme service
     * @param string                       $cacheDir      The cache path
     */
    public function __construct(FileLocatorInterface $locator, $configHandler, $themeService, $cacheDir = null)
    {
        if (null !== $cacheDir && is_file($cache = $cacheDir.'/templates.php')) {
            $this->cache = require $cache;
        }

        $this->locator = $locator;
        $this->configHandler = $configHandler;
        $this->themeService = $themeService;
    }

    /**
     * {@inheritDoc}
     */
    public function locate($template, $currentPath = null, $first = true)
    {
//        return parent::locate($template, $currentPath, $first);
        if (!$template instanceof TemplateReferenceInterface) {
            throw new \InvalidArgumentException('The template must be an instance of TemplateReferenceInterface.');
        }

        $path = $this->configHandler->getParameter('theme');
        $theme = $this->themeService->findTheme(array('path' => $path));
        $bundle = substr($path, 0, strpos($path, ':'));

        if (is_object($theme) and
            $bundle !== '' and
            $bundle !== $template->get('bundle') and
            $template->get('bundle') === 'ClarolineCoreBundle'
        ) {
            $tmp = clone $template;

            $tmp->set('bundle', $bundle);
            $tmp->set(
                'controller',
                strtolower(str_replace(' ', '', $theme->getName())).'/'.$template->get('controller')
            );

            try {
                $this->locator->locate($tmp->getPath(), $currentPath);
                $template = $tmp;
            } catch (\Exception $e) {
                //var_dump($template);
                unset($tmp);
            }
        }

        $key = $this->getCacheKey($template);

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        try {
            return $this->cache[$key] = $this->locator->locate($template->getPath(), $currentPath);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(
                sprintf('Unable to find template "%s" : "%s".', $template, $e->getMessage()), 0, $e
            );
        }
    }
}

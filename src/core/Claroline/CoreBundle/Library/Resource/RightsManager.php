<?php

namespace Claroline\CoreBundle\Library\Resource;

use Doctrine\ORM\EntityManager;
use Claroline\CoreBundle\Entity\Resource\AbstractResource;

class RightsManager
{
    /** @var EntityManager */
    private $em;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Returns an array of ResourceRight for a resource in a workspace
     *
     * @param AbstractResource $resource
     *
     * @return array
     */
    public function getRights(AbstractResource $resource)
    {
        $repo = $this->em->getRepository('ClarolineCoreBundle:Workspace\ResourceRights');
        $configs = $repo->getAllForResource($resource);
        $baseConfigs = array();
        $customConfigs = array();
        $effectiveConfigs = array();

        foreach($configs as $config){
            ($config->getResource() != null) ? $customConfigs[] = $config: $baseConfigs[] = $config;
        }

        foreach($baseConfigs as $baseConfig){
            $found = false;
            $toAdd = null;
            foreach ($customConfigs as $key => $customConfig){
                if($customConfig->getRole() == $baseConfig->getRole()){
                    $found = true;
                    $toAdd = $key;

                }
            }

            $found ? $effectiveConfigs[] = $customConfigs[$toAdd] : $effectiveConfigs[] = $baseConfig;
        }

        return $effectiveConfigs;
    }

    /**
     * Takes the array of checked ids from the rights form (ie rights_form.html.twig) and
     * transforms them into a easy to use permission array.
     *
     * @param array $checks
     *
     * @Return array
     */
    public function setRightsRequest($checks)
    {
        $configs = array();
        foreach(array_keys($checks) as $key){
            $arr = explode('-', $key);
            $configs[$arr[1]][$arr[0]] = true;
        }

        foreach($configs as $key => $config){
            $configs[$key] = $this->addMissingRights($config);
        }

        return $configs;
    }

    private function addMissingRights($rights)
    {
        $expectedKeys = array('canSee', 'canOpen', 'canDelete', 'canEdit', 'canCopy');
        foreach($expectedKeys as $expected){
            if(!isset($rights[$expected])){
                $rights[$expected] = false;
            }
        }

        return $rights;
    }
}


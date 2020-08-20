<?php

namespace Claroline\CoreBundle\Tests\NewAPI;

use Claroline\AppBundle\API\SerializerProvider;
use Claroline\CoreBundle\Library\Testing\TransactionalTestCase;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;

class FinderProviderTest extends TransactionalTestCase
{
    /** @var SerializerProvider */
    private $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = $this->client->getContainer()->get('Claroline\AppBundle\API\FinderProvider');
        $this->reader = $this->client->getContainer()->get('test.annotation_reader');
        $tokenStorage = $this->client->getContainer()->get('security.token_storage');
        $token = new AnonymousToken('key', 'anon.');
        $tokenStorage->setToken($token);
    }

    /**
     * @dataProvider getHandledClassesProvider
     *
     * Just test the generated sql is correct and there is no syntax error
     *
     * @param string $class
     */
    public function testFinder($class)
    {
        $finder = $this->provider->get($class);
        $filters = $finder->getFilters();

        if (array_key_exists('$defaults', $filters)) {
            unset($filters['$defaults']);
            //todo: test les defaults
            $refClass = new \ReflectionClass($class);
            $properties = $refClass->getProperties();

            foreach ($properties as $refProp) {
                if (!in_array($refProp->getName(), $filters)) {
                    $annotation = $this->reader->getPropertyAnnotation($refProp, 'Doctrine\\ORM\\Mapping\\Column');

                    if ($annotation) {
                        $filters[$refProp->getName()] = [
                            'name' => "filters[{$refProp->getName()}]",
                            'type' => $annotation->type,
                            'description' => 'Autogenerated from doctrine annotations (no description found)',
                        ];
                    }
                }
            }
        }

        $allFilters = [];

        foreach ($filters as $filterName => $filterOptions) {
            $filter = $this->buildFilter($filterName, $filterOptions);
            $allFilters = array_merge($allFilters, $filter);
        }

        try {
            $data = $this->provider->fetch($class, $allFilters);
        } catch (\Exception $e) {
            //use var_dump here for debugging purpose
            throw new \Exception($e->getMessage());
        }
        //empty array
        $this->assertTrue(is_array($data));
    }

    private function buildFilter($filterName, $filterOptions)
    {
        $type = $filterOptions['type'];

        if (is_array($type)) {
            $type = $type[0];
        }

        $value = 'abcdef';
        $aDate = new \DateTime();

        switch ($type) {
          case 'bool': $value = true; break;
          case 'boolean': $value = true; break;
          case 'integer': $value = 123; break;
          case 'datetime': $value = $aDate; break;
          case 'date': $value = $aDate; break;
        }

        return [$filterName => $value];
    }

    /**
     * @return [][]
     */
    public function getHandledClassesProvider()
    {
        parent::setUp();
        $provider = $this->client->getContainer()->get('Claroline\AppBundle\API\FinderProvider');

        $finders = array_filter($provider->all(), function ($finder) {
            return method_exists($finder, 'getFilters') ? count($finder->getFilters()) > 0 : false;
        });

        return array_map(function ($finder) use ($provider) {
            return [$finder->getClass()];
        }, $finders);
    }
}

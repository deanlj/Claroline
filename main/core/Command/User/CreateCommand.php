<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Command\User;

use Claroline\AppBundle\API\Crud;
use Claroline\AppBundle\Command\BaseCommandTrait;
use Claroline\AppBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Command\AdminCliCommand;
use Claroline\CoreBundle\Entity\Role;
use Claroline\CoreBundle\Entity\User as UserEntity;
use Claroline\CoreBundle\Security\PlatformRoles;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates an user, optionally with a specific role (default to simple user).
 */
class CreateCommand extends Command implements AdminCliCommand
{
    use BaseCommandTrait;

    private $params = [
        'user_first_name' => 'first name',
        'user_last_name' => 'last name',
        'user_username' => 'username',
        'user_password' => 'password',
        'user_email' => 'email',
    ];
    private $om;
    private $crud;

    public function __construct(ObjectManager $om, Crud $crud)
    {
        $this->om = $om;
        $this->crud = $crud;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Creates a new user.');
        $this->configureParams();
        $this->addOption(
            'ws_creator',
            'w',
            InputOption::VALUE_NONE,
            'When set to true, created user will have the workspace creator role'
        );
        $this->addOption(
            'admin',
            'a',
            InputOption::VALUE_NONE,
            'When set to true, created user will have the admin role'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('user_email');
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ?
            $email : $email.'@debug.net';

        if ($input->getOption('admin')) {
            $roleName = PlatformRoles::ADMIN;
        } elseif ($input->getOption('ws_creator')) {
            $roleName = PlatformRoles::WS_CREATOR;
        } else {
            $roleName = PlatformRoles::USER;
        }

        $object = $this->crud->create(
            UserEntity::class,
            [
              'firstName' => $input->getArgument('user_first_name'),
              'lastName' => $input->getArgument('user_last_name'),
              'username' => $input->getArgument('user_username'),
              'email' => $email,
              'plainPassword' => $input->getArgument('user_password'),
             ]
        );

        $role = $this->om->getRepository(Role::class)->findOneByName($roleName);
        $object->addRole($role);
        $this->om->persist($object);
        $this->om->flush();
    }
}

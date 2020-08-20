<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Command\Removal;

use Claroline\AppBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Manager\RoleManager;
use Claroline\CoreBundle\Manager\Workspace\WorkspaceManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Removes users from a workspace.
 */
class EmptyWorkspaceCommand extends Command
{
    private $om;
    private $workspaceManager;
    private $roleManager;

    public function __construct(ObjectManager $om, WorkspaceManager $workspaceManager, RoleManager $roleManager)
    {
        $this->om = $om;
        $this->workspaceManager = $workspaceManager;
        $this->roleManager = $roleManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('claroline:workspace:empty')
            ->setDescription('Empty workspaces');
        $this->setDefinition(
            [
                new InputArgument('workspace_code', InputArgument::OPTIONAL, 'The workspace code'),
                new InputArgument('role_key', InputArgument::OPTIONAL, 'The role key'),
            ]
        );
        $this->addOption(
            'user',
            'u',
            InputOption::VALUE_NONE,
            'When set to true, remove users from the workspace'
        );

        $this->addOption(
            'group',
            'g',
            InputOption::VALUE_NONE,
            'When set to true, remove groups from the workspace'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $removeUsers = $input->getOption('user');
        $removeGroups = $input->getOption('group');

        $helper = $this->getHelper('question');
        $question = new Question('Filter on code (continue if no filter)', null);
        $code = $helper->ask($input, $output, $question);

        if (!$code) {
            $code = $input->getArgument('workspace_code');
        }

        $question = new Question('Filter on name (continue if no filter)', null);
        $name = $helper->ask($input, $output, $question);
        $workspaces = $this->workspaceManager->getNonPersonalByCodeAndName($code, $name);

        foreach ($workspaces as $workspace) {
            $roles = $this->roleManager->getWorkspaceRoles($workspace);

            $roleNames = array_map(function ($role) {
                return $role->getTranslationKey();
            }, $roles);
            $roleNames[] = 'NONE';

            $questionString = "Pick a role list for [{$workspace->getName()} - {$workspace->getCode()}]:";
            $question = new ChoiceQuestion($questionString, $roleNames);
            $question->setMultiselect(true);
            $roleNames = $helper->ask($input, $output, $question);

            if (!$roleNames) {
                $roleNames = [$input->getArgument('role_key')];
            }

            $pickedRoles = array_filter($roles, function ($role) use ($roleNames) {
                return in_array($role->getTranslationKey(), $roleNames);
            });

            $this->om->startFlushSuite();

            foreach ($pickedRoles as $role) {
                if ($removeUsers) {
                    $count = $this->om->getRepository('ClarolineCoreBundle:User')->countUsersByRole($role);
                    $output->writeln("Removing {$count} users from role {$role->getTranslationKey()}");
                    $this->roleManager->emptyRole($role, RoleManager::EMPTY_USERS);
                }

                if ($removeGroups) {
                    $count = $this->om->getRepository('ClarolineCoreBundle:Group')->countGroupsByRole($role);
                    $output->writeln("Removing {$count} groups from role {$role->getTranslationKey()}");
                    $this->roleManager->emptyRole($role, RoleManager::EMPTY_GROUPS);
                }
            }

            $this->om->endFlushSuite();
        }
    }
}

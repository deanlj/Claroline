<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Entity\User;

class ToolRepository extends EntityRepository
{
    /**
     * Returns the workspace tools visible by a set of roles.
     *
     * @param string[]          $roles
     * @param Workspace $workspace
     *
     * @return Tool[]
     *
     * @throws \RuntimeException
     */
    public function findDisplayedByRolesAndWorkspace(array $roles, Workspace $workspace)
    {
        $isAdmin = false;

        foreach ($roles as $role) {
            if ($role === 'ROLE_ADMIN' || $role === 'ROLE_WS_MANAGER_' . $workspace->getGuid()) {
                $isAdmin = true;
            }
        }

        if (!$isAdmin) {
            if (null === $firstRole = array_shift($roles)) {
                throw new \RuntimeException('The roles array cannot be empty');
            }

            $dql = "
                SELECT tool FROM Claroline\CoreBundle\Entity\Tool\Tool tool
                JOIN tool.orderedTools ot
                JOIN ot.workspace ws
                JOIN ot.roles role
                WHERE role.name = :firstRole and ws.id = {$workspace->getId()}
            ";

            foreach ($roles as $key => $role) {
                $dql .= " OR role.name = :role_{$key} and ws.id = {$workspace->getId()}";
            }

            $dql .= ' ORDER BY ot.order';
            $query = $this->_em->createQuery($dql);
            $query->setParameter('firstRole', $firstRole);

            foreach ($roles as $key => $role) {
                $query->setParameter('role_'.$key, $role);
            }

        } else {
            $dql = '
                SELECT tool
                FROM Claroline\CoreBundle\Entity\Tool\Tool tool
                WHERE tool.isDisplayableInWorkspace = true
            ';

            $query = $this->_em->createQuery($dql);
        }

        return $query->getResult();
    }

    /**
     * Returns the visible tools in a user's desktop.
     *
     * @param User $user
     *
     * @return array[Tool]
     */
    public function findDesktopDisplayedToolsByUser(User $user)
    {
        $dql = "
            SELECT tool FROM Claroline\CoreBundle\Entity\Tool\Tool tool
            JOIN tool.orderedTools ot
            JOIN ot.user user
            WHERE user.id = {$user->getId()}
            AND ot.isVisibleInDesktop = true
            ORDER BY ot.order
        ";
        $query = $this->_em->createQuery($dql);

        return $query->getResult();
    }

    /**
     * Returns the non-visible tools in a user's desktop.
     *
     * @param User $user
     *
     * @return array[Tool]
     */
    public function findDesktopUndisplayedToolsByUser(User $user)
    {
        $dql = "
            SELECT tool
            FROM Claroline\CoreBundle\Entity\Tool\Tool tool
            WHERE tool NOT IN (
                SELECT tool_2
                FROM Claroline\CoreBundle\Entity\Tool\Tool tool_2
                JOIN tool_2.orderedTools ot_2
                JOIN ot_2.user user_2
                WHERE user_2.id = {$user->getId()}
            )
            AND tool.isDisplayableInDesktop = true
        ";
        $query = $this->_em->createQuery($dql);

        return $query->getResult();
    }

    /**
     * Returns the non-visible tools in a workspace.
     *
     * @param Workspace $workspace
     *
     * @return array[Tool]
     */
    public function findUndisplayedToolsByWorkspace(Workspace $workspace)
    {
        $dql = "
            SELECT tool
            FROM Claroline\CoreBundle\Entity\Tool\Tool tool
            WHERE tool.isDisplayableInWorkspace = true
            AND tool NOT IN (
                SELECT tool_2 FROM Claroline\CoreBundle\Entity\Tool\Tool tool_2
                JOIN tool_2.orderedTools ot
                JOIN ot.workspace ws
                WHERE ws.id = {$workspace->getId()}
                AND tool.isDisplayableInWorkspace = true
            )
        ";
        $query = $this->_em->createQuery($dql);

        return $query->getResult();
    }

    /**
     * Returns the visible tools in a workspace.
     *
     * @param Workspace $workspace
     *
     * @return array[Tool]
     */
    public function findDisplayedToolsByWorkspace(Workspace $workspace)
    {
        $dql = '
            SELECT tool
            FROM Claroline\CoreBundle\Entity\Tool\Tool tool
            JOIN tool.orderedTools ot
            JOIN ot.workspace ws
            WHERE ws = :workspace
        ';
        $query = $this->_em->createQuery($dql);
        $query->setParameter('workspace', $workspace);

        return $query->getResult();
    }

    /**
     * Returns the number of tools visible in a workspace.
     *
     * @param Workspace $workspace
     *
     * @return integer
     */
    public function countDisplayedToolsByWorkspace(Workspace $workspace)
    {
        $dql = "
            SELECT count(tool)
            FROM Claroline\CoreBundle\Entity\Tool\Tool tool
            JOIN tool.orderedTools ot
            JOIN ot.workspace ws
            WHERE ws.id = {$workspace->getId()}
        ";
        $query = $this->_em->createQuery($dql);

        return $query->getSingleScalarResult();
    }

    public function findToolsDispayableInWorkspace()
    {
        $dql = '
            SELECT t
            FROM Claroline\CoreBundle\Entity\Tool\Tool t
            WHERE t.isDisplayableInWorkspace = true
        ';
        $query = $this->_em->createQuery($dql);

        return $query->getResult();
    }
}

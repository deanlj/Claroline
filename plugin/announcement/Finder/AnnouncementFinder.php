<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\AnnouncementBundle\Finder;

use Claroline\AnnouncementBundle\Entity\Announcement;
use Claroline\AppBundle\API\Finder\AbstractFinder;
use Doctrine\ORM\QueryBuilder;
use JMS\DiExtraBundle\Annotation as DI;

/**
 * @DI\Service("claroline.api.finder.announcement")
 * @DI\Tag("claroline.finder")
 */
class AnnouncementFinder extends AbstractFinder
{
    public function getClass()
    {
        return Announcement::class;
    }

    public function configureQueryBuilder(QueryBuilder $qb, array $searches = [], array $sortBy = null, array $options = ['count' => false, 'page' => 0, 'limit' => -1])
    {
        foreach ($searches as $filterName => $filterValue) {
            switch ($filterName) {
                case 'creator':
                    $qb->leftJoin('obj.creator', 'creator');
                    $qb->andWhere("creator.username LIKE :{$filterName}");
                    $qb->setParameter($filterName, '%'.$filterValue.'%');
                    break;
                case 'workspace':
                    $qb->leftJoin('obj.aggregate', 'a');
                    $qb->leftJoin('a.resourceNode', 'node');
                    $qb->leftJoin('node.workspace', 'w');
                    $qb->andWhere("w.uuid = :{$filterName}");
                    $qb->setParameter($filterName, $filterValue);
                    break;
                case 'meta.publishedAt':
                    break;
                case 'notDoneYet':
                  $now = new \DateTime();
                  if ($filterValue) {
                      $qb->andWhere("obj.publicationDate >= :{$filterName}");
                  } else {
                      $qb->andWhere("obj.publicationDate <= :{$filterName}");
                  }
                  $qb->setParameter($filterName, $now);
                  break;
                case 'visible':
                  if ($filterValue) {
                      $now = new \DateTime();
                      $expr[] = $qb->expr()->orX(
                        $qb->expr()->gte('obj.visibleFrom', $now),
                        $qb->expr()->isNull('obj.visibleFrom')
                      );
                      $expr[] = $qb->expr()->orX(
                        $qb->expr()->lte('obj.visibleUntil', $now),
                        $qb->expr()->isNull('obj.visibleUntil')
                      );
                      $qb->expr()->andX(...$expr);
                      $qb->andWhere('obj.visible = true');
                  }
                  break;
                case 'user':
                  $byUserSearch = $byGroupSearch = $searches;
                  $byUserSearch['_user'] = $filterValue;
                  $byGroupSearch['_group'] = $filterValue;
                  unset($byUserSearch['user']);
                  unset($byGroupSearch['user']);

                  return $this->union($byUserSearch, $byGroupSearch, $options, $sortBy);
                  break;
                case '_user':
                  $qb->leftJoin('obj.aggregate', 'a');
                  $qb->leftJoin('a.resourceNode', 'node');
                  $qb->leftJoin('node.workspace', 'w');
                  $qb->leftJoin('w.roles', 'r');
                  $qb->leftJoin('r.users', 'ru');
                  $qb->andWhere($qb->expr()->orX(
                      $qb->expr()->eq('ru.uuid', ':_userUuid'),
                      $qb->expr()->eq('ru.id', ':_userId')
                  ));
                  $qb->andWhere('r.name != :roleUser');
                  $qb->setParameter('_userUuid', $filterValue);
                  $qb->setParameter('_userId', $filterValue);
                  $qb->setParameter('roleUser', 'ROLE_USER');
                  break;
                case '_group':
                  $qb->leftJoin('obj.aggregate', 'a');
                  $qb->leftJoin('a.resourceNode', 'node');
                  $qb->leftJoin('node.workspace', 'w');
                  $qb->leftJoin('w.roles', 'r');
                  $qb->leftJoin('r.groups', 'rg');
                  $qb->leftJoin('rg.users', 'rgu');
                  $qb->andWhere($qb->expr()->orX(
                      $qb->expr()->eq('rgu.uuid', ':_groupUserId'),
                      $qb->expr()->eq('rgu.id', ':_groupUserUuid')
                  ));
                  $qb->andWhere('r.name != :roleUser');
                  $qb->setParameter('_groupUserId', $filterValue);
                  $qb->setParameter('_groupUserUuid', $filterValue);
                  $qb->setParameter('roleUser', 'ROLE_USER');
                  break;
                default:
                    $this->setDefaults($qb, $filterName, $filterValue);
            }
        }

        if (!is_null($sortBy) && isset($sortBy['property']) && isset($sortBy['direction'])) {
            $sortByProperty = $sortBy['property'];
            $sortByDirection = 1 === $sortBy['direction'] ? 'ASC' : 'DESC';

            switch ($sortByProperty) {
                case 'meta.publishedAt':
                    $qb->orderBy('obj.publicationDate', $sortByDirection);
                    break;
            }
        }
    }
}

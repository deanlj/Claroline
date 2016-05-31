<?php

namespace UJM\ExoBundle\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * ChoiceRepository.
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 *
 * @deprecated Not used anymore to delete
 */
class ChoiceRepository extends EntityRepository
{
    /**
     * right choice for an QCM interaction.
     *
     *
     * @param int $interactionId id InteractionQCM
     *
     * Return array[Choice]
     */
    public function getRightChoice($interactionId)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->join('c.interactionQCM', 'iqcm')
            ->where($qb->expr()->in('iqcm.id', $interactionId))
            ->andWhere('c.rightResponse = 1');

        return $qb->getQuery()->getResult();
    }
}

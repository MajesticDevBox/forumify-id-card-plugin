<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;

/**
 * Extends Forumify's AbstractRepository (rather than a plain ServiceEntityRepository)
 * specifically so the generic repository() Twig function - which requires it - can be used
 * to look up a card from outside this plugin, e.g. from Command Net's optional personnel
 * file integration.
 *
 * @extends AbstractRepository<IdentificationCard>
 */
class IdentificationCardRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return IdentificationCard::class;
    }

    public function search(string $search, string $source, string $status, int $page): array
    {
        $qb = $this->createQueryBuilder('c');
        if ($search !== '') {
            $qb->andWhere('c.displayName LIKE :q OR c.memberId LIKE :q')->setParameter('q', '%'.$search.'%');
        }
        if (in_array($source, ['manual', 'milhq', 'commandnet'], true)) {
            $qb->andWhere('c.source = :source')->setParameter('source', $source);
        }
        if ($status === 'revoked') {
            $qb->andWhere("c.status = 'revoked' OR c.revokedAt IS NOT NULL");
        } elseif (in_array($status, ['active', 'expired'], true)) {
            $qb->andWhere("c.status != 'revoked' AND c.revokedAt IS NULL")->setParameter('now', new \DateTimeImmutable());
            $qb->andWhere($status === 'expired' ? "(c.expirationDate < :now OR c.status = 'expired')" : "(c.expirationDate >= :now AND c.status = 'active')");
        }
        $total = (int) (clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        return ['cards' => $qb->orderBy('c.createdAt', 'DESC')->setFirstResult(($page - 1) * 20)->setMaxResults(20)->getQuery()->getResult(), 'total' => $total];
    }
}

<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Service;

use MajesticDev\ForumifyIdCard\DTO\CardData;
use MajesticDev\ForumifyIdCard\Entity\UnitMapping;

class MilhqCardProvider extends AbstractCardProvider
{
    private const SOLDIER = 'Forumify\\Milhq\\Entity\\Soldier';

    protected function soldierEntityClass(): string
    {
        return self::SOLDIER;
    }

    protected function syncMeta(): array
    {
        return ['source' => 'milhq', 'soldierIdProperty' => 'milhqSoldierId', 'revokeMessage' => 'MILHQ personnel status changed.'];
    }

    public function searchSoldiers(string $query): array
    {
        if (!$this->isAvailable()) {
            return [];
        }
        $soldiers = $this->registry->getRepository(self::SOLDIER)->createQueryBuilder('s')
            ->where('s.name LIKE :q')->setParameter('q', '%'.mb_substr($query, 0, 100).'%')
            ->orderBy('s.name', 'ASC')->setMaxResults(25)->getQuery()->getResult();
        return array_map(static fn ($s) => ['id' => $s->getId(), 'name' => $s->getName()], $soldiers);
    }

    public function resolveCardData(int $soldierId, string $preference = 'auto'): CardData
    {
        $soldier = $this->getSoldier($soldierId);
        if ($soldier === null) {
            throw new \DomainException('MILHQ record unavailable. Existing card data has been preserved.');
        }
        $defaults = $this->settings->all();
        $unit = $soldier->getUnit();
        $mapping = $unit ? $this->registry->getRepository(UnitMapping::class)->findOneBy(['milhqUnitId' => $unit->getId(), 'enabled' => true]) : null;
        $organization = $mapping
            ? [$mapping->organizationLine1, $mapping->organizationLine2, $mapping->organizationLine3]
            : [$defaults['organizationLine1'], $defaults['organizationLine2'], $unit?->getName() ?? $defaults['organizationLine3']];
        $photo = null;
        $source = 'default';
        if (in_array($preference, ['auto', 'milhq_uniform'], true) && $soldier->getUniform()) {
            $photo = $soldier->getUniform();
            $source = 'milhq_uniform';
        } elseif ($preference !== 'default' && $soldier->getUser()?->getAvatar()) {
            $photo = $soldier->getUser()->getAvatar();
            $source = 'forumify_avatar';
        }
        // MILHQ statuses are free-form organization labels. Only explicit terminal labels revoke.
        $label = mb_strtolower(trim($soldier->getStatus()?->getName() ?? ''));
        $status = in_array($label, ['discharged', 'revoked', 'terminated'], true) ? 'revoked' : null;
        return new CardData($soldier->getName(), $organization, $photo, $source, $status, $mapping ? null : 'No unit mapping found. Default organization lines and the current unit name were used.');
    }
}

<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Service;

use Doctrine\Persistence\ManagerRegistry;
use MajesticDev\ForumifyIdCard\DTO\CardData;
use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;
use MajesticDev\ForumifyIdCard\Entity\UnitMapping;

class MilhqCardProvider
{
    private const SOLDIER = 'Forumify\\Milhq\\Entity\\Soldier';

    public function __construct(private readonly ManagerRegistry $registry, private readonly CardSettings $settings) {}

    public function isAvailable(): bool
    {
        return class_exists(self::SOLDIER) && $this->registry->getManagerForClass(self::SOLDIER) !== null;
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

    public function getSoldier(int $id): ?object
    {
        return $this->isAvailable() ? $this->registry->getRepository(self::SOLDIER)->find($id) : null;
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

    public function syncCard(IdentificationCard $card): ?string
    {
        if ($card->source !== 'milhq' || !$card->milhqSoldierId) {
            return null;
        }
        $data = $this->resolveCardData($card->milhqSoldierId);
        if ($card->syncName) { $card->displayName = $data->name; }
        if ($card->syncOrganization) { [$card->organizationLine1, $card->organizationLine2, $card->organizationLine3] = $data->organization; }
        if ($card->syncPhoto && $card->photoSource !== 'custom') { $card->photo = $data->photo; $card->photoSource = $data->photoSource; }
        if ($card->syncStatus && $data->status === 'revoked') { $card->revoke('MILHQ personnel status changed.'); }
        $card->updatedAt = new \DateTimeImmutable();
        return $data->warning;
    }
}

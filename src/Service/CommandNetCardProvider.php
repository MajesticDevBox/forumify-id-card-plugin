<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Service;

use Doctrine\Persistence\ManagerRegistry;
use MajesticDev\ForumifyIdCard\DTO\CardData;
use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;

/**
 * A second, optional personnel source alongside MilhqCardProvider, for communities running
 * the private "Command Net" personnel plugin instead of (or alongside) MILHQ. Mirrors
 * MilhqCardProvider's optional-dependency shape exactly: class_exists() and a mapped
 * Doctrine manager both have to be true before anything here touches the database, so this
 * is fully inert - and never rendered anywhere - on an install that doesn't have it.
 */
class CommandNetCardProvider
{
    private const SOLDIER = 'MajesticDev\\CommandNet\\Entity\\SoldierProfile';

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
            ->join('s.user', 'u')
            ->where('u.displayName LIKE :q')->setParameter('q', '%'.mb_substr($query, 0, 100).'%')
            ->orderBy('u.displayName', 'ASC')->setMaxResults(25)->getQuery()->getResult();
        return array_map(static fn ($s) => ['id' => $s->getId(), 'name' => $s->getUser()->getDisplayName()], $soldiers);
    }

    public function getSoldier(int $id): ?object
    {
        return $this->isAvailable() ? $this->registry->getRepository(self::SOLDIER)->find($id) : null;
    }

    public function resolveCardData(int $soldierId, string $preference = 'auto'): CardData
    {
        $soldier = $this->getSoldier($soldierId);
        if ($soldier === null) {
            throw new \DomainException('Command Net record unavailable. Existing card data has been preserved.');
        }
        $defaults = $this->settings->all();
        $unit = $soldier->getPrimaryAssignment()?->getUnit();
        // No dedicated unit-mapping table for this source (unlike MILHQ): the unit's own
        // name is already a readable organization line, so the community default lines
        // plus that name are used directly.
        $organization = [$defaults['organizationLine1'], $defaults['organizationLine2'], $unit?->getName() ?? $defaults['organizationLine3']];
        $photo = null;
        $source = 'default';
        if ($preference !== 'default' && $soldier->getUser()->getAvatar()) {
            $photo = $soldier->getUser()->getAvatar();
            $source = 'forumify_avatar';
        }
        // Only a discharge ends personnel status outright; leave/AWOL/retired are not
        // treated as terminal here, unlike MILHQ's free-form status labels.
        $status = $soldier->getStatus()->value === 'discharged' ? 'revoked' : null;
        return new CardData($soldier->getUser()->getDisplayName(), $organization, $photo, $source, $status);
    }

    public function syncCard(IdentificationCard $card): ?string
    {
        if ($card->source !== 'commandnet' || !$card->commandNetSoldierId) {
            return null;
        }
        $data = $this->resolveCardData($card->commandNetSoldierId);
        if ($card->syncName) { $card->displayName = $data->name; }
        if ($card->syncOrganization) { [$card->organizationLine1, $card->organizationLine2, $card->organizationLine3] = $data->organization; }
        if ($card->syncPhoto && $card->photoSource !== 'custom') { $card->photo = $data->photo; $card->photoSource = $data->photoSource; }
        if ($card->syncStatus && $data->status === 'revoked') { $card->revoke('Command Net personnel status changed.'); }
        $card->updatedAt = new \DateTimeImmutable();
        return $data->warning;
    }
}

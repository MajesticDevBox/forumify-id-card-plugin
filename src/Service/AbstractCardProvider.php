<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Service;

use Doctrine\Persistence\ManagerRegistry;
use MajesticDev\ForumifyIdCard\DTO\CardData;
use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;

/**
 * MilhqCardProvider and CommandNetCardProvider are two optional, mutually-exclusive
 * personnel sources with the identical optional-dependency shape (class_exists() plus a
 * mapped Doctrine manager) and an identical syncCard() skeleton. What differs between them -
 * searchSoldiers()'s query, resolveCardData()'s unit-mapping/photo-fallback/status logic - is
 * real, schema-driven divergence documented on each subclass, not accidental duplication, so
 * only the genuinely identical pieces are pulled up here.
 */
abstract class AbstractCardProvider
{
    public function __construct(protected readonly ManagerRegistry $registry, protected readonly CardSettings $settings) {}

    /** @return class-string the optional personnel plugin's soldier/profile entity */
    abstract protected function soldierEntityClass(): string;

    /** @return array{source: string, soldierIdProperty: string, revokeMessage: string} */
    abstract protected function syncMeta(): array;

    public function isAvailable(): bool
    {
        return class_exists($this->soldierEntityClass()) && $this->registry->getManagerForClass($this->soldierEntityClass()) !== null;
    }

    public function getSoldier(int $id): ?object
    {
        return $this->isAvailable() ? $this->registry->getRepository($this->soldierEntityClass())->find($id) : null;
    }

    abstract public function searchSoldiers(string $query): array;

    abstract public function resolveCardData(int $soldierId, string $preference = 'auto'): CardData;

    public function syncCard(IdentificationCard $card): ?string
    {
        $meta = $this->syncMeta();
        $soldierId = $card->{$meta['soldierIdProperty']};
        if ($card->source !== $meta['source'] || !$soldierId) {
            return null;
        }
        $data = $this->resolveCardData($soldierId);
        if ($card->syncName) { $card->displayName = $data->name; }
        if ($card->syncOrganization) { [$card->organizationLine1, $card->organizationLine2, $card->organizationLine3] = $data->organization; }
        if ($card->syncPhoto && $card->photoSource !== 'custom') { $card->photo = $data->photo; $card->photoSource = $data->photoSource; }
        if ($card->syncStatus && $data->status === 'revoked') { $card->revoke($meta['revokeMessage']); }
        $card->updatedAt = new \DateTimeImmutable();
        return $data->warning;
    }
}

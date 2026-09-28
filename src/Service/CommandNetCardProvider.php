<?php

declare(strict_types=1);

namespace MajesticDev\MilsimIdCard\Service;

use MajesticDev\MilsimIdCard\DTO\CardData;

/**
 * A second, optional personnel source alongside MilhqCardProvider, for communities running
 * the private "Command Net" personnel plugin instead of (or alongside) MILHQ. Mirrors
 * MilhqCardProvider's optional-dependency shape exactly: class_exists() and a mapped
 * Doctrine manager both have to be true before anything here touches the database, so this
 * is fully inert - and never rendered anywhere - on an install that doesn't have it.
 */
class CommandNetCardProvider extends AbstractCardProvider
{
    private const SOLDIER = 'MajesticDev\\CommandNet\\Entity\\SoldierProfile';

    protected function soldierEntityClass(): string
    {
        return self::SOLDIER;
    }

    protected function syncMeta(): array
    {
        return ['source' => 'commandnet', 'soldierIdProperty' => 'commandNetSoldierId', 'revokeMessage' => 'Command Net personnel status changed.'];
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

    public function resolveCardData(int $soldierId, string $preference = 'auto'): CardData
    {
        $soldier = $this->getSoldier($soldierId);
        if ($soldier === null) {
            throw new \DomainException('Command Net record unavailable. Existing card data has been preserved.');
        }
        $defaults = $this->settings->all();
        $assignment = $soldier->getPrimaryAssignment();
        $organization = $this->buildOrganization($assignment?->getUnit(), $assignment?->getSquad(), $defaults);
        $photo = null;
        $source = 'default';
        if ($preference !== 'default' && $soldier->getUser()->getAvatar()) {
            $photo = $soldier->getUser()->getAvatar();
            $source = 'forumify_avatar';
        }
        // Only a discharge ends personnel status outright; leave/AWOL/retired are not
        // treated as terminal here, unlike MILHQ's free-form status labels.
        $status = $soldier->getStatus()->value === 'discharged' ? 'revoked' : null;
        $qualifications = array_map(
            static fn ($sq) => ['name' => $sq->getQualification()->getName(), 'tier' => $sq->getQualification()->getTier()?->value],
            $soldier->getQualifications()->toArray(),
        );
        $awards = array_map(static fn ($sa) => ['name' => $sa->getAward()->getName()], $soldier->getAwards()->toArray());
        return new CardData(
            $soldier->getUser()->getDisplayName(), $organization, $photo, $source, $status,
            rank: $soldier->getRank()?->getName(),
            specialty: $soldier->getSpecialty()?->getName(),
            callsign: $soldier->getCallsign(),
            qualifications: $qualifications,
            awards: $awards,
        );
    }

    /**
     * Auto-populates the three organization lines from the soldier's own unit/squad
     * assignment instead of requiring a mapping to be entered per unit (unlike MILHQ's
     * UnitMapping table): line 1 is the community-wide default (set once in Configuration),
     * line 2 is the assigned unit's own chain root-to-self (e.g. "3rd Infantry Division, 75th
     * Ranger Regiment, 1st Air Cavalry Brigade, Detachment 7"), and line 3 is the soldier's
     * squad/team within that unit, if one is set - squads/teams aren't Units themselves (see
     * commandnet-plugin's Squad entity), so this reads Assignment::getSquad() rather than
     * walking the unit tree any further.
     *
     * @param array<string, mixed> $defaults
     * @return array{0: string, 1: string, 2: string}
     */
    private function buildOrganization(?object $unit, ?object $squad, array $defaults): array
    {
        if ($unit === null) {
            return [$defaults['organizationLine1'], $defaults['organizationLine2'], $defaults['organizationLine3']];
        }
        $chain = [$unit->getName()];
        for ($ancestor = $unit->getParent(); $ancestor !== null; $ancestor = $ancestor->getParent()) {
            $chain[] = $ancestor->getName();
        }
        $line2 = implode(', ', array_reverse($chain));
        $line3 = $squad?->getName() ?? $defaults['organizationLine3'];
        return [$defaults['organizationLine1'], $line2, $line3];
    }
}

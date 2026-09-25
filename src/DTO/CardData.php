<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\DTO;

final readonly class CardData
{
    /**
     * @param array<int, array{name: string, tier: ?string}> $qualifications
     * @param array<int, array{name: string}> $awards
     */
    public function __construct(
        public string $name,
        public array $organization,
        public ?string $photo,
        public string $photoSource,
        public ?string $status,
        public ?string $warning = null,
        public ?string $rank = null,
        public ?string $specialty = null,
        public ?string $callsign = null,
        public array $qualifications = [],
        public array $awards = [],
    ) {}
}

<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\DTO;

final readonly class CardData
{
    public function __construct(
        public string $name,
        public array $organization,
        public ?string $photo,
        public string $photoSource,
        public ?string $status,
        public ?string $warning = null,
    ) {}
}

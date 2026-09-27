<?php

declare(strict_types=1);

namespace MajesticDev\MilsimIdCard\Service;

use MajesticDev\MilsimIdCard\Entity\IdentificationCard;

final class CardStatusResolver
{
    public function resolve(IdentificationCard $card, ?\DateTimeImmutable $now = null): string
    {
        if ($card->status === 'revoked' || $card->revokedAt !== null) {
            return 'revoked';
        }
        return ($now ?? new \DateTimeImmutable()) > $card->expirationDate || $card->status === 'expired' ? 'expired' : 'active';
    }
}

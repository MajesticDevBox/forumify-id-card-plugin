<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Service;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;

class CardIssuer
{
    public function __construct(private readonly ManagerRegistry $registry, private readonly MemberIdGenerator $ids) {}

    public function allocate(): string
    {
        return $this->ids->generate(fn (string $id) => $this->registry->getRepository(IdentificationCard::class)->findOneBy(['memberId' => $id]) !== null);
    }

    /** Retry a concurrent unique-ID insert with a fresh manager after Doctrine closes the failed one. */
    public function save(IdentificationCard $card): void
    {
        $new = $card->id === null;
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $em = $this->registry->getManagerForClass(IdentificationCard::class);
            try {
                $card->updatedAt = new \DateTimeImmutable();
                $em->persist($card);
                $em->flush();
                return;
            } catch (UniqueConstraintViolationException $e) {
                if (!$new) { throw new \DomainException('Member ID is already in use. Regenerate it and save again.', previous: $e); }
                $this->registry->resetManager();
                $card->id = null;
                $card->memberId = $this->allocate();
                $card->qrToken = bin2hex(random_bytes(32));
            }
        }
        throw new \DomainException('Could not allocate a unique card. Please try again.');
    }
}

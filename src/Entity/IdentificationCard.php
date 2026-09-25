<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Entity;

use Doctrine\ORM\Mapping as ORM;
use MajesticDev\ForumifyIdCard\Repository\IdentificationCardRepository;
use MajesticDev\ForumifyIdCard\Service\ExpirationCalculator;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: IdentificationCardRepository::class)]
#[ORM\Table(name: 'majestic_id_card')]
#[ORM\Index(columns: ['source', 'milhq_soldier_id'], name: 'idx_id_card_source')]
#[ORM\Index(columns: ['source', 'command_net_soldier_id'], name: 'idx_id_card_source_cn')]
#[ORM\Index(columns: ['status', 'expiration_date'], name: 'idx_id_card_status')]
class IdentificationCard
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Column(length: 10)]
    #[Assert\Choice(['manual', 'milhq', 'commandnet'])]
    public string $source = 'manual';
    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    public ?int $milhqSoldierId = null;
    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    public ?int $commandNetSoldierId = null;
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank, Assert\Length(max: 100)]
    public string $displayName = '';
    #[ORM\Column(length: 6, unique: true)]
    #[Assert\Regex('/^\d{6}$/D')]
    public string $memberId = '';
    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100)]
    public string $organizationLine1 = '2nd Ranger Battalion';
    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100)]
    public string $organizationLine2 = 'Misfit Company';
    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100)]
    public string $organizationLine3 = 'Misfit - 1 C';
    #[ORM\Column(length: 255, nullable: true)]
    public ?string $photo = null;
    #[ORM\Column(length: 24)]
    public string $photoSource = 'default';
    #[ORM\Column(type: 'date_immutable')]
    public \DateTimeImmutable $issueDate;
    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\GreaterThan(propertyPath: 'issueDate')]
    public \DateTimeImmutable $expirationDate;
    #[ORM\Column]
    public bool $expirationOverride = false;
    #[ORM\Column(length: 64, unique: true)]
    public string $qrToken;
    #[ORM\Column(length: 10)]
    #[Assert\Choice(['active', 'expired', 'revoked'])]
    public string $status = 'active';
    #[ORM\Column]
    public bool $autoSyncMilhq = false;
    #[ORM\Column]
    public bool $autoSyncCommandNet = false;
    #[ORM\Column]
    public bool $syncName = true;
    #[ORM\Column]
    public bool $syncOrganization = true;
    #[ORM\Column]
    public bool $syncPhoto = true;
    #[ORM\Column]
    public bool $syncStatus = false;
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    public ?string $rank = null;
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    public ?string $specialty = null;
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    public ?string $callsign = null;
    /** @var array<int, array{name: string, tier: ?string}> */
    #[ORM\Column(type: 'json')]
    public array $qualifications = [];
    /** @var array<int, array{name: string}> */
    #[ORM\Column(type: 'json')]
    public array $awards = [];
    #[ORM\Column]
    public bool $syncQualifications = true;
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $revokedAt = null;
    #[ORM\Column(length: 500, nullable: true)]
    public ?string $revocationReason = null;
    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 5000)]
    public ?string $notes = null;

    public function __construct()
    {
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
        $this->issueDate = new \DateTimeImmutable('today');
        $this->expirationDate = (new ExpirationCalculator())->calculate($this->issueDate);
        $this->qrToken = bin2hex(random_bytes(32));
    }

    public function revoke(string $reason): void
    {
        $this->status = 'revoked';
        $this->revokedAt ??= new \DateTimeImmutable();
        $this->revocationReason = $reason;
        $this->updatedAt = new \DateTimeImmutable();
    }
}

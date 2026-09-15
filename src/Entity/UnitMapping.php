<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'majestic_id_unit_mapping')]
class UnitMapping
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Column(unique: true)]
    #[Assert\Positive]
    public int $milhqUnitId = 1;
    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100)]
    public string $milhqUnitName = '';
    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100)]
    public string $organizationLine1 = '2nd Ranger Battalion';
    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100)]
    public string $organizationLine2 = 'Misfit Company';
    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100)]
    public string $organizationLine3 = 'Misfit - 1 C';
    #[ORM\Column]
    public bool $enabled = true;
}

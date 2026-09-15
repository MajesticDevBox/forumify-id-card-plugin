<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Service;

use Forumify\Core\Repository\SettingRepository;

class CardSettings
{
    public const DEFAULTS = [
        'organizationLine1' => '2nd Ranger Battalion', 'organizationLine2' => 'Misfit Company', 'organizationLine3' => 'Misfit - 1 C',
        'years' => 5, 'header' => 'SPEARHEAD GAMING', 'subtitle' => 'MILSIM IDENTIFICATION CARD',
        'disclaimer' => 'NOT A GOVERNMENT ID', 'baseUrl' => '', 'logo' => null,
    ];

    public function __construct(private readonly SettingRepository $settings) {}

    public function all(): array
    {
        return array_replace(self::DEFAULTS, (array) $this->settings->get('id-cards.configuration'));
    }

    public function save(array $data): void
    {
        $this->settings->set('id-cards.configuration', array_intersect_key($data, self::DEFAULTS));
    }
}

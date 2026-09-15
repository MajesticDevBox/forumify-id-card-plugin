<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Service;

use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;

class CardRenderer
{
    public function __construct(private readonly QrCodeGenerator $qr, private readonly PhotoStorage $photos, private readonly CardSettings $settings, private readonly CardStatusResolver $status) {}

    /** Explicit allowlist: never serialize an entity or a MILHQ/User object into a response. */
    public function data(IdentificationCard $card): array
    {
        $settings = $this->settings->all();
        return [
            'name' => $card->displayName, 'memberId' => $card->memberId,
            'organization' => [$card->organizationLine1, $card->organizationLine2, $card->organizationLine3],
            'issue' => $card->issueDate->format('Y-m-d'), 'expiration' => $card->expirationDate->format('Y-m-d'),
            'status' => $this->status->resolve($card), 'photo' => $this->photos->url($card),
            'qr' => 'data:image/png;base64,'.base64_encode($this->qr->png($card)), 'verificationUrl' => $this->qr->url($card),
            'header' => $settings['header'], 'subtitle' => $settings['subtitle'], 'disclaimer' => $settings['disclaimer'],
            'logo' => $settings['logo'] ? '/storage/id-cards/'.basename($settings['logo']) : null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Service;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel;
use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class QrCodeGenerator
{
    public function __construct(private readonly UrlGeneratorInterface $router, private readonly CardSettings $settings) {}

    public function url(IdentificationCard $card): string
    {
        $base = rtrim($this->settings->all()['baseUrl'], '/');
        $path = $this->router->generate('id_cards_verify', ['token' => $card->qrToken], UrlGeneratorInterface::ABSOLUTE_PATH);
        return $base !== '' ? $base.$path : $this->router->generate('id_cards_verify', ['token' => $card->qrToken], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function png(IdentificationCard $card): string
    {
        return (new PngWriter())->write(new QrCode(data: $this->url($card), errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 300, margin: 20))->getString();
    }
}

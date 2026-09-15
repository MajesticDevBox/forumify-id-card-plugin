<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Service;

use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoStorage
{
    public function __construct(private readonly string $projectDir, private readonly Packages $assets) {}

    public function upload(UploadedFile $file): string
    {
        if (!$file->isValid() || $file->getSize() > 5 * 1024 * 1024 || !in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \DomainException('Use a JPEG, PNG or WebP image smaller than 5 MB.');
        }
        $size = @getimagesize($file->getPathname());
        if (!$size || $size[0] < 80 || $size[1] < 80 || $size[0] * $size[1] > 24000000) {
            throw new \DomainException('Images must be at least 80 × 80 pixels and no more than 24 megapixels.');
        }
        $image = @imagecreatefromstring((string) file_get_contents($file->getPathname()));
        if (!$image) { throw new \DomainException('The image could not be decoded.'); }
        $ratio = min(1, 1200 / max($size[0], $size[1]));
        $scaled = imagescale($image, max(1, (int) ($size[0] * $ratio)), max(1, (int) ($size[1] * $ratio)));
        $dir = $this->projectDir.'/public/storage/id-cards';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) { throw new \RuntimeException('Photo storage is not writable.'); }
        $name = bin2hex(random_bytes(24)).'.png';
        if (!$scaled || !imagepng($scaled, $dir.'/'.$name)) { throw new \RuntimeException('Could not save photo.'); }
        return $name;
    }

    public function url(IdentificationCard $card): ?string
    {
        if (!$card->photo || basename($card->photo) !== $card->photo || str_contains($card->photo, '\\')) { return null; }
        try { return match ($card->photoSource) {
            'custom' => $this->assets->getUrl('storage/id-cards/'.$card->photo),
            'milhq_uniform' => $this->assets->getUrl($card->photo, 'milhq.asset'),
            'forumify_avatar' => $this->assets->getUrl($card->photo, 'forumify.avatar'),
            default => null,
        }; } catch (\InvalidArgumentException) {
            // Optional MILHQ asset package may disappear when its plugin is removed.
            return null;
        }
    }
}

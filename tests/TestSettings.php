<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Tests;

class TestSettings extends \MajesticDev\ForumifyIdCard\Service\CardSettings
{
    private array $data = self::DEFAULTS;
    public function __construct() {}
    public function all(): array { return $this->data; }
    public function save(array $data): void { $this->data = $data; }
}

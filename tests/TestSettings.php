<?php

declare(strict_types=1);

namespace MajesticDev\MilsimIdCard\Tests;

class TestSettings extends \MajesticDev\MilsimIdCard\Service\CardSettings
{
    private array $data = self::DEFAULTS;
    public function __construct() {}
    public function all(): array { return $this->data; }
    public function save(array $data): void { $this->data = $data; }
}

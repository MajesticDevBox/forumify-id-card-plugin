<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Service;

final class MemberIdGenerator
{
    public function generate(callable $exists, ?callable $random = null): string
    {
        $random ??= random_int(...);
        for ($i = 0; $i < 100; ++$i) {
            $id = sprintf('%06d', $random(0, 999999));
            if (!$exists($id)) {
                return $id;
            }
        }
        throw new \RuntimeException('Could not allocate a member ID. Please try again.');
    }
}

<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Service;

final class ExpirationCalculator
{
    /** February 29 anniversaries clamp to February 28 in non-leap years. */
    public function calculate(\DateTimeImmutable $issue, int $years = 5): \DateTimeImmutable
    {
        if ($years < 1 || $years > 20) {
            throw new \InvalidArgumentException('Expiration years must be between 1 and 20.');
        }
        $year = (int) $issue->format('Y') + $years;
        $month = (int) $issue->format('m');
        $first = $issue->setDate($year, $month, 1);
        return $first->setDate($year, $month, min((int) $issue->format('d'), (int) $first->format('t')))->setTime(23, 59, 59);
    }
}

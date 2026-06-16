<?php

declare(strict_types=1);

namespace Reflexive\ContactForm\Tests\Support;

use Reflexive\ContactForm\Clock\ClockInterface;

final class FixedClock implements ClockInterface
{
    public function __construct(private readonly \DateTimeImmutable $now)
    {
    }

    public static function at(string $dateTime): self
    {
        return new self(new \DateTimeImmutable($dateTime));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}

<?php

declare(strict_types=1);

namespace Reflexive\ContactForm\Clock;

final class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now');
    }
}

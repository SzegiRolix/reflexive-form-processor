<?php

declare(strict_types=1);

namespace Reflexive\ContactForm\Clock;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}

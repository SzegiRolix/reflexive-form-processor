<?php

declare(strict_types=1);

namespace Reflexive\ContactForm\Contract;

interface FormProcessorInterface
{
    /**
     * Process form submission data.
     * Validate, and persist the data as needed.
     *
     * @param array $data Submitted data
     *
     * @return void
     */
    public function process(array $data): void;
}

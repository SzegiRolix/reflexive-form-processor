<?php

declare(strict_types=1);

namespace Reflexive\ContactForm;

use Reflexive\ContactForm\Clock\ClockInterface;
use Reflexive\ContactForm\Clock\SystemClock;
use Reflexive\ContactForm\Contract\ContactFormSubmissionRepositoryInterface;
use Reflexive\ContactForm\Contract\ContactRepositoryInterface;
use Reflexive\ContactForm\Contract\FormProcessorInterface;
use Reflexive\ContactForm\Exception\ValidationException;

final class FormProcessor implements FormProcessorInterface
{
    public function __construct(
        private readonly ContactRepositoryInterface $contactRepository,
        private readonly ContactFormSubmissionRepositoryInterface $submissionRepository,
        private readonly ClockInterface $clock = new SystemClock(),
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     */
    public function process(array $data): void
    {
        $formData = ContactFormData::fromArray($data, $this->clock->now());

        $contactId = $this->resolveContactId($formData);

        $this->submissionRepository->create($formData->toSubmissionArray($contactId));
    }

    private function resolveContactId(ContactFormData $formData): int
    {
        $existing = $this->contactRepository->getContactByEmail($formData->email);

        if ($existing === null) {
            return $this->contactRepository->create($formData->toContactArray());
        }

        $this->contactRepository->update($existing['id'], [
            'first_name' => $formData->firstName,
            'last_name' => $formData->lastName,
        ]);

        return $existing['id'];
    }
}

<?php

declare(strict_types=1);

namespace Reflexive\ContactForm;

use Reflexive\ContactForm\Exception\ValidationException;

final class ContactFormData
{
    public const MAX_NAME_LENGTH = 255;
    public const MAX_EMAIL_LENGTH = 255;
    public const MAX_FIELD_LENGTH = 255;
    public const MAX_SERVICE_LENGTH = 255;
    public const MAX_MESSAGE_LENGTH = 65535;
    public const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    private function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $field,
        public readonly string $service,
        public readonly string $message,
        public readonly string $timestamp,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     */
    public static function fromArray(array $data, \DateTimeImmutable $now): self
    {
        /** @var array<string, list<string>> $errors */
        $errors = [];

        $firstName = self::validateRequiredString($data, 'first_name', self::MAX_NAME_LENGTH, $errors);
        $lastName  = self::validateRequiredString($data, 'last_name', self::MAX_NAME_LENGTH, $errors);
        $email     = self::validateEmail($data, $errors);
        $field     = self::validateRequiredString($data, 'field', self::MAX_FIELD_LENGTH, $errors);
        $service   = self::validateRequiredString($data, 'service', self::MAX_SERVICE_LENGTH, $errors);
        $message   = self::validateRequiredString($data, 'message', self::MAX_MESSAGE_LENGTH, $errors);
        $timestamp = self::validateTimestamp($data, $now, $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self(
            firstName: $firstName ?? '',
            lastName: $lastName ?? '',
            email: $email ?? '',
            field: $field ?? '',
            service: $service ?? '',
            message: $message ?? '',
            timestamp: $timestamp ?? '',
        );
    }

    /**
     * @return array{first_name: string, last_name: string, email: string}
     */
    public function toContactArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
        ];
    }

    /**
     * @return array{contact_id: int, field: string, service: string, message: string, timestamp: string}
     */
    public function toSubmissionArray(int $contactId): array
    {
        return [
            'contact_id' => $contactId,
            'field' => $this->field,
            'service' => $this->service,
            'message' => $this->message,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * @param array<string, mixed>        $data
     * @param array<string, list<string>> $errors
     */
    private static function validateRequiredString(
        array $data,
        string $key,
        int $maxLength,
        array &$errors,
    ): ?string {
        if (!array_key_exists($key, $data)) {
            $errors[$key][] = sprintf('The "%s" field is required.', $key);

            return null;
        }

        $value = $data[$key];

        if (!is_string($value)) {
            $errors[$key][] = sprintf('The "%s" field must be a string.', $key);

            return null;
        }

        $value = trim($value);

        if ($value === '') {
            $errors[$key][] = sprintf('The "%s" field cannot be empty.', $key);

            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            $errors[$key][] = sprintf('The "%s" field cannot be longer than %d characters.', $key, $maxLength);

            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed>        $data
     * @param array<string, list<string>> $errors
     */
    private static function validateEmail(array $data, array &$errors): ?string
    {
        if (!array_key_exists('email', $data)) {
            $errors['email'][] = 'The "email" field is required.';

            return null;
        }

        $value = $data['email'];

        if (!is_string($value)) {
            $errors['email'][] = 'The "email" field must be a string.';

            return null;
        }

        $value = trim($value);

        if ($value === '') {
            $errors['email'][] = 'The "email" field cannot be empty.';

            return null;
        }

        if (mb_strlen($value) > self::MAX_EMAIL_LENGTH) {
            $errors['email'][] = sprintf('The "email" field cannot be longer than %d characters.', self::MAX_EMAIL_LENGTH);

            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'][] = 'The "email" field must be a valid e-mail address.';

            return null;
        }

        return mb_strtolower($value);
    }

    /**
     * @param array<string, mixed>        $data
     * @param array<string, list<string>> $errors
     */
    private static function validateTimestamp(array $data, \DateTimeImmutable $now, array &$errors): ?string
    {
        if (!array_key_exists('timestamp', $data)) {
            return $now->format(self::TIMESTAMP_FORMAT);
        }

        $value = $data['timestamp'];

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === null) {
            return $now->format(self::TIMESTAMP_FORMAT);
        }

        if (!is_string($value)) {
            $errors['timestamp'][] = 'The "timestamp" field must be a string.';

            return null;
        }

        try {
            $parsed = new \DateTimeImmutable($value);
        } catch (\Exception) {
            $errors['timestamp'][] = 'The "timestamp" field must be a valid date/time string.';

            return null;
        }

        return $parsed->format(self::TIMESTAMP_FORMAT);
    }
}

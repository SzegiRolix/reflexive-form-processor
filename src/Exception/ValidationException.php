<?php

declare(strict_types=1);

namespace Reflexive\ContactForm\Exception;

final class ValidationException extends \InvalidArgumentException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'The submitted form data is invalid.',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<string>
     */
    public function getAllMessages(): array
    {
        $messages = [];

        foreach ($this->errors as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = $error;
            }
        }

        return $messages;
    }
}

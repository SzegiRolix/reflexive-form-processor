<?php

declare(strict_types=1);

namespace Reflexive\ContactForm\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Reflexive\ContactForm\ContactFormData;
use Reflexive\ContactForm\Exception\ValidationException;

final class ContactFormDataTest extends TestCase
{
    private const NOW = '2026-06-16 12:00:00';

    #[Test]
    public function it_builds_a_value_object_from_valid_data(): void
    {
        $data = self::validPayload();

        $formData = ContactFormData::fromArray($data, $this->now());

        self::assertSame('John', $formData->firstName);
        self::assertSame('Doe', $formData->lastName);
        self::assertSame('john@example.com', $formData->email);
        self::assertSame('Marketing', $formData->field);
        self::assertSame('SEO', $formData->service);
        self::assertSame('Hello there', $formData->message);
        self::assertSame('2026-05-01 08:30:00', $formData->timestamp);
    }

    #[Test]
    public function it_trims_whitespace_and_lowercases_the_email(): void
    {
        $data = self::validPayload([
            'first_name' => '  John  ',
            'last_name' => "Doe\t",
            'email' => '  John@Example.COM ',
        ]);

        $formData = ContactFormData::fromArray($data, $this->now());

        self::assertSame('John', $formData->firstName);
        self::assertSame('Doe', $formData->lastName);
        self::assertSame('john@example.com', $formData->email);
    }

    #[Test]
    public function it_uses_the_processing_time_when_no_timestamp_is_given(): void
    {
        $data = self::validPayload();
        unset($data['timestamp']);

        $formData = ContactFormData::fromArray($data, $this->now());

        self::assertSame(self::NOW, $formData->timestamp);
    }

    #[Test]
    public function it_uses_the_processing_time_when_timestamp_is_empty(): void
    {
        $data = self::validPayload(['timestamp' => '   ']);

        $formData = ContactFormData::fromArray($data, $this->now());

        self::assertSame(self::NOW, $formData->timestamp);
    }

    #[Test]
    public function it_normalises_a_provided_timestamp(): void
    {
        $data = self::validPayload(['timestamp' => '2026-05-01T08:30:00+00:00']);

        $formData = ContactFormData::fromArray($data, $this->now());

        self::assertSame('2026-05-01 08:30:00', $formData->timestamp);
    }

    #[Test]
    public function it_collects_every_validation_error_at_once(): void
    {
        try {
            ContactFormData::fromArray([], $this->now());
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $errors = $exception->getErrors();

            self::assertArrayHasKey('first_name', $errors);
            self::assertArrayHasKey('last_name', $errors);
            self::assertArrayHasKey('email', $errors);
            self::assertArrayHasKey('field', $errors);
            self::assertArrayHasKey('service', $errors);
            self::assertArrayHasKey('message', $errors);
            self::assertArrayNotHasKey('timestamp', $errors);
        }
    }

    /**
     * @param array<string, mixed> $override
     */
    #[Test]
    #[DataProvider('invalidFieldProvider')]
    public function it_rejects_invalid_fields(array $override, string $expectedErrorKey): void
    {
        $data = self::validPayload($override);

        try {
            ContactFormData::fromArray($data, $this->now());
            self::fail('Expected a ValidationException for field: ' . $expectedErrorKey);
        } catch (ValidationException $exception) {
            self::assertArrayHasKey($expectedErrorKey, $exception->getErrors());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidFieldProvider(): iterable
    {
        yield 'empty first name' => [['first_name' => '   '], 'first_name'];
        yield 'null first name' => [['first_name' => null], 'first_name'];
        yield 'non-string last name' => [['last_name' => ['array']], 'last_name'];
        yield 'invalid email format' => [['email' => 'not-an-email'], 'email'];
        yield 'empty email' => [['email' => ''], 'email'];
        yield 'empty field' => [['field' => ''], 'field'];
        yield 'empty service' => [['service' => ''], 'service'];
        yield 'empty message' => [['message' => ''], 'message'];
        yield 'too long first name' => [['first_name' => str_repeat('a', 256)], 'first_name'];
        yield 'unparseable timestamp' => [['timestamp' => 'definitely-not-a-date'], 'timestamp'];
        yield 'non-string timestamp' => [['timestamp' => 12345], 'timestamp'];
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private static function validPayload(array $override = []): array
    {
        return array_merge([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'field' => 'Marketing',
            'service' => 'SEO',
            'message' => 'Hello there',
            'timestamp' => '2026-05-01 08:30:00',
        ], $override);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }
}

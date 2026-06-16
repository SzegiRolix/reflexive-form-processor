<?php

declare(strict_types=1);

namespace Reflexive\ContactForm\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Reflexive\ContactForm\Contract\ContactFormSubmissionRepositoryInterface;
use Reflexive\ContactForm\Contract\ContactRepositoryInterface;
use Reflexive\ContactForm\Exception\ValidationException;
use Reflexive\ContactForm\FormProcessor;
use Reflexive\ContactForm\Tests\Support\FixedClock;

final class FormProcessorTest extends TestCase
{
    #[Test]
    public function it_creates_a_new_contact_and_a_submission_for_an_unknown_email(): void
    {
        $contacts = $this->createMock(ContactRepositoryInterface::class);
        $submissions = $this->createMock(ContactFormSubmissionRepositoryInterface::class);

        $contacts->method('getContactByEmail')
            ->with('john@example.com')
            ->willReturn(null);

        $contacts->expects(self::once())
            ->method('create')
            ->with([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
            ])
            ->willReturn(42);

        $contacts->expects(self::never())->method('update');

        $submissions->expects(self::once())
            ->method('create')
            ->with([
                'contact_id' => 42,
                'field' => 'Marketing',
                'service' => 'SEO',
                'message' => 'Hello there',
                'timestamp' => '2026-05-01 08:30:00',
            ])
            ->willReturn(1);

        $processor = new FormProcessor($contacts, $submissions);

        $processor->process($this->validPayload());
    }

    #[Test]
    public function it_updates_an_existing_contact_instead_of_creating_a_new_one(): void
    {
        $contacts = $this->createMock(ContactRepositoryInterface::class);
        $submissions = $this->createMock(ContactFormSubmissionRepositoryInterface::class);

        $contacts->method('getContactByEmail')
            ->with('john@example.com')
            ->willReturn([
                'id' => 7,
                'first_name' => 'Johnny',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
            ]);

        $contacts->expects(self::never())->method('create');

        $contacts->expects(self::once())
            ->method('update')
            ->with(7, [
                'first_name' => 'John',
                'last_name' => 'Doe',
            ])
            ->willReturn([
                'id' => 7,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
            ]);

        $submissions->expects(self::once())
            ->method('create')
            ->with(self::callback(static fn (array $data): bool => $data['contact_id'] === 7))
            ->willReturn(1);

        $processor = new FormProcessor($contacts, $submissions);

        $processor->process($this->validPayload());
    }

    #[Test]
    public function it_looks_up_contacts_using_the_normalised_email(): void
    {
        $contacts = $this->createMock(ContactRepositoryInterface::class);
        $submissions = $this->createMock(ContactFormSubmissionRepositoryInterface::class);

        $contacts->expects(self::once())
            ->method('getContactByEmail')
            ->with('john@example.com')
            ->willReturn(null);

        $contacts->method('create')->willReturn(1);
        $submissions->method('create')->willReturn(1);

        $processor = new FormProcessor($contacts, $submissions);

        $processor->process($this->validPayload(['email' => '  John@Example.COM ']));
    }

    #[Test]
    public function it_falls_back_to_the_clock_for_a_missing_timestamp(): void
    {
        $contacts = $this->createMock(ContactRepositoryInterface::class);
        $submissions = $this->createMock(ContactFormSubmissionRepositoryInterface::class);

        $contacts->method('getContactByEmail')->willReturn(null);
        $contacts->method('create')->willReturn(99);

        $submissions->expects(self::once())
            ->method('create')
            ->with(self::callback(
                static fn (array $data): bool => $data['timestamp'] === '2026-03-01 09:00:00',
            ))
            ->willReturn(1);

        $processor = new FormProcessor(
            $contacts,
            $submissions,
            FixedClock::at('2026-03-01 09:00:00'),
        );

        $data = $this->validPayload();
        unset($data['timestamp']);

        $processor->process($data);
    }

    #[Test]
    public function it_throws_and_persists_nothing_when_the_data_is_invalid(): void
    {
        $contacts = $this->createMock(ContactRepositoryInterface::class);
        $submissions = $this->createMock(ContactFormSubmissionRepositoryInterface::class);

        $contacts->expects(self::never())->method('getContactByEmail');
        $contacts->expects(self::never())->method('create');
        $contacts->expects(self::never())->method('update');
        $submissions->expects(self::never())->method('create');

        $processor = new FormProcessor($contacts, $submissions);

        $this->expectException(ValidationException::class);

        $processor->process(['email' => 'not-an-email']);
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $override = []): array
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
}

<?php

declare(strict_types=1);

namespace Reflexive\ContactForm\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Reflexive\ContactForm\Exception\ValidationException;
use Reflexive\ContactForm\FormProcessor;
use Reflexive\ContactForm\Tests\Support\FixedClock;
use Reflexive\ContactForm\Tests\Support\InMemoryContactFormSubmissionRepository;
use Reflexive\ContactForm\Tests\Support\InMemoryContactRepository;

final class FormProcessorIntegrationTest extends TestCase
{
    private InMemoryContactRepository $contacts;
    private InMemoryContactFormSubmissionRepository $submissions;
    private FormProcessor $processor;

    protected function setUp(): void
    {
        $this->contacts = new InMemoryContactRepository();
        $this->submissions = new InMemoryContactFormSubmissionRepository();
        $this->processor = new FormProcessor(
            $this->contacts,
            $this->submissions,
            FixedClock::at('2026-06-16 12:00:00'),
        );
    }

    #[Test]
    public function it_stores_a_contact_and_its_submission(): void
    {
        $this->processor->process($this->validPayload());

        $storedContacts = $this->contacts->all();
        self::assertCount(1, $storedContacts);
        self::assertSame([
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ], $storedContacts[0]);

        $storedSubmissions = $this->submissions->all();
        self::assertCount(1, $storedSubmissions);
        self::assertSame([
            'id' => 1,
            'contact_id' => 1,
            'field' => 'Marketing',
            'service' => 'SEO',
            'message' => 'Hello there',
            'timestamp' => '2026-05-01 08:30:00',
        ], $storedSubmissions[0]);
    }

    #[Test]
    public function it_reuses_the_same_contact_for_multiple_submissions(): void
    {
        $this->processor->process($this->validPayload([
            'field' => 'Marketing',
            'service' => 'SEO',
            'message' => 'First enquiry',
        ]));

        $this->processor->process($this->validPayload([
            'field' => 'PR',
            'service' => 'Media relations',
            'message' => 'Second enquiry',
        ]));

        self::assertCount(1, $this->contacts->all());

        $linked = $this->submissions->getSubmissionsByContact(1);
        self::assertCount(2, $linked);
        self::assertSame('First enquiry', $linked[0]['message']);
        self::assertSame('Second enquiry', $linked[1]['message']);
    }

    #[Test]
    public function it_updates_the_contact_name_when_the_same_email_returns(): void
    {
        $this->processor->process($this->validPayload([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]));

        $this->processor->process($this->validPayload([
            'first_name' => 'Jonathan',
            'last_name' => 'Doe-Smith',
        ]));

        $contacts = $this->contacts->all();
        self::assertCount(1, $contacts);
        self::assertSame('Jonathan', $contacts[0]['first_name']);
        self::assertSame('Doe-Smith', $contacts[0]['last_name']);
        self::assertSame('john@example.com', $contacts[0]['email']);
    }

    #[Test]
    public function it_treats_differently_cased_emails_as_the_same_contact(): void
    {
        $this->processor->process($this->validPayload(['email' => 'john@example.com']));
        $this->processor->process($this->validPayload(['email' => 'JOHN@EXAMPLE.COM']));

        self::assertCount(1, $this->contacts->all());
        self::assertCount(2, $this->submissions->all());
    }

    #[Test]
    public function it_applies_the_processing_time_when_no_timestamp_is_supplied(): void
    {
        $data = $this->validPayload();
        unset($data['timestamp']);

        $this->processor->process($data);

        $submission = $this->submissions->all()[0];
        self::assertSame('2026-06-16 12:00:00', $submission['timestamp']);
    }

    #[Test]
    public function it_does_not_store_anything_when_validation_fails(): void
    {
        try {
            $this->processor->process(['first_name' => 'John']);
            self::fail('Expected a ValidationException.');
        } catch (ValidationException) {
        }

        self::assertCount(0, $this->contacts->all());
        self::assertCount(0, $this->submissions->all());
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

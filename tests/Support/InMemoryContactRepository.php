<?php

declare(strict_types=1);

namespace Reflexive\ContactForm\Tests\Support;

use Reflexive\ContactForm\Contract\ContactRepositoryInterface;

final class InMemoryContactRepository implements ContactRepositoryInterface
{
    /** @var array<int, array{id: int, first_name: string, last_name: string, email: string}> */
    private array $contacts = [];

    private int $nextId = 1;

    public function get(int $id): ?array
    {
        return $this->contacts[$id] ?? null;
    }

    public function create(array $data): int
    {
        $id = $this->nextId++;

        $this->contacts[$id] = [
            'id' => $id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
        ];

        return $id;
    }

    public function update(int $id, array $data): array
    {
        if (!isset($this->contacts[$id])) {
            throw new \RuntimeException(sprintf('Contact #%d does not exist.', $id));
        }

        $this->contacts[$id] = array_merge($this->contacts[$id], $data);

        return $this->contacts[$id];
    }

    public function delete(int $id): void
    {
        unset($this->contacts[$id]);
    }

    public function getContactByEmail(string $email): ?array
    {
        foreach ($this->contacts as $contact) {
            if ($contact['email'] === $email) {
                return $contact;
            }
        }

        return null;
    }

    /**
     * @return list<array{id: int, first_name: string, last_name: string, email: string}>
     */
    public function all(): array
    {
        return array_values($this->contacts);
    }
}

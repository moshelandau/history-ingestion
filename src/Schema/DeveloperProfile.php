<?php

declare(strict_types=1);

namespace HistoryIngestion\Schema;

/**
 * Basic developer metadata extracted from configuration.
 */
final readonly class DeveloperProfile
{
    public function __construct(
        public string $name,
        public string $github,
        public ?string $email,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'github' => $this->github,
            'email' => $this->email,
        ];
    }
}

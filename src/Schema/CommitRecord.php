<?php

declare(strict_types=1);

namespace HistoryIngestion\Schema;

/**
 * A single normalized commit from a project's git history.
 */
final readonly class CommitRecord
{
    public function __construct(
        public string $hash,
        public string $shortHash,
        public string $message,
        public string $author,
        public string $authorEmail,
        public \DateTimeImmutable $date,
        public int $filesChanged,
        public int $insertions,
        public int $deletions,
    ) {}

    public function toArray(): array
    {
        return [
            'hash' => $this->hash,
            'short_hash' => $this->shortHash,
            'message' => $this->message,
            'author' => $this->author,
            'author_email' => $this->authorEmail,
            'date' => $this->date->format(\DateTimeInterface::ATOM),
            'files_changed' => $this->filesChanged,
            'insertions' => $this->insertions,
            'deletions' => $this->deletions,
        ];
    }
}

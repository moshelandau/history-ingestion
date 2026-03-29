<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use HistoryIngestion\Schema\CommitRecord;
use PHPUnit\Framework\TestCase;

final class CommitRecordTest extends TestCase
{
    public function test_to_array_returns_expected_shape(): void
    {
        $date = new \DateTimeImmutable('2024-01-15T10:30:00+00:00');
        $commit = new CommitRecord(
            hash: 'abc123def456abc123def456abc123def456abc1',
            shortHash: 'abc123d',
            message: 'feat: add user authentication',
            author: 'Moshe Landau',
            authorEmail: 'moshe@example.com',
            date: $date,
            filesChanged: 5,
            insertions: 120,
            deletions: 10,
        );

        $array = $commit->toArray();

        $this->assertSame('abc123def456abc123def456abc123def456abc1', $array['hash']);
        $this->assertSame('abc123d', $array['short_hash']);
        $this->assertSame('feat: add user authentication', $array['message']);
        $this->assertSame('Moshe Landau', $array['author']);
        $this->assertSame('moshe@example.com', $array['author_email']);
        $this->assertSame(5, $array['files_changed']);
        $this->assertSame(120, $array['insertions']);
        $this->assertSame(10, $array['deletions']);
        $this->assertStringContainsString('2024-01-15', $array['date']);
    }
}

<?php

declare(strict_types=1);

namespace QueryGuard\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryGuard\Recorder\QuerySignature;

final class QuerySignatureTest extends TestCase
{
    public function test_strips_numeric_literals(): void
    {
        self::assertSame(
            'select * from users where id = ?',
            QuerySignature::normalize('SELECT * FROM users WHERE id = 42'),
        );
    }

    public function test_strips_string_literals_with_escaped_quotes(): void
    {
        self::assertSame(
            'select * from users where name = ?',
            QuerySignature::normalize("SELECT * FROM users WHERE name = 'O''Brien'"),
        );
    }

    public function test_collapses_in_lists(): void
    {
        self::assertSame(
            'select * from posts where id in (?)',
            QuerySignature::normalize('SELECT * FROM posts WHERE id IN (1, 2, 3, 4)'),
        );
    }

    public function test_collapses_multi_row_values(): void
    {
        self::assertSame(
            'insert into logs (a, b) values (?)',
            QuerySignature::normalize("INSERT INTO logs (a, b) VALUES (1, 'x'), (2, 'y'), (3, 'z')"),
        );
    }

    public function test_normalizes_whitespace(): void
    {
        self::assertSame(
            'select id from users where active = ?',
            QuerySignature::normalize("SELECT  id\n  FROM   users\nWHERE\tactive = 1"),
        );
    }

    public function test_lowercases_keywords_only(): void
    {
        // Identifiers (table/column names) should keep their original case.
        self::assertSame(
            'select Id, Name from Users where Status = ?',
            QuerySignature::normalize('SELECT Id, Name FROM Users WHERE Status = 1'),
        );
    }
}

<?php

declare(strict_types=1);

namespace QueryGuard\Recorder;

final class QuerySignature
{
    public static function normalize(string $sql): string
    {
        $s = trim($sql);

        // Collapse whitespace.
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        // Strip single-quoted strings (handle escaped quotes).
        $s = preg_replace("/'(?:''|\\\\.|[^'\\\\])*'/", '?', $s) ?? $s;

        // Drop identifier-quoting characters so table/column names stay visible
        // in the signature. SQLite/Postgres use double quotes and MySQL uses
        // backticks to quote identifiers (not string values — Laravel binds
        // values as `?`).
        $s = str_replace(['"', '`'], '', $s);

        // Numeric literals.
        $s = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $s) ?? $s;

        // Collapse IN (?, ?, ?) → IN (?).
        $s = preg_replace('/\bIN\s*\(\s*\?(?:\s*,\s*\?)*\s*\)/i', 'IN (?)', $s) ?? $s;

        // Collapse VALUES (?, ?), (?, ?) → VALUES (?).
        $s = preg_replace('/\bVALUES\s*\(\s*\?(?:\s*,\s*\?)*\s*\)(?:\s*,\s*\(\s*\?(?:\s*,\s*\?)*\s*\))*/i', 'VALUES (?)', $s) ?? $s;

        // Lowercase SQL keywords for stable signatures (leave identifiers as-is — they may be quoted).
        $s = preg_replace_callback(
            '/\b(SELECT|FROM|WHERE|AND|OR|NOT|IN|IS|NULL|JOIN|LEFT|RIGHT|INNER|OUTER|ON|GROUP|BY|ORDER|LIMIT|OFFSET|INSERT|INTO|VALUES|UPDATE|SET|DELETE|RETURNING|HAVING|AS|DISTINCT|UNION|ALL|EXISTS|BETWEEN|LIKE|ASC|DESC|CASE|WHEN|THEN|ELSE|END)\b/i',
            static fn (array $m): string => strtolower($m[1]),
            $s
        ) ?? $s;

        return trim($s);
    }
}

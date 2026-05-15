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

        // Strip double-quoted strings (rare in MySQL/Postgres values, but possible).
        $s = preg_replace('/"(?:""|\\\\.|[^"\\\\])*"/', '?', $s) ?? $s;

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

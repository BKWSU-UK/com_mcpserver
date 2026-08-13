<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

final class TextDiff
{
    public const MAX_LINES = 5000;
    public const MAX_MATRIX_CELLS = 1_000_000;

    /**
     * Unified line diff. Returns '' when the texts are equal, or null when
     * either side exceeds MAX_LINES or the LCS matrix would exceed MAX_MATRIX_CELLS.
     */
    public static function unifiedDiff(string $old, string $new, int $contextLines = 3): ?string
    {
        $old = str_replace(["\r\n", "\r"], "\n", $old);
        $new = str_replace(["\r\n", "\r"], "\n", $new);

        if ($old === $new) {
            return '';
        }

        $oldLines = self::splitLines($old);
        $newLines = self::splitLines($new);

        if (count($oldLines) > self::MAX_LINES || count($newLines) > self::MAX_LINES) {
            return null;
        }

        $oldCount = count($oldLines);
        $newCount = count($newLines);
        $prefixLen = 0;
        $limit = min($oldCount, $newCount);
        while ($prefixLen < $limit && $oldLines[$prefixLen] === $newLines[$prefixLen]) {
            $prefixLen++;
        }

        $suffixLen = 0;
        while (
            $suffixLen < ($oldCount - $prefixLen)
            && $suffixLen < ($newCount - $prefixLen)
            && $oldLines[$oldCount - 1 - $suffixLen] === $newLines[$newCount - 1 - $suffixLen]
        ) {
            $suffixLen++;
        }

        $oldMiddle = array_slice($oldLines, $prefixLen, $oldCount - $prefixLen - $suffixLen);
        $newMiddle = array_slice($newLines, $prefixLen, $newCount - $prefixLen - $suffixLen);
        $m = count($oldMiddle);
        $n = count($newMiddle);

        if ($m * $n > self::MAX_MATRIX_CELLS) {
            return null;
        }

        $ops = [];
        for ($i = 0; $i < $prefixLen; $i++) {
            $ops[] = ['type' => 'equal', 'text' => $oldLines[$i]];
        }
        foreach (self::editScript($oldMiddle, $newMiddle) as $op) {
            $ops[] = $op;
        }
        for ($i = $oldCount - $suffixLen; $i < $oldCount; $i++) {
            $ops[] = ['type' => 'equal', 'text' => $oldLines[$i]];
        }

        return self::emitHunks($ops, $contextLines);
    }

    /**
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     * @param  list<string>  $fields
     * @param  list<string>  $textFields
     * @return list<array<string, mixed>>
     */
    public static function diffFields(
        array $from,
        array $to,
        array $fields,
        array $textFields = ['introtext', 'fulltext']
    ): array {
        $textFieldSet = array_fill_keys($textFields, true);
        $changes = [];

        foreach ($fields as $field) {
            $fromHas = array_key_exists($field, $from);
            $toHas = array_key_exists($field, $to);
            if (!$fromHas && !$toHas) {
                continue;
            }

            $fromVal = $fromHas ? $from[$field] : null;
            $toVal = $toHas ? $to[$field] : null;

            if (isset($textFieldSet[$field])) {
                $old = (string) ($fromVal ?? '');
                $new = (string) ($toVal ?? '');
                if ($old === $new) {
                    continue;
                }

                $diff = self::unifiedDiff($old, $new);
                $entry = [
                    'field' => $field,
                    'type' => 'text',
                    'diff' => $diff,
                    'old_length' => strlen($old),
                    'new_length' => strlen($new),
                ];
                if ($diff === null) {
                    $entry['old'] = $old;
                    $entry['new'] = $new;
                }
                $changes[] = $entry;
                continue;
            }

            if (is_array($fromVal) || is_array($toVal)) {
                if (json_encode($fromVal) === json_encode($toVal)) {
                    continue;
                }
            } elseif ((string) $fromVal === (string) $toVal) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'type' => 'scalar',
                'old' => $fromVal,
                'new' => $toVal,
            ];
        }

        return $changes;
    }

    /**
     * @return list<string>
     */
    private static function splitLines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return explode("\n", $text);
    }

    /**
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return list<array{type: 'equal'|'delete'|'insert', text: string}>
     */
    private static function editScript(array $old, array $new): array
    {
        $m = count($old);
        $n = count($new);
        $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = 0; $i < $m; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($old[$i] === $new[$j]) {
                    $dp[$i + 1][$j + 1] = $dp[$i][$j] + 1;
                } else {
                    $dp[$i + 1][$j + 1] = max($dp[$i + 1][$j], $dp[$i][$j + 1]);
                }
            }
        }

        $ops = [];
        $i = $m;
        $j = $n;
        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $old[$i - 1] === $new[$j - 1]) {
                $ops[] = ['type' => 'equal', 'text' => $old[$i - 1]];
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $dp[$i][$j - 1] >= $dp[$i - 1][$j])) {
                $ops[] = ['type' => 'insert', 'text' => $new[$j - 1]];
                $j--;
            } else {
                $ops[] = ['type' => 'delete', 'text' => $old[$i - 1]];
                $i--;
            }
        }

        return array_reverse($ops);
    }

    /**
     * @param  list<array{type: 'equal'|'delete'|'insert', text: string}>  $ops
     */
    private static function emitHunks(array $ops, int $contextLines): string
    {
        $changeIndexes = [];
        foreach ($ops as $idx => $op) {
            if ($op['type'] !== 'equal') {
                $changeIndexes[] = $idx;
            }
        }

        if ($changeIndexes === []) {
            return '';
        }

        $opCount = count($ops);
        $hunks = [];
        $hunkStart = max(0, $changeIndexes[0] - $contextLines);
        $hunkEnd = min($opCount - 1, $changeIndexes[0] + $contextLines);

        for ($k = 1, $n = count($changeIndexes); $k < $n; $k++) {
            $idx = $changeIndexes[$k];
            $nextStart = max(0, $idx - $contextLines);
            if ($nextStart <= $hunkEnd + 1) {
                $hunkEnd = min($opCount - 1, $idx + $contextLines);
            } else {
                $hunks[] = [$hunkStart, $hunkEnd];
                $hunkStart = $nextStart;
                $hunkEnd = min($opCount - 1, $idx + $contextLines);
            }
        }
        $hunks[] = [$hunkStart, $hunkEnd];

        $out = [];
        foreach ($hunks as [$start, $end]) {
            $oldStart = 0;
            $newStart = 0;
            $oldCount = 0;
            $newCount = 0;

            for ($i = 0; $i < $start; $i++) {
                if ($ops[$i]['type'] !== 'insert') {
                    $oldStart++;
                }
                if ($ops[$i]['type'] !== 'delete') {
                    $newStart++;
                }
            }
            for ($i = $start; $i <= $end; $i++) {
                if ($ops[$i]['type'] !== 'insert') {
                    $oldCount++;
                }
                if ($ops[$i]['type'] !== 'delete') {
                    $newCount++;
                }
            }

            $oldHeader = $oldCount === 0 ? $oldStart : $oldStart + 1;
            $newHeader = $newCount === 0 ? $newStart : $newStart + 1;
            $out[] = sprintf('@@ -%d,%d +%d,%d @@', $oldHeader, $oldCount, $newHeader, $newCount);

            for ($i = $start; $i <= $end; $i++) {
                $marker = match ($ops[$i]['type']) {
                    'equal' => ' ',
                    'delete' => '-',
                    'insert' => '+',
                };
                $out[] = $marker . $ops[$i]['text'];
            }
        }

        return implode("\n", $out);
    }
}

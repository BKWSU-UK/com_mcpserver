<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Tests\Unit;

defined('_JEXEC') or die;

use Joomla\Component\Mcpserver\Administrator\Service\TextDiff;
use PHPUnit\Framework\TestCase;

class TextDiffTest extends TestCase
{
    public function testIdenticalTextsReturnEmptyString(): void
    {
        $this->assertSame('', TextDiff::unifiedDiff("a\nb\n", "a\nb\n"));
    }

    public function testSingleChangeProducesOneHunk(): void
    {
        $diff = TextDiff::unifiedDiff("line1\nline2\nline3", "line1\nCHANGED\nline3");

        $this->assertNotNull($diff);
        $this->assertSame(1, preg_match_all('/^@@ /m', $diff));
        $this->assertStringContainsString('-line2', $diff);
        $this->assertStringContainsString('+CHANGED', $diff);
        $this->assertSame(
            "@@ -1,3 +1,3 @@\n line1\n-line2\n+CHANGED\n line3",
            $diff
        );
    }

    public function testInsertOnly(): void
    {
        $diff = TextDiff::unifiedDiff("a\nb\nc", "a\nINSERTED\nb\nc");

        $this->assertNotNull($diff);
        $this->assertSame(1, preg_match_all('/^@@ /m', $diff));
        $this->assertStringContainsString('+INSERTED', $diff);
        $this->assertStringNotContainsString('-', preg_replace('/^@@ .+$/m', '', $diff) ?? '');
    }

    public function testDeleteOnly(): void
    {
        $diff = TextDiff::unifiedDiff("a\nGONE\nb\nc", "a\nb\nc");

        $this->assertNotNull($diff);
        $this->assertSame(1, preg_match_all('/^@@ /m', $diff));
        $this->assertStringContainsString('-GONE', $diff);
    }

    public function testTwoSeparatedEditsProduceTwoHunks(): void
    {
        $oldLines = [];
        $newLines = [];
        for ($i = 1; $i <= 12; $i++) {
            $oldLines[] = 'line' . $i;
            $newLines[] = $i === 2 || $i === 11 ? 'changed' . $i : 'line' . $i;
        }

        $diff = TextDiff::unifiedDiff(implode("\n", $oldLines), implode("\n", $newLines));

        $this->assertNotNull($diff);
        $this->assertSame(2, preg_match_all('/^@@ /m', $diff));
        $this->assertStringContainsString('-line2', $diff);
        $this->assertStringContainsString('+changed2', $diff);
        $this->assertStringContainsString('-line11', $diff);
        $this->assertStringContainsString('+changed11', $diff);
    }

    public function testCrlfIsNormalisedBeforeDiff(): void
    {
        $this->assertSame('', TextDiff::unifiedDiff("a\r\nb\r\nc", "a\nb\nc"));
        $diff = TextDiff::unifiedDiff("a\r\nb\r\nc", "a\nCHANGED\nc");
        $this->assertNotNull($diff);
        $this->assertStringContainsString('-b', $diff);
        $this->assertStringContainsString('+CHANGED', $diff);
    }

    public function testEmptyOldIsInsertOnlyHunk(): void
    {
        $diff = TextDiff::unifiedDiff('', "hello\nworld");

        $this->assertSame("@@ -0,0 +1,2 @@\n+hello\n+world", $diff);
    }

    public function testOverLineCapReturnsNull(): void
    {
        $old = implode("\n", array_fill(0, TextDiff::MAX_LINES + 1, 'x'));
        $new = implode("\n", array_fill(0, TextDiff::MAX_LINES + 1, 'y'));

        $this->assertNull(TextDiff::unifiedDiff($old, $new));
    }

    public function testOverMatrixCapReturnsNull(): void
    {
        $n = 1001;
        $old = implode("\n", array_map(static fn (int $i): string => 'old' . $i, range(1, $n)));
        $new = implode("\n", array_map(static fn (int $i): string => 'new' . $i, range(1, $n)));

        $this->assertGreaterThan(TextDiff::MAX_MATRIX_CELLS, $n * $n);
        $this->assertNull(TextDiff::unifiedDiff($old, $new));
    }

    public function testDiffFieldsScalarChange(): void
    {
        $changes = TextDiff::diffFields(
            ['title' => 'Old', 'state' => 1],
            ['title' => 'New', 'state' => 1],
            ['title', 'state']
        );

        $this->assertSame([
            [
                'field' => 'title',
                'type' => 'scalar',
                'old' => 'Old',
                'new' => 'New',
            ],
        ], $changes);
    }

    public function testDiffFieldsTextChangeIncludesUnifiedDiff(): void
    {
        $changes = TextDiff::diffFields(
            ['introtext' => "a\nb"],
            ['introtext' => "a\nB"],
            ['introtext']
        );

        $this->assertCount(1, $changes);
        $this->assertSame('introtext', $changes[0]['field']);
        $this->assertSame('text', $changes[0]['type']);
        $this->assertIsString($changes[0]['diff']);
        $this->assertStringContainsString('-b', $changes[0]['diff']);
        $this->assertStringContainsString('+B', $changes[0]['diff']);
        $this->assertSame(3, $changes[0]['old_length']);
        $this->assertSame(3, $changes[0]['new_length']);
        $this->assertArrayNotHasKey('old', $changes[0]);
    }

    public function testDiffFieldsComparesArraysViaJsonEncode(): void
    {
        $changes = TextDiff::diffFields(
            ['images' => ['image_intro' => 'a.jpg']],
            ['images' => ['image_intro' => 'b.jpg']],
            ['images']
        );

        $this->assertSame('scalar', $changes[0]['type']);
        $this->assertSame(['image_intro' => 'a.jpg'], $changes[0]['old']);
        $this->assertSame(['image_intro' => 'b.jpg'], $changes[0]['new']);
    }

    public function testDiffFieldsIdenticalArraysAreSkipped(): void
    {
        $images = ['image_intro' => 'a.jpg'];
        $this->assertSame([], TextDiff::diffFields(
            ['images' => $images],
            ['images' => $images],
            ['images']
        ));
    }

    public function testDiffFieldsOneSideAbsentIsAChange(): void
    {
        $changes = TextDiff::diffFields(
            ['title' => 'Kept'],
            [],
            ['title']
        );

        $this->assertSame([
            [
                'field' => 'title',
                'type' => 'scalar',
                'old' => 'Kept',
                'new' => null,
            ],
        ], $changes);
    }

    public function testDiffFieldsSkipsAbsentOnBothSides(): void
    {
        $this->assertSame([], TextDiff::diffFields(
            ['title' => 'X'],
            ['title' => 'X'],
            ['metadesc']
        ));
    }

    public function testDiffFieldsOverCapIncludesFullOldAndNew(): void
    {
        $old = implode("\n", array_fill(0, TextDiff::MAX_LINES + 1, 'x'));
        $new = implode("\n", array_fill(0, TextDiff::MAX_LINES + 1, 'y'));

        $changes = TextDiff::diffFields(
            ['introtext' => $old],
            ['introtext' => $new],
            ['introtext']
        );

        $this->assertCount(1, $changes);
        $this->assertNull($changes[0]['diff']);
        $this->assertSame($old, $changes[0]['old']);
        $this->assertSame($new, $changes[0]['new']);
    }
}

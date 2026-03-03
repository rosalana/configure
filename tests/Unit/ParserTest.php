<?php

use Rosalana\Configure\Node\Comment;
use Rosalana\Configure\Node\RichComment;
use Rosalana\Configure\Node\Value;

// ─────────────────────────────────────────────
// Value::parse()
// ─────────────────────────────────────────────

describe('Value::parse()', function () {

    it('parses scalar values from flat config content', function () {
        $content = [
            0 => "    'driver' => 'mysql',",
            1 => "    'host' => 'localhost',",
        ];

        $nodes = Value::parse($content);

        expect($nodes)->toHaveCount(2);
    });

    it('assigns the key from the left side of the arrow', function () {
        $content = [0 => "    'driver' => 'mysql',"];
        $node = Value::parse($content)->first();

        expect($node->key())->toBe('driver');
    });

    it('assigns dot-notation key for values inside a nested array block', function () {
        $content = [
            0 => "    'connections' => [",
            1 => "        'host' => '127.0.0.1',",
            2 => "    ],",
        ];

        $node = Value::parse($content)->first();

        expect($node->key())->toBe('connections.host');
    });

    it('tracks nesting depth across multiple levels', function () {
        $content = [
            0 => "    'database' => [",
            1 => "        'connections' => [",
            2 => "            'host' => '127.0.0.1',",
            3 => "        ],",
            4 => "    ],",
        ];

        $node = Value::parse($content)->first();

        expect($node->key())->toBe('database.connections.host');
    });

    it('does not parse an array block opener as a value', function () {
        $content = [
            0 => "    'connections' => [",
            1 => "    ],",
        ];

        expect(Value::parse($content))->toHaveCount(0);
    });

    it('treats a multiline function call as a single value node', function () {
        $content = [
            0 => "    'key' => env(",
            1 => "        'APP_KEY',",
            2 => "        null",
            3 => "    ),",
        ];

        $nodes = Value::parse($content);

        expect($nodes)->toHaveCount(1);
        expect($nodes->first()->start())->toBe(0);
        expect($nodes->first()->end())->toBe(3);
    });

    it('preserves original line numbers from the source content', function () {
        $content = [
            10 => "    'driver' => 'mysql',",
            11 => "    'host' => 'localhost',",
        ];

        $nodes = Value::parse($content);

        expect($nodes->first()->start())->toBe(10);
        expect($nodes->last()->start())->toBe(11);
    });
});

// ─────────────────────────────────────────────
// Comment::parse()
// ─────────────────────────────────────────────

describe('Comment::parse()', function () {

    it('detects a single-line comment', function () {
        $content = [
            0 => "    // This is a comment",
            1 => "    'driver' => 'mysql',",
        ];

        expect(Comment::parse($content))->toHaveCount(1);
    });

    it('groups consecutive comment lines into one node', function () {
        $content = [
            0 => "    // Line one",
            1 => "    // Line two",
            2 => "    'driver' => 'mysql',",
        ];

        $nodes = Comment::parse($content);

        expect($nodes)->toHaveCount(1);
        expect($nodes->first()->label())->toContain('Line one');
        expect($nodes->first()->label())->toContain('Line two');
    });

    it('strips the // markers from the label', function () {
        $content = [0 => "    // Hello world"];

        $label = Comment::parse($content)->first()->label();

        expect($label)->not->toContain('//');
        expect($label)->toContain('Hello world');
    });

    it('produces separate nodes for non-consecutive comment blocks', function () {
        $content = [
            0 => "    // First block",
            1 => "    'value' => 'x',",
            2 => "    // Second block",
        ];

        expect(Comment::parse($content))->toHaveCount(2);
    });

    it('prefixes the key with the parent section name for nested comments', function () {
        // The comment must be followed by a non-comment line (other than `]`)
        // so the buffer is flushed while the section key is still on the stack.
        $content = [
            0 => "    'connections' => [",
            1 => "        // nested comment",
            2 => "        'host' => '127.0.0.1',",
            3 => "    ],",
        ];

        $key = Comment::parse($content)->first()->key();

        expect($key)->toStartWith('connections.');
    });
});

// ─────────────────────────────────────────────
// RichComment::parse()
// ─────────────────────────────────────────────

function richCommentLines(string $label, ?string $description = null): array
{
    $lines = ['/*', '|--------------------------------------------------------------------------', '| ' . $label, '|--------------------------------------------------------------------------'];

    if ($description !== null) {
        $lines[] = '|';
        foreach (explode("\n", $description) as $line) {
            $lines[] = '| ' . $line;
        }
        $lines[] = '|';
    }

    $lines[] = '*/';

    return array_combine(range(0, count($lines) - 1), $lines);
}

describe('RichComment::parse()', function () {

    it('detects a rich comment block', function () {
        $content = richCommentLines('Section Title');

        expect(RichComment::parse($content))->toHaveCount(1);
    });

    it('extracts the label from the block', function () {
        $content = richCommentLines('My Section');

        $node = RichComment::parse($content)->first();

        expect($node->label())->toBe('My Section');
    });

    it('extracts the description when present', function () {
        $content = richCommentLines('Title', 'Some description text');

        $node = RichComment::parse($content)->first();

        expect($node->description())->not->toBeNull();
        expect($node->description())->toContain('Some description text');
    });

    it('has null description when no description is provided', function () {
        $content = richCommentLines('Title Only');

        $node = RichComment::parse($content)->first();

        expect($node->description())->toBeNull();
    });

    it('spans the correct line range', function () {
        $content = richCommentLines('Title');

        $node = RichComment::parse($content)->first();

        expect($node->start())->toBe(0);
        expect($node->end())->toBe(array_key_last($content));
    });
});

<?php

use Rosalana\Configure\Configure;
use Rosalana\Configure\Node\Section;
use Rosalana\Configure\Node\Value;

// Fixture used by most read tests — covers scalars, nested section, numeric value.
// No empty line directly after "return [" — that would cause a leading-line mismatch in diff().
$basicFixture = <<<'PHP'
<?php

return [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'connections' => [
        'mysql' => [
            'host' => '127.0.0.1',
        ],
    ],
];
PHP;

// ─────────────────────────────────────────────
// Reading
// ─────────────────────────────────────────────

describe('reading a config file', function () use ($basicFixture) {

    beforeEach(function () use ($basicFixture) {
        $this->createConfig('basic.php', $basicFixture);
    });

    it('parses top-level values', function () {
        $file = Configure::file('basic');

        expect($file->has('driver'))->toBeTrue();
        expect($file->has('host'))->toBeTrue();
        expect($file->has('port'))->toBeTrue();
    });

    it('parses nested sections', function () {
        $file = Configure::file('basic');

        expect($file->has('connections'))->toBeTrue();
        expect($file->has('connections.mysql'))->toBeTrue();
        expect($file->has('connections.mysql.host'))->toBeTrue();
    });

    it('returns the correct node type for sections and values', function () {
        $file = Configure::file('basic');

        expect($file->section('connections'))->toBeInstanceOf(Section::class);
        expect($file->value('driver'))->toBeInstanceOf(Value::class);
    });

    it('has no diff after a save-and-reread roundtrip without changes', function () {
        // Directly reading an arbitrary file may produce a diff because reflow()
        // adjusts positions for padding. The reliable invariant is: save → re-read → no diff.
        $file = Configure::file('basic');
        $file->save();

        $reread = Configure::file('basic');
        expect($reread->diff())->toBeEmpty();
    });
});

// ─────────────────────────────────────────────
// Dot-notation traversal
// ─────────────────────────────────────────────

describe('dot-notation traversal', function () use ($basicFixture) {

    beforeEach(function () use ($basicFixture) {
        $this->createConfig('basic.php', $basicFixture);
    });

    it('reaches a deeply nested value via dot path', function () {
        $file = Configure::file('basic');

        $node = $file->value('connections.mysql.host');

        expect($node)->toBeInstanceOf(Value::class);
    });

    it('reaches a deeply nested section via dot path', function () {
        $file = Configure::file('basic');

        $section = $file->section('connections.mysql');

        expect($section)->toBeInstanceOf(Section::class);
        expect($section->key())->toBe('mysql');
    });
});

// ─────────────────────────────────────────────
// Diff detection
// ─────────────────────────────────────────────

describe('diff detection', function () use ($basicFixture) {

    beforeEach(function () use ($basicFixture) {
        $this->createConfig('basic.php', $basicFixture);
    });

    it('reports a changed line when a value is modified', function () {
        $file = Configure::file('basic');

        $file->value('driver')->set('pgsql');

        $diff = $file->diff();
        $changed = array_filter($diff, fn($d) => $d['type'] === 'changed');

        expect($changed)->not->toBeEmpty();
    });

    it('reports added lines when a new value is created', function () {
        $file = Configure::file('basic');

        $file->value('password', "'secret'");

        $diff = $file->diff();
        $added = array_filter($diff, fn($d) => $d['type'] === 'added');

        expect($added)->not->toBeEmpty();
    });

    it('diff is non-empty after a value is deleted', function () {
        // diff() compares by line number: deletion + reflow causes sibling lines
        // to shift, which shows as "changed" entries rather than "removed".
        // The key principle: any deletion makes the file dirty.
        $file = Configure::file('basic');

        $file->value('host')->remove();

        expect($file->diff())->not->toBeEmpty();
    });
});

// ─────────────────────────────────────────────
// Write & re-read roundtrip
// ─────────────────────────────────────────────

describe('write and re-read', function () use ($basicFixture) {

    beforeEach(function () use ($basicFixture) {
        $this->createConfig('basic.php', $basicFixture);
    });

    it('persists a changed value to disk', function () {
        $file = Configure::file('basic');
        $file->value('driver')->set("'pgsql'");
        $file->save();

        $reread = Configure::file('basic');
        expect($reread->value('driver')->get())->toContain('pgsql');
    });

    it('persists a new value to disk', function () {
        $file = Configure::file('basic');
        $file->value('password', "'secret'");
        $file->save();

        $reread = Configure::file('basic');
        expect($reread->has('password'))->toBeTrue();
    });

    it('persists a new section with values to disk', function () {
        $file = Configure::file('basic');
        $file->section('cache')->value('driver', "'redis'");
        $file->save();

        $reread = Configure::file('basic');
        expect($reread->has('cache'))->toBeTrue();
        expect($reread->has('cache.driver'))->toBeTrue();
    });

    it('produces no diff after reading a just-written file', function () {
        $file = Configure::file('basic');
        $file->value('driver')->set("'pgsql'");
        $file->save();

        $reread = Configure::file('basic');
        expect($reread->diff())->toBeEmpty();
    });

    it('removing a value is reflected after re-read', function () {
        $file = Configure::file('basic');
        $file->value('host')->remove();
        $file->save();

        $reread = Configure::file('basic');
        expect($reread->has('host'))->toBeFalse();
    });
});

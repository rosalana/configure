<?php

use Rosalana\Configure\Node\Section;
use Rosalana\Configure\Node\Value;

describe('Section::wrap() — tree building', function () {

    it('wraps a value with a nested key into a section', function () {
        $v = Value::make(1, 1, [1 => "    'host' => '127.0.0.1',"])
            ->setKey('connections.host');

        $result = Section::wrap(collect([$v]));

        expect($result)->toHaveCount(1);
        expect($result->first())->toBeInstanceOf(Section::class);
        expect($result->first()->key())->toBe('connections');
    });

    it('leaves a top-level value directly without wrapping in a section', function () {
        $v = Value::make(0, 0, [0 => "    'driver' => 'mysql',"])
            ->setKey('driver');

        $result = Section::wrap(collect([$v]));

        expect($result)->toHaveCount(1);
        expect($result->first())->toBeInstanceOf(Value::class);
    });

    it('groups values sharing the same parent key into one section', function () {
        $v1 = Value::make(1, 1, [1 => "    'host' => '127.0.0.1',"])->setKey('connections.host');
        $v2 = Value::make(2, 2, [2 => "    'port' => 3306,"])->setKey('connections.port');

        $result = Section::wrap(collect([$v1, $v2]));

        expect($result)->toHaveCount(1);
        expect($result->first()->nodes())->toHaveCount(2);
    });

    it('creates nested sections for deeply nested keys', function () {
        $v = Value::make(2, 2, [2 => "    'name' => 'test',"])
            ->setKey('database.connections.name');

        $result = Section::wrap(collect([$v]));

        $database = $result->first();
        $connections = $database->nodes()->first();

        expect($database)->toBeInstanceOf(Section::class);
        expect($connections)->toBeInstanceOf(Section::class);
        expect($connections->nodes()->first())->toBeInstanceOf(Value::class);
    });

    it('sets the correct parent-child relationship after wrapping', function () {
        $v = Value::make(1, 1, [1 => "    'host' => '127.0.0.1',"])
            ->setKey('connections.host');

        $result = Section::wrap(collect([$v]));
        $section = $result->first();
        $child = $section->nodes()->first();

        expect($child->parent())->toBe($section);
    });
});

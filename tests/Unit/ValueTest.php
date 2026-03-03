<?php

use Rosalana\Configure\Node\Value;

// Helper: create a standalone Value with a given raw string value
// ghost=true prevents calling parent->reflow() when there's no parent
function makeValue(string $rawValue): Value
{
    return Value::make(0, 0, [])->setKey('test')->set($rawValue, true);
}

describe('data type detection', function () {

    it('identifies integer and float as number', function () {
        expect(makeValue('42')->isNumber())->toBeTrue();
        expect(makeValue('3.14')->isNumber())->toBeTrue();
        expect(makeValue('0')->isNumber())->toBeTrue();
    });

    it('does not confuse a quoted number string with a number', function () {
        expect(makeValue("'42'")->isNumber())->toBeFalse();
    });

    it('identifies true and false as boolean regardless of case', function () {
        expect(makeValue('true')->isBoolean())->toBeTrue();
        expect(makeValue('false')->isBoolean())->toBeTrue();
        expect(makeValue('TRUE')->isBoolean())->toBeTrue();
        expect(makeValue('FALSE')->isBoolean())->toBeTrue();
    });

    it('identifies null as null regardless of case', function () {
        expect(makeValue('null')->isNull())->toBeTrue();
        expect(makeValue('NULL')->isNull())->toBeTrue();
    });

    it('identifies bracket-enclosed content as array', function () {
        expect(makeValue("['a', 'b']")->isArray())->toBeTrue();
        expect(makeValue('[]')->isArray())->toBeTrue();
    });

    it('identifies function call syntax as function', function () {
        expect(makeValue("env('APP_KEY')")->isFunction())->toBeTrue();
        expect(makeValue("config('app.name')")->isFunction())->toBeTrue();
    });

    it('identifies ::class references as class', function () {
        expect(makeValue('SomeClass::class')->isClass())->toBeTrue();
        expect(makeValue('App\\Models\\User::class')->isClass())->toBeTrue();
    });

    it('defaults to string for quoted values', function () {
        expect(makeValue("'hello'")->isString())->toBeTrue();
        expect(makeValue('"world"')->isString())->toBeTrue();
    });

    it('each type is mutually exclusive', function () {
        $number = makeValue('42');
        expect($number->isNumber())->toBeTrue()
            ->and($number->isString())->toBeFalse()
            ->and($number->isBoolean())->toBeFalse()
            ->and($number->isNull())->toBeFalse();
    });
});

describe('value get/set', function () {

    it('returns the value that was set', function () {
        $v = makeValue("'mysql'");
        expect($v->get())->toBe("'mysql'");
    });

    it('strips trailing comma on set because render adds it back', function () {
        $v = makeValue("'mysql',");
        expect($v->get())->toBe("'mysql'");
    });

    it('is null when no value has been set yet', function () {
        $v = Value::make(0, 0, [])->setKey('test');
        expect($v->isNull())->toBeTrue()
            ->and($v->isEmpty())->toBeTrue();
    });

    it('is not null after a value is set', function () {
        $v = Value::make(0, 0, [])->setKey('test');
        $v->set("'value'", true);
        expect($v->isNull())->toBeFalse()
            ->and($v->isNotEmpty())->toBeTrue();
    });
});

describe('node identity', function () {

    it('is dirty when freshly created via makeEmpty', function () {
        $v = Value::makeEmpty('key');
        expect($v->isDirty())->toBeTrue()
            ->and($v->isNew())->toBeTrue();
    });

    it('reports its name as the last segment of its key', function () {
        $v = Value::make(0, 0, [])->setKey('connections.mysql.host');
        expect($v->name())->toBe('host');
    });

    it('reports its type as value', function () {
        $v = Value::makeEmpty('test');
        expect($v->type())->toBe('value');
    });
});

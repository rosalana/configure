<?php

namespace Rosalana\Configure\Node;

use Illuminate\Support\Collection;
use Rosalana\Configure\Abstracts\Node;
use Rosalana\Configure\Traits\ZeroPaddingNode;

class Value extends Node
{
    use ZeroPaddingNode;

    protected string|null $value = null;

    public function __construct(int $start, int $end, array $raw)
    {
        parent::__construct($start, $end, $raw);
    }

    public static function parse(array $content): Collection
    {
        $nodes = collect();
        $stack = [];

        $arrayStartRegex = '/^\s*([\'"])(?<key>[^\'"]+)\1\s*=>\s*\[\s*$/';

        $valueRegex = '/^\s*([\'"])(?<key>[^\'"]+)\1\s*=>\s*(?<value>.+?),\s*$/';

        foreach ($content as $index => $line) {

            $trim = trim($line);

            if (preg_match($arrayStartRegex, $line, $match)) {
                $stack[] = $match['key'];
                continue;
            }

            if ($trim === '],' || $trim === ']') {
                array_pop($stack);
                continue;
            }

            if (!preg_match($valueRegex, $line, $match)) {
                continue;
            }

            $key = $match['key'];
            $value = trim($match['value']);

            if (str_starts_with($value, '[') || str_starts_with($value, 'array(')) {
                if (str_contains($value, '=>')) {
                    continue;
                }
            }

            $fullKey = $stack
                ? implode('.', $stack) . '.' . $key
                : $key;

            $nodes->push(Value::make(
                start: $index,
                end: $index,
                raw: [$index => $line]
            )->set($value, true)->setKey($fullKey));
        }

        return $nodes;
    }

    public function render(): array
    {
        if ($this->isArray() && $this->arrayCount() >= 5) {
            $result = collect();
            $result->push("'{$this->name()}' => [");

            foreach ($this->arrayValues() as $index => $v) {
                if ($index === $this->arrayCount() - 1) {
                    $result->push(str_repeat(' ', $this->parent()?->indent() ?? 4) . $v);
                } else {
                    $result->push(str_repeat(' ', $this->parent()?->indent() ?? 4) . $v . ',');
                }
            }

            $result->push('],');

            return $result->mapWithKeys(function ($line, $index) {
                return [$this->start() + $index => str_repeat(' ', $this->parent()?->indent() ?? 0) . $line];
            })->toArray();
        }

        if ($this->getValueDataType() === 'string' && !preg_match('/^([\'"]).*\1$/', $this->value)) {
            $this->value = "'" . trim($this->value, "'\"") . "'";
        }

        return [
            $this->start() => str_repeat(' ', $this->parent()?->indent() ?? 0) . "'{$this->name()}' => {$this->value},",
        ];
    }

    public function add(string $value, bool $ghost = false): static
    {
        if (!$this->value) {
            $this->value = $value;
        }

        if ($this->arrayCount() >= 5) {
            $this->end = $this->start() + $this->arrayCount() + 1;
            if (! $ghost) {
                $this->parent()->reflow();
            }
        }

        return $this;
    }

    public function set(string $value, bool $ghost = false): static
    {
        $this->value = $value;

        if ($this->arrayCount() >= 5) {
            $this->end = $this->start() + $this->arrayCount() + 1;
            if (! $ghost) {
                $this->parent()->reflow();
            }
        }

        return $this;
    }

    public function get(): ?string
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === null;
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function isNull(): bool
    {
        return $this->getValueDataType() === 'null' || $this->isEmpty();
    }

    public function isString(): bool
    {
        return $this->getValueDataType() === 'string';
    }

    public function isNumber(): bool
    {
        return $this->getValueDataType() === 'number';
    }

    public function isBoolean(): bool
    {
        return $this->getValueDataType() === 'boolean';
    }

    public function isArray(): bool
    {
        return $this->getValueDataType() === 'array';
    }

    public function isFunction(): bool
    {
        return $this->getValueDataType() === 'function';
    }

    public function isClass(): bool
    {
        return $this->getValueDataType() === 'class';
    }

    public function withComment(string $label): static
    {
        if ($this->siblingsBefore()->last() instanceof Comment) {
            $this->siblingsBefore()->last()->remove();
        }

        $comment = Comment::makeEmpty($label);
        $this->parent()->addChild($comment);

        $comment->before($this);

        return $this;
    }

    public function withoutComment(): static
    {
        if ($this->siblingsBefore()->last() instanceof Comment) {
            $this->siblingsBefore()->last()->remove();
        }

        return $this;
    }

    protected function getValueDataType(): string
    {
        if (is_numeric($this->value)) {
            return 'number';
        }

        if (in_array(strtolower($this->value), ['true', 'false'], true)) {
            return 'boolean';
        }

        if (strtolower($this->value) === 'null') {
            return 'null';
        }

        if (str_starts_with($this->value, '[') && str_ends_with($this->value, ']')) {
            return 'array';
        }

        if (preg_match('/^\w+\([^)]*\)/', $this->value)) {
            return 'function';
        }

        if (preg_match('/^[\w\\\\]+::class$/', $this->value)) {
            return 'class';
        }

        return 'string';
    }

    public function setArrayValue(array $raw): void
    {
        $result = [];

        $arrayStartRegex = '/^\s*([\'"])(?<key>[^\'"]+)\1\s*=>\s*\[\s*$/';

        foreach ($raw as $line) {
            $trim = trim($line);

            if (preg_match($arrayStartRegex, $line, $match)) {
                continue;
            }

            if ($trim === ']' || $trim === '],') {
                continue;
            }

            array_push($result, $trim);
        }

        if (count($result) >= 5) {
            $this->end = $this->start() + count($result) + 1;
        } else {
            $this->end = $this->start();
        }

        if (str_ends_with(',', array_last($result))) {
            $last = array_last($result);
            array_pop($result);
            array_push($result, rtrim($last, ','));
        }

        $this->value = '[' . implode(' ', $result) . ']';
    }

    protected function arrayCount(): int
    {
        if ($this->isArray()) {
            $value = $this->value;
            $value = ltrim($value, '[');
            $value = rtrim($value, ']');
            $value = explode(',', $value);

            return count($value);
        }

        return 0;
    }

    protected function arrayValues(): array
    {
        if ($this->isArray()) {
            $value = $this->value;
            $value = ltrim($value, '[');
            $value = rtrim($value, ']');
            $value = explode(',', $value);

            $value = array_map(fn($v) => trim($v), $value);

            return $value;
        }

        return [];
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'value' => $this->value,
        ]);
    }
}

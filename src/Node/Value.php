<?php

namespace Rosalana\Configure\Node;

use Illuminate\Support\Collection;
use Rosalana\Configure\Abstracts\Node;
use Rosalana\Configure\Traits\ZeroPaddingNode;

class Value extends Node
{
    use ZeroPaddingNode;

    protected string|null $value;

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
            )->set($value)->setKey($fullKey));
        }

        return $nodes;
    }

    public function render(): array
    {
        if ($this->getValueDataType() === 'string' && !preg_match('/^([\'"]).*\1$/', $this->value)) {
            $this->value = "'" . trim($this->value, "'\"") . "'";
        }

        return [
            $this->start() => str_repeat(' ', $this->parent()?->indent() ?? 0) . "'{$this->name()}' => {$this->value},",
        ];
    }

    public function add(string $value): self
    {
        if (!$this->value) {
            $this->value = $value;
        }

        return $this;
    }

    public function set(string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function withComment(string $label): self
    {
        if ($this->siblingsBefore()->last() instanceof Comment) {
            $this->siblingsBefore()->last()->remove();
        }

        $comment = Comment::makeEmpty($label);
        $this->parent()->addChild($comment);

        $comment->before($this);

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

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'value' => $this->value,
        ]);
    }
}

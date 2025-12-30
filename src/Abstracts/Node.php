<?php

namespace Rosalana\Configure\Abstracts;

use Illuminate\Support\Collection;
use Rosalana\Configure\Configure;
use Rosalana\Configure\Contracts\Node as ContractsNode;
use Rosalana\Configure\Node\File;

abstract class Node implements ContractsNode
{
    protected Configure|ParentNode|null $parent = null;

    protected string $key;
    protected bool $created = false;

    public function __construct(
        protected int $start,
        protected int $end,
        protected array $raw,
    ) {}

    public static function make(int $start, int $end, array $raw): static
    {
        return new static(start: $start, end: $end, raw: $raw);
    }

    public static function makeEmpty(string $key): static
    {
        $instance = static::make(start: 0, end: 0, raw: []);
        $instance->setKey($key)->created = true;

        return $instance;
    }

    public function type(): string
    {
        return strtolower(class_basename($this));
    }

    public function key(): string
    {
        return $this->key;
    }

    public function name(): string
    {
        $parts = explode('.', $this->key);
        return $parts[array_key_last($parts)];
    }

    public function start(): int
    {
        return $this->start;
    }

    public function end(): int
    {
        return $this->end;
    }

    public function raw(): array
    {
        return $this->raw;
    }

    public function path(): string
    {
        if ($this->parent() instanceof ContractsNode) {
            $parentPath = $this->parent()->path();

            if ($parentPath) {
                return $parentPath . '.' . $this->key();
            } else {
                return $this->key();
            }
        } else {
            return $this->key();
        }
    }

    public function depths(): array
    {
        $depths = [];
        foreach ($this->raw as $lineNumber => $line) {
            $trimmed = ltrim($line);
            $depths[$lineNumber] = strlen($line) - strlen($trimmed);
        }
        return $depths;
    }

    public function scale(): int
    {
        return ($this->end - $this->start + 1) + ($this->padding() * 2);
    }

    public function parent(): ParentNode|Configure|null
    {
        return $this->parent;
    }

    public function root(): File
    {
        $current = $this;

        while ($current->parent() instanceof ContractsNode) {
            if ($current->isRoot()) {
                return $current->parent();
            }

            $current = $current->parent();
        }

        throw new \RuntimeException("Node has no root configure.");
    }

    public function siblings(): Collection
    {
        if ($this->parent() instanceof ParentNode) {
            return $this->parent->nodes()->filter(fn($node) => $node !== $this);
        }

        return collect();
    }

    public function siblingsAfter(): Collection
    {
        return $this->siblings()->filter(fn($node) => $node->start() > $this->end());
    }

    public function siblingsBefore(): Collection
    {
        return $this->siblings()->filter(fn($node) => $node->end() < $this->start());
    }

    public function is(Node|ParentNode $node): bool
    {
        return $this === $node;
    }

    public function isNew(): bool
    {
        return $this->created;
    }

    public function exists(): bool
    {
        return ! $this->isNew();
    }

    public function isDirty(): bool
    {
        if ($this->isNew()) {
            return true;
        }

        $raw = $this->raw();
        $render = $this->render();

        if (count($raw) !== count($render)) {
            return true;
        }

        foreach ($raw as $lineNumber => $line) {
            if (!array_key_exists($lineNumber, $render) || $render[$lineNumber] !== $line) {
                return true;
            }
        }

        return false;
    }

    public function isTypeOf(string $type): bool
    {
        return $this->type() === strtolower($type);
    }

    public function isSubNode(): bool
    {
        $path = explode('.', $this->path());

        return count($path) > 1 || $this->parent instanceof ContractsNode;
    }

    public function isRoot(): bool
    {
        return $this->parent instanceof File;
    }

    public function isChildOf(ParentNode $parent): bool
    {
        return $this->parent === $parent;
    }

    public function isSiblingOf(Node|ParentNode $node): bool
    {
        return $this->parent === $node->parent();
    }

    public function isFirstChild(): bool
    {
        if ($this->parent() instanceof ParentNode) {
            return $this->parent()->nodes()->first() === $this;
        }

        return false;
    }

    public function isLastChild(): bool
    {
        if ($this->parent() instanceof ParentNode) {
            return $this->parent()->nodes()->last() === $this;
        }

        return false;
    }

    public function isNextBefore(Node|ParentNode $node): bool
    {
        return $this->siblingsBefore()->last()?->is($node) ?? false;
    }

    public function isNextAfter(Node|ParentNode $node): bool
    {
        return $this->siblingsAfter()->first()?->is($node) ?? false;
    }

    protected function swapWith(Node $other): void
    {
        $this->parent()->swapChildren($this, $other);
    }

    public function moveTo(int $line): static
    {
        $distance = abs($this->start() - $this->end());

        $this->start = $line;
        $this->end = $line + $distance;

        return $this;
    }

    public function moveUp(): static
    {
        $beforeNode = $this->siblingsBefore()->last();

        if (! $beforeNode) return $this;

        $this->swapWith($beforeNode);

        return $this;
    }

    public function moveDown(): static
    {
        $afterNode = $this->siblingsAfter()->first();

        if (! $afterNode) return $this;

        $afterNode->swapWith($this);

        return $this;
    }

    public function keepStart(): static
    {
        return $this->before($this->siblings()->first());
    }

    public function keepEnd(): static
    {
        return $this->after($this->siblings()->last());
    }

    public function before(Node|ParentNode|string $node): static
    {
        if (is_string($node)) {
            $node = $this->parent()->getChild($node);
        }

        if (! $node || ! $this->isSiblingOf($this)) return $this;

        $direction = $this->start() < $node->start() ? 'down' : 'up';

        while (! $this->isNextAfter($node)) {
            if ($direction === 'down') {
                $this->moveDown();
                if ($this->isLastChild()) break;
            } else {
                $this->moveUp();
                if ($this->isFirstChild()) break;
            }
        }

        return $this;
    }

    public function after(Node|ParentNode|string $node): static
    {
        if (is_string($node)) {
            $node = $this->parent()->getChild($node);
        }

        if (! $node || ! $this->isSiblingOf($this)) return $this;

        $direction = $this->start() < $node->start() ? 'down' : 'up';

        while (! $this->isNextBefore($node)) {
            if ($direction === 'down') {
                $this->moveDown();
                if ($this->isLastChild()) break;
            } else {
                $this->moveUp();
                if ($this->isFirstChild()) break;
            }
        }

        return $this;
    }

    public function cut(ParentNode|string $parent): static
    {
        if (is_string($parent)) {
            $parent = $this->root()->section($parent);
        }

        $this->parent()->removeChild($this);
        $parent->addChild($this);

        return $this;
    }

    public function copy(ParentNode|string $parent): static
    {
        if (is_string($parent)) {
            $parent = $this->root()->section($parent);
        }

        $copy = $this->replicate();

        $parent->addChild($copy);

        return $copy;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;
        return $this;
    }

    public function setParent(ParentNode|Configure $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    public function setStart(int $start): static
    {
        $this->start = $start;
        return $this;
    }

    public function setEnd(int $end): static
    {
        $this->end = $end;
        return $this;
    }

    public function rename(string $name): static
    {
        $parts = explode('.', $this->key());
        $parts[array_key_last($parts)] = $name;

        return $this->setKey(implode('.', $parts));
    }

    public function remove(): ParentNode
    {
        return $this->parent()->removeChild($this);
    }

    public function replicate(): static
    {
        return clone $this;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'start' => $this->start,
            'end' => $this->end,
            'raw' => $this->raw,
            'depths' => $this->depths(),
            'key' => $this->key(),
            'path' => $this->path(),
            'name' => $this->name(),
            'is_root' => $this->isRoot(),
            'is_sub_node' => $this->isSubNode(),
            'parent' => $this->parent()?->key() ?? null,
            'was_created' => $this->isNew(),
            'is_dirty' => $this->isDirty(),
            'render' => $this->render(),
        ];
    }

    public function __call($name, $arguments)
    {
        if ($this->parent() === null) {
            throw new \BadMethodCallException("Method {$name} does not exist on " . static::class);
        }

        return $this->parent()->$name(...$arguments);
    }
}

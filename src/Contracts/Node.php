<?php

namespace Rosalana\Configure\Contracts;

use Illuminate\Support\Collection;
use Rosalana\Configure\Abstracts\ParentNode;
use Rosalana\Configure\Abstracts\Node as ChildNode;
use Rosalana\Configure\Configure;
use Rosalana\Configure\Node\File;

interface Node
{
    /**
     * Create a new instance of the node.
     * @param int $start
     * @param int $end
     * @param array $raw
     * 
     * @internal
     * @return static
     */
    public static function make(int $start, int $end, array $raw): static;

    /**
     * Create an empty instance of the node.
     * @param string $key
     * 
     * @internal
     * @return static
     */
    public static function makeEmpty(string $key): static;

    /**
     * Go through the content and parse it into self-nodes.
     * @param array $content
     * 
     * @internal
     * @return Collection<int, static>
     */
    public static function parse(array $content): Collection;

    /**
     * Render the node back to array of lines.
     * 
     * @internal
     * @return array<int, string>
     */
    public function render(): array;

    /**
     * Get the type of the node.
     * 
     * @return string
     */
    public function type(): string;

    /**
     * Get the key of the node.
     * 
     * @internal
     * @return string
     */
    public function key(): string;

    /**
     * Get only the name part of the node's key.
     * 
     * @return string
     */
    public function name(): string;

    /**
     * Get the start line of the node.
     * 
     * @internal
     * @return int
     */
    public function start(): int;

    /**
     * Get the end line of the node.
     * 
     * @internal
     * @return int
     */
    public function end(): int;

    /**
     * Get the raw content of the node. It is a cut of the original file content.
     * 
     * @internal
     * @return array
     */
    public function raw(): array;

    /**
     * Get the full path of the node's key.
     * 
     * @internal
     * @return string
     */
    public function path(): string;

    /**
     * Get the depths of the node's content line by line.
     * 
     * @internal
     * @return array<int, int>
     */
    public function depths(): array;

    /**
     * Get the number of empty lines the node requires before and after it.
     * 
     * @internal
     * @return int
     */
    public function padding(): int;

    /**
     * Get the scale (including padding) of the node.
     * 
     * @internal
     * @return int
     */
    public function scale(): int;

    /**
     * Get the parent node of the current node.
     * 
     * @return ParentNode|Configure|null
     */
    public function parent(): ParentNode|Configure|null;

    /**
     * Get the root file node instance.
     * 
     * @return File
     */
    public function root(): File;

    /**
     * Get the sibling nodes of the current node.
     * 
     * @internal
     * @return Collection<int, ChildNode|ParentNode>
     */
    public function siblings(): Collection;

    /**
     * Get the sibling nodes that come after the current node.
     * 
     * @internal
     * @return Collection<int, ChildNode|ParentNode>
     */
    public function siblingsAfter(): Collection;

    /**
     * Get the sibling nodes that come before the current node.
     * 
     * @internal
     * @return Collection<int, ChildNode|ParentNode>
     */
    public function siblingsBefore(): Collection;

    /**
     * Determine if the node is the same as another node.
     * @param ChildNode|ParentNode $node
     * 
     * @return bool
     */
    public function is(ChildNode|ParentNode $node): bool;

    /**
     * Determine if the node is new (not yet saved).
     * 
     * @return bool
     */
    public function isNew(): bool;

    /**
     * Determine if the node has unsaved changes.
     * 
     * @return bool
     */
    public function isDirty(): bool;

    /**
     * Determine if the node is of a specific type.
     * @param string $type 'file', 'section', 'value', 'richcomment', 'comment'
     * 
     * @return bool
     */
    public function isTypeOf(string $type): bool;

    /**
     * Determine if the node is a sub-node (not top-level).
     * 
     * @return bool
     */
    public function isSubNode(): bool;

    /**
     * Determine if the node is a root-level node.
     * 
     * @return bool
     */
    public function isRoot(): bool;

    /**
     * Determine if the node is a child of the given parent node.
     * @param ParentNode $parent
     * 
     * @return bool
     */
    public function isChildOf(ParentNode $parent): bool;

    /**
     * Determine if the node is a sibling of the given node.
     * @param ChildNode|ParentNode $node
     * 
     * @return bool
     */
    public function isSiblingOf(ChildNode|ParentNode $node): bool;

    /**
     * Determine if the node is the first child of its parent.
     * 
     * @return bool
     */
    public function isFirstChild(): bool;

    /**
     * Determine if the node is the last child of its parent.
     * 
     * @return bool
     */
    public function isLastChild(): bool;

    /**
     * Determine if the node is immediately before another node.
     * @param ChildNode|ParentNode $node
     * 
     * @return bool
     */
    public function isNextBefore(ChildNode|ParentNode $node): bool;

    /**
     * Determine if the node is immediately after another node.
     * @param ChildNode|ParentNode $node
     * 
     * @return bool
     */
    public function isNextAfter(ChildNode|ParentNode $node): bool;

    /**
     * Move the node to a specific line in the file.
     * @param int $line
     * 
     * @internal
     * @return static
     */
    public function moveTo(int $line): static;

    /**
     * Move the node up in it's section
     * 
     * @return static
     */
    public function moveUp(): static;

    /**
     * Move the node down in it's section
     * 
     * @return static
     */
    public function moveDown(): static;

    /**
     * Keep the node at the start of it's section
     * 
     * @return static
     */
    public function keepStart(): static;

    /**
     * Keep the node at the end of it's section
     * 
     * @return static
     */
    public function keepEnd(): static;

    /**
     * Place the node after another node.
     * @param ChildNode|ParentNode|string $node
     * 
     * @return static
     */
    public function after(ChildNode|ParentNode|string $node): static;

    /**
     * Place the node before another node.
     * @param ChildNode|ParentNode|string $node
     * 
     * @return static
     */
    public function before(ChildNode|ParentNode|string $node): static;

    /**
     * Set the key of the node.
     * @param string $key
     * 
     * @internal
     * @return static
     */
    public function setKey(string $key): static;

    /**
     * Set the parent of the node.
     * @param ParentNode|Configure $parent
     * 
     * @return static
     */
    public function setParent(ParentNode|Configure $parent): static;

    /**
     * Set the start line of the node.
     * @param int $start
     * 
     * @return static
     */
    public function setStart(int $start): static;

    /**
     * Set the end line of the node.
     * @param int $end
     * 
     * @return static
     */
    public function setEnd(int $end): static;

    /**
     * Rename the node's key.
     * @param string $name
     * 
     * @return static
     */
    public function rename(string $name): static;

    /**
     * Remove the node from it's parent.
     * 
     * @return ParentNode|Configure
     */
    public function remove(): ParentNode|Configure;

    /**
     * Make a duplicate of the node.
     * 
     * @return static
     */
    public function replicate(): static;

    /**
     * Convert the node to an array representation.
     * 
     * @return array
     */
    public function toArray(): array;
}

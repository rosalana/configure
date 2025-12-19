<?php

namespace Rosalana\Configure\Node;

use Illuminate\Support\Collection;
use Rosalana\Configure\Abstracts\Node;
use Rosalana\Configure\Traits\ZeroPaddingNode;

class Comment extends Node
{
    use ZeroPaddingNode;

    protected string $label = '';

    public function __construct(int $start, int $end, array $raw)
    {
        return parent::__construct($start, $end, $raw);
    }

    public static function makeEmpty(string $label): static
    {
        $instance = parent::makeEmpty('comment_' . bin2hex(random_bytes(4)));
        $instance->setLabel($label, true);
        $instance->end += count(explode("\n", $label)) - 1;

        return $instance;
    }

    public static function parse(array $content): Collection
    {
        $nodes = collect();

        $start = null;
        $buffer = [];
        $stack = [];

        $arrayStartRegex = '/^\s*([\'"])(?<key>[^\'"]+)\1\s*=>\s*\[\s*$/';

        foreach ($content as $index => $line) {

            $trim = trim($line);

            if (preg_match($arrayStartRegex, $line, $match)) {
                $stack[] = $match['key'];
            }

            if ($trim === '],' || $trim === ']') {
                array_pop($stack);
            }

            if (str_starts_with($trim, '//')) {

                if ($start === null) {
                    $start = $index;
                }

                $buffer[$index] = $line;
                continue;
            }

            if ($start !== null) {

                $label = static::parseLabel($buffer);
                $key = 'comment_' . bin2hex(random_bytes(4));
                $end = array_key_last($buffer);

                $nodes->push(
                    Comment::make($start, $end, $buffer)
                        ->setLabel($label, true)
                        ->setKey(
                            $stack ? implode('.', $stack) . '.' . $key : $key
                        )
                );

                $start = null;
                $buffer = [];
            }
        }

        if ($start !== null) {
            $label = static::parseLabel($buffer);
            $key = 'comment_' . bin2hex(random_bytes(4));
            $end = array_key_last($buffer);

            $nodes->push(
                Comment::make($start, $end, $buffer)
                    ->setLabel($label, true)
                    ->setKey(
                        $stack ? implode('.', $stack) . '.' . $key : $key
                    )
            );
        }

        return $nodes;
    }

    public function render(): array
    {
        $result = collect();

        foreach (explode("\n", $this->label()) as $line) {
            $result->push('// ' . $line);
        }

        return $result->mapWithKeys(function ($line, $index) {
            return [$this->start() + $index => str_repeat(' ', $this->parent()?->indent() ?? 0) . $line];
        })->toArray();
    }

    public function label(): string
    {
        return $this->label;
    }

    public function setLabel(string $label, bool $ghost = false): static
    {
        if (! $ghost) {
            $currentScale = count(explode("\n", $this->label));
            $incommingScale = count(explode("\n", $label));

            $this->end += $incommingScale - $currentScale;
            $this->parent()->reflow();
        }

        $this->label = $label;
        return $this;
    }

    protected static function parseLabel(array $buffer): string
    {
        $lines = [];

        foreach ($buffer as $line) {
            $trim = trim($line);
            $trim = ltrim($trim, '/');
            $trim = ltrim($trim, '/');
            $lines[] = trim($trim);
        }

        return implode("\n", $lines);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'label' => $this->label,
        ]);
    }
}

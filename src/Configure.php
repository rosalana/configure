<?php

namespace Rosalana\Configure;

use Rosalana\Configure\Node\File;
use Rosalana\Configure\Support\Reader;
use Rosalana\Configure\Support\Writer;

class Configure
{
    protected File $file;

    protected Reader $reader;

    protected Writer $writer;

    public function __construct(string $file)
    {
        $this->file = File::makeEmpty($file)->setParent($this);

        $this->reader = new Reader($this->file);
        $this->writer = new Writer($this->file);
    }

    public static function file(string $name): File
    {
        return (new self($name))->reader->read();
    }

    public function save(): void
    {
        $this->writer->write();
    }

    /** @internal */
    public function key(): string
    {
        return '';
    }

    public function toArray(): array
    {
        return $this->file->nodes()->map(fn($node) => $node->toArray())->toArray();
    }
}

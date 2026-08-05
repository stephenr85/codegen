<?php

namespace Rushing\Codegen\Model;

/**
 * A named product type — a record of {@see Field}s. This is what a driver realizes as a
 * spatie/laravel-data `Data` class, a TS `interface`, or a Rust `struct`. The unit the "Data class
 * from a schema inline" import case (codegen-substrate #07) produces.
 */
class RecordType
{
    /** @var Field[] */
    public array $fields = [];

    public function __construct(
        public string $name,
        public ?string $doc = null,
    ) {}

    public function field(string $name, Type $type, ?string $doc = null): static
    {
        $this->fields[] = new Field($name, $type, $doc);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'doc' => $this->doc,
            'fields' => array_map(fn (Field $f) => $f->toArray(), $this->fields),
        ], fn ($v) => $v !== null && $v !== []);
    }
}

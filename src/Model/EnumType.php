<?php

namespace Rushing\Codegen\Model;

/**
 * A named enumeration — a fixed set of scalar cases. A driver realizes it as a PHP backed enum, a TS
 * string-literal union / `enum`, or a Rust fieldless enum. Referenced from fields via
 * {@see Type::ref()}, like a {@see RecordType}. Backing type is a {@see Primitive} scalar (string by
 * default) so every target has an honest analog.
 */
class EnumType
{
    /**
     * @param  array<int, string|int>  $cases
     */
    public function __construct(
        public string $name,
        public array $cases = [],
        public Primitive $backing = Primitive::String,
        public ?string $doc = null,
    ) {}

    public function case(string|int $case): static
    {
        $this->cases[] = $case;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'backing' => $this->backing->value,
            'cases' => array_values($this->cases),
            'doc' => $this->doc,
        ], fn ($v) => $v !== null && $v !== []);
    }
}

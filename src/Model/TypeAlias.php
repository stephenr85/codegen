<?php

namespace Rushing\Codegen\Model;

/** A named structural type, including recursive unions and intersections. */
class TypeAlias
{
    public function __construct(
        public string $name,
        public Type $type,
        public ?string $doc = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type->toArray(),
            'doc' => $this->doc,
        ], fn ($v) => $v !== null);
    }
}

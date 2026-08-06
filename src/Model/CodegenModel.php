<?php

namespace Rushing\Codegen\Model;

use Closure;
use Rushing\Codegen\Contracts\Generator;
use Rushing\Codegen\Contracts\SchemaHydrator;

/**
 * The canonical semantic model and its fluent builder — the "ORM for any language" (codegen-substrate
 * #01). One in-memory representation of records + operations that every {@see Generator}
 * consumes (as the serialized `toArray()`) and every driver realizes idiomatically.
 *
 * Authored declaratively when hand-built:
 *
 *   $model = (new CodegenModel)
 *       ->record('User', fn (RecordType $r) => $r
 *           ->field('id', Type::primitive(Primitive::Uuid))
 *           ->field('name', Type::primitive(Primitive::String))
 *           ->field('age', Type::optional(Type::primitive(Primitive::Int))))
 *       ->operation('listUsers', 'GET', '/users', Type::ref('User'), returnsMany: true);
 *
 * ...but in practice it is mostly HYDRATED, not hand-written — a {@see SchemaHydrator}
 * builds it from the JSON Schema the app already emits from its DTOs, and the route manifest adds the
 * operations. The at-rest format is JSON Schema / OpenAPI (the adopted "wheel"); this is the working
 * in-memory form (the "wagon").
 */
class CodegenModel
{
    /** @var array<string, RecordType> */
    public array $records = [];

    /** @var array<string, EnumType> */
    public array $enums = [];

    /** @var Operation[] */
    public array $operations = [];

    /**
     * @param  Closure(RecordType): mixed|null  $define
     */
    public function record(string $name, ?Closure $define = null, ?string $doc = null): static
    {
        $record = $this->records[$name] ??= new RecordType($name, $doc);

        if ($define !== null) {
            $define($record);
        }

        return $this;
    }

    /**
     * Declare a named enumeration. Cases may be passed inline or built via a closure:
     *
     *   ->enum('Status', ['draft', 'published'])
     *   ->enum('Status', fn (EnumType $e) => $e->case('draft')->case('published'))
     *
     * @param  array<int, string|int>|Closure(EnumType): mixed  $cases
     */
    public function enum(
        string $name,
        array|Closure $cases = [],
        Primitive $backing = Primitive::String,
        ?string $doc = null,
    ): static {
        $enum = $this->enums[$name] ??= new EnumType($name, backing: $backing, doc: $doc);

        if ($cases instanceof Closure) {
            $cases($enum);
        } else {
            foreach ($cases as $case) {
                $enum->case($case);
            }
        }

        return $this;
    }

    /**
     * @param  list<string>  $methods
     * @param  array<string, mixed>  $meta
     * @param  Field[]  $params
     */
    public function operation(
        string $name,
        string $method,
        string $path,
        ?Type $returns = null,
        bool $returnsMany = false,
        ?string $doc = null,
        array $methods = [],
        array $meta = [],
        array $params = [],
        ?RecordType $body = null,
    ): static {
        $this->operations[] = new Operation($name, $method, $path, $returns, $returnsMany, $doc, $methods ?: [$method], $meta, $params, $body);

        return $this;
    }

    /**
     * Assert the model is safely emittable before any generator runs (codegen-substrate #01): every
     * `ref` resolves to a defined record/enum, no name is declared twice, no record is empty. Returns
     * `$this` for chaining; throws {@see ModelValidationException} listing *all* problems at once so a
     * host fixes the model in one pass rather than one error per run.
     */
    public function validate(): static
    {
        $known = array_merge(array_keys($this->records), array_keys($this->enums));
        $problems = [];

        $dupes = array_keys(array_filter(array_count_values($known), fn (int $n) => $n > 1));
        foreach ($dupes as $name) {
            $problems[] = "type name `{$name}` is declared more than once";
        }

        foreach ($this->records as $record) {
            if ($record->fields === []) {
                $problems[] = "record `{$record->name}` has no fields";
            }
            foreach ($record->fields as $field) {
                foreach ($this->refsIn($field->type) as $ref) {
                    if (! in_array($ref, $known, true)) {
                        $problems[] = "field `{$record->name}.{$field->name}` references undefined type `{$ref}`";
                    }
                }
            }
        }

        foreach ($this->operations as $operation) {
            if ($operation->returns === null) {
                continue;
            }
            foreach ($this->refsIn($operation->returns) as $ref) {
                if (! in_array($ref, $known, true)) {
                    $problems[] = "operation `{$operation->name}` returns undefined type `{$ref}`";
                }
            }
        }

        if ($problems !== []) {
            throw ModelValidationException::of($problems);
        }

        return $this;
    }

    /**
     * Every `ref` name reachable inside a type, recursing through list/map/optional/union.
     *
     * @return string[]
     */
    private function refsIn(Type $type): array
    {
        // Only internal refs must resolve; an External ref points outside the model by design.
        if ($type->kind === TypeKind::Ref && $type->ref !== null) {
            return [$type->ref];
        }

        $refs = [];
        foreach (array_merge([$type->inner, $type->value], $type->variants) as $child) {
            if ($child instanceof Type) {
                $refs = array_merge($refs, $this->refsIn($child));
            }
        }

        return $refs;
    }

    /**
     * The canonical serialization handed to every generator's `invoke()` as `input['model']`. This
     * shape is the generator wire contract (codegen-substrate #01) — treat additions as append-only.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'records' => array_map(fn (RecordType $r) => $r->toArray(), array_values($this->records)),
            'enums' => array_map(fn (EnumType $e) => $e->toArray(), array_values($this->enums)),
            'operations' => array_map(fn (Operation $o) => $o->toArray(), $this->operations),
        ];
    }
}

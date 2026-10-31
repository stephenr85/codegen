<?php

namespace Rushing\Codegen\Hydration;

use Rushing\Codegen\Contracts\SchemaHydrator;
use Rushing\Codegen\Model\CodegenModel;
use Rushing\Codegen\Model\Field;
use Rushing\Codegen\Model\Primitive;
use Rushing\Codegen\Model\RecordType;
use Rushing\Codegen\Model\Type;

/**
 * Hydrates a {@see CodegenModel} from a JSON Schema document — the v1 source path (codegen-substrate
 * #03). Named schemas under `$defs`/`definitions` (and the root, if titled) become records/enums/aliases;
 * property schemas map to the type-kind vocabulary.
 *
 * Scope (skeleton, honest about its edges):
 * - `$ref: '#/$defs/Foo'` → {@see Type::ref}('Foo'). Only the *name* is resolved here — deep/external
 *   `$ref` resolution is a solved problem to ADOPT a resolver for (research-wheel), not hand-roll.
 * - Inline nested objects (an object schema with `properties` but no name/`$ref`) are best-effort
 *   mapped to {@see Primitive::Json} rather than synthesizing anonymous records — a named `$def` is the
 *   supported way to model a nested record. Named compositions retain inline records so their
 *   required discriminator literals are preserved.
 */
class JsonSchemaHydrator implements SchemaHydrator
{
    /**
     * @param  array<string, mixed>  $jsonSchema
     */
    public function hydrate(array $jsonSchema, ?string $rootName = null): CodegenModel
    {
        $model = new CodegenModel;

        $defs = $jsonSchema['$defs'] ?? $jsonSchema['definitions'] ?? [];

        // The root itself may be a named object or composition schema. Its name is the caller's to give — see
        // SchemaHydrator::hydrate() for why `title` is only the fallback and not the source of truth.
        $root = $rootName ?? ($jsonSchema['title'] ?? null);

        if ($root !== null && (($jsonSchema['type'] ?? null) === 'object'
            || isset($jsonSchema['oneOf']) || isset($jsonSchema['anyOf']) || isset($jsonSchema['allOf']))) {
            $defs[$root] = $jsonSchema;
        }

        foreach ($defs as $name => $schema) {
            if (! is_array($schema)) {
                continue;
            }

            if (isset($schema['oneOf']) || isset($schema['anyOf']) || isset($schema['allOf'])) {
                $model->alias($name, $this->typeFor($schema, preserveComposition: true), $schema['description'] ?? null);

                continue;
            }

            if (isset($schema['enum']) && ! isset($schema['properties'])) {
                $model->enum($name, array_values($schema['enum']), $this->enumBacking($schema), $schema['description'] ?? null);

                continue;
            }

            if (($schema['type'] ?? null) === 'object' || isset($schema['properties'])) {
                $model->record($name, function (RecordType $record) use ($schema) {
                    $required = $schema['required'] ?? [];
                    foreach ($schema['properties'] ?? [] as $field => $propSchema) {
                        $type = $this->typeFor($propSchema);
                        if (! in_array($field, $required, true) && $type->kind->value !== 'optional') {
                            $type = Type::optional($type);
                        }
                        $record->field($field, $type, is_array($propSchema) ? ($propSchema['description'] ?? null) : null);
                    }
                }, $schema['description'] ?? null);
            }
        }

        return $model;
    }

    /**
     * Map a single JSON Schema property/value schema to a {@see Type}. Public so a consumer that hydrates
     * operations (path params, request-body fields) off an OpenAPI document — not just top-level records —
     * can reuse the exact same type mapping instead of re-deriving it. `hydrate()` uses this internally.
     *
     * @param  array<string, mixed>|mixed  $schema
     * @param  bool  $preserveComposition  retain exact union branches and inline records within named compositions
     */
    public function typeFor(mixed $schema, bool $preserveComposition = false): Type
    {
        if (! is_array($schema)) {
            return Type::primitive(Primitive::Json);
        }

        if (array_key_exists('const', $schema)) {
            return Type::literal($schema['const']);
        }

        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            return Type::intersection(array_map(fn ($member) => $this->typeFor($member, preserveComposition: true), $schema['allOf']));
        }

        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            return Type::ref($this->refName($schema['$ref']));
        }

        foreach (['oneOf', 'anyOf'] as $union) {
            if (isset($schema[$union]) && is_array($schema[$union])) {
                return $preserveComposition
                    ? Type::union(array_map(fn ($member) => $this->typeFor($member, preserveComposition: true), $schema[$union]), $union === 'anyOf' ? 'anyOf' : null)
                    : $this->unionOrOptional($schema[$union]);
            }
        }

        // Composition members must retain their object constraints, especially required literal
        // discriminators. Ordinary anonymous properties retain the established JSON fallback.
        if ($preserveComposition && isset($schema['properties'])) {
            $fields = [];
            foreach ($schema['properties'] as $name => $property) {
                $type = $this->typeFor($property, preserveComposition: true);
                if (! in_array($name, $schema['required'] ?? [], true)) {
                    $type = Type::optional($type);
                }
                $fields[] = new Field($name, $type, $property['description'] ?? null);
            }

            return Type::record($fields);
        }

        if ($preserveComposition && ($schema['type'] ?? null) === 'null') {
            return Type::literal(null);
        }

        // A nullable type array, e.g. ['type' => ['string', 'null']].
        if (is_array($schema['type'] ?? null)) {
            $nonNull = array_values(array_filter($schema['type'], fn ($t) => $t !== 'null'));
            $inner = $this->typeFor(['type' => $nonNull[0] ?? 'string'] + $schema);

            return in_array('null', $schema['type'], true) ? Type::optional($inner) : $inner;
        }

        return match ($schema['type'] ?? null) {
            'array' => Type::listOf($this->typeFor($schema['items'] ?? [], $preserveComposition)),
            'object' => isset($schema['additionalProperties']) && ! isset($schema['properties'])
                ? Type::mapOf($this->typeFor($schema['additionalProperties'], $preserveComposition))
                : Type::primitive(Primitive::Json),
            'string' => Type::primitive($this->stringFormat($schema['format'] ?? null)),
            'integer' => Type::primitive(Primitive::Int),
            'number' => Type::primitive(Primitive::Float),
            'boolean' => Type::primitive(Primitive::Bool),
            default => Type::primitive(Primitive::Json),
        };
    }

    /**
     * @param  array<int, mixed>  $members
     */
    private function unionOrOptional(array $members): Type
    {
        $nullable = false;
        $types = [];
        foreach ($members as $member) {
            if (is_array($member) && ($member['type'] ?? null) === 'null') {
                $nullable = true;

                continue;
            }
            $types[] = $this->typeFor($member);
        }

        $type = count($types) === 1 ? $types[0] : Type::union($types);

        return $nullable ? Type::optional($type) : $type;
    }

    private function stringFormat(?string $format): Primitive
    {
        return match ($format) {
            'uuid' => Primitive::Uuid,
            'date-time', 'date' => Primitive::Timestamp,
            default => Primitive::String,
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function enumBacking(array $schema): Primitive
    {
        return match ($schema['type'] ?? 'string') {
            'integer' => Primitive::Int,
            default => Primitive::String,
        };
    }

    private function refName(string $ref): string
    {
        return substr($ref, (int) strrpos($ref, '/') + 1);
    }
}

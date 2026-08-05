<?php

namespace Rushing\Codegen\Hydration;

use Rushing\Codegen\Contracts\SchemaHydrator;
use Rushing\Codegen\Model\CodegenModel;
use Rushing\Codegen\Model\Primitive;
use Rushing\Codegen\Model\RecordType;
use Rushing\Codegen\Model\Type;

/**
 * Hydrates a {@see CodegenModel} from a JSON Schema document — the v1 source path (codegen-substrate
 * #03). Named schemas under `$defs`/`definitions` (and the root, if titled) become records/enums;
 * property schemas map to the type-kind vocabulary.
 *
 * Scope (skeleton, honest about its edges):
 * - `$ref: '#/$defs/Foo'` → {@see Type::ref}('Foo'). Only the *name* is resolved here — deep/external
 *   `$ref` resolution is a solved problem to ADOPT a resolver for (research-wheel), not hand-roll.
 * - Inline nested objects (an object schema with `properties` but no name/`$ref`) are best-effort
 *   mapped to {@see Primitive::Json} rather than synthesizing anonymous records — a named `$def` is the
 *   supported way to model a nested record. This keeps the skeleton bounded; #03 may lift it later.
 */
class JsonSchemaHydrator implements SchemaHydrator
{
    /**
     * @param  array<string, mixed>  $jsonSchema
     */
    public function hydrate(array $jsonSchema): CodegenModel
    {
        $model = new CodegenModel;

        $defs = $jsonSchema['$defs'] ?? $jsonSchema['definitions'] ?? [];

        // The root itself may be a named object schema.
        if (isset($jsonSchema['title']) && ($jsonSchema['type'] ?? null) === 'object') {
            $defs[$jsonSchema['title']] = $jsonSchema;
        }

        foreach ($defs as $name => $schema) {
            if (! is_array($schema)) {
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
     * @param  array<string, mixed>|mixed  $schema
     */
    private function typeFor(mixed $schema): Type
    {
        if (! is_array($schema)) {
            return Type::primitive(Primitive::Json);
        }

        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            return Type::ref($this->refName($schema['$ref']));
        }

        foreach (['oneOf', 'anyOf'] as $union) {
            if (isset($schema[$union]) && is_array($schema[$union])) {
                return $this->unionOrOptional($schema[$union]);
            }
        }

        // A nullable type array, e.g. ['type' => ['string', 'null']].
        if (is_array($schema['type'] ?? null)) {
            $nonNull = array_values(array_filter($schema['type'], fn ($t) => $t !== 'null'));
            $inner = $this->typeFor(['type' => $nonNull[0] ?? 'string'] + $schema);

            return in_array('null', $schema['type'], true) ? Type::optional($inner) : $inner;
        }

        return match ($schema['type'] ?? null) {
            'array' => Type::listOf($this->typeFor($schema['items'] ?? [])),
            'object' => isset($schema['additionalProperties']) && ! isset($schema['properties'])
                ? Type::mapOf($this->typeFor($schema['additionalProperties']))
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

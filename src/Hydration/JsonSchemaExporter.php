<?php

namespace Rushing\Codegen\Hydration;

use Rushing\Codegen\Contracts\SchemaExporter;
use Rushing\Codegen\Model\CodegenModel;
use Rushing\Codegen\Model\Type;

/**
 * Exports a {@see CodegenModel} to a JSON Schema document — the inverse of {@see JsonSchemaHydrator}
 * (codegen-substrate #07). Records → object schemas under `$defs`, enums → enum schemas, fields →
 * property schemas; an optional field drops out of `required`; refs become `$ref` pointers. Together
 * with the hydrator this makes the model a round-trippable interchange point, not a one-way sink.
 *
 * Fidelity note: an {@see Type::external} ref has no first-class JSON Schema
 * form, so it exports as a `$ref` by name — re-hydrating it yields an *internal* ref (the
 * internal/external distinction is a model-level nicety JSON Schema can't carry). Everything else
 * round-trips structurally.
 */
class JsonSchemaExporter implements SchemaExporter
{
    /**
     * Export the model to a JSON Schema document.
     *
     * With `$id` null this returns the bare `['$defs' => [...]]` document (the model round-trips, but
     * carries no identity). With a non-empty `$id` it prepends a JSON-Schema header (`$schema` + `$id`)
     * so the whole model becomes ONE registerable bundle document — the write-side enabler: hand it to a
     * write-once schema registry's `register()` (which keys/fingerprints by `$id`) to complete the
     * model↔schema round-trip (codegen-substrate #07).
     *
     * The entire model bundles under a single `$id`; internal `#/$defs/...` `$ref`s stay valid inside the
     * one document. Per-record split-registration (one `$id` per record, with `$ref` rewriting across
     * documents) is a future refinement.
     *
     * @return array<string, mixed>
     */
    public function export(CodegenModel $model, ?string $id = null): array
    {
        $defs = [];

        foreach ($model->enums as $enum) {
            $defs[$enum->name] = array_filter([
                'type' => $enum->backing->value === 'int' ? 'integer' : 'string',
                'enum' => array_values($enum->cases),
                'description' => $enum->doc,
            ], fn ($v) => $v !== null && $v !== []);
        }

        foreach ($model->records as $record) {
            $properties = [];
            $required = [];

            foreach ($record->fields as $field) {
                $type = $field->type->toArray();
                $optional = ($type['kind'] ?? null) === 'optional';
                $properties[$field->name] = $this->schemaFor($optional ? ($type['inner'] ?? []) : $type, $field->doc);

                if (! $optional) {
                    $required[] = $field->name;
                }
            }

            $defs[$record->name] = array_filter([
                'type' => 'object',
                'description' => $record->doc,
                'properties' => $properties,
                'required' => $required,
            ], fn ($v) => $v !== null && $v !== []);
        }

        if ($id === null || $id === '') {
            return ['$defs' => $defs];
        }

        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$id' => $id,
            '$defs' => $defs,
        ];
    }

    /**
     * @param  array<string, mixed>  $type  a serialized Type
     * @return array<string, mixed>
     */
    private function schemaFor(array $type, ?string $doc = null): array
    {
        $schema = match ($type['kind'] ?? null) {
            'primitive' => $this->primitive($type['primitive'] ?? 'json'),
            'ref', 'external' => ['$ref' => '#/$defs/'.($type['ref'] ?? '')],
            'list' => ['type' => 'array', 'items' => $this->schemaFor($type['inner'] ?? [])],
            'map' => ['type' => 'object', 'additionalProperties' => $this->schemaFor($type['value'] ?? [])],
            'optional' => $this->schemaFor($type['inner'] ?? []),
            'union' => ['oneOf' => array_map(fn ($v) => $this->schemaFor($v), $type['variants'] ?? [])],
            default => [],
        };

        if ($doc !== null && ! isset($schema['$ref'])) {
            $schema['description'] = $doc;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function primitive(string $p): array
    {
        return match ($p) {
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'bool' => ['type' => 'boolean'],
            'uuid' => ['type' => 'string', 'format' => 'uuid'],
            'timestamp' => ['type' => 'string', 'format' => 'date-time'],
            'bytes' => ['type' => 'string'],
            'json' => ['type' => 'object'],
            default => ['type' => 'string'],
        };
    }
}

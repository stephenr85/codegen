<?php

namespace Rushing\Codegen\Hydration;

use Rushing\Codegen\Contracts\SchemaHydrator;
use Rushing\Codegen\Model\CodegenModel;
use Rushing\Codegen\Model\Primitive;
use Rushing\Codegen\Model\RecordType;
use Rushing\Codegen\Model\Type;

/**
 * Hydrates a {@see CodegenModel} from `tofu providers schema -json`'s document shape
 * (`provider_schemas` → `<provider>` → `resource_schemas` → `<resource type>` → `block`) — the
 * gcp-cloud-run-provisioning map's ticket 08. Every `resource_schemas` entry present in the document
 * becomes a top-level record named from its PascalCased resource type (e.g.
 * `google_sql_database_instance` → `GoogleSqlDatabaseInstance`); scoping to a handful of resource
 * types (as the map's tickets 07/09 need) is the caller's job — pass a document already filtered down,
 * same as {@see JsonSchemaHydrator} hydrates whatever `$defs` it's handed.
 *
 * Unlike JSON Schema's `object`-without-`$ref` case (which {@see JsonSchemaHydrator} collapses to
 * {@see Primitive::Json} as a deliberate v1 skeleton limit), Terraform's nested blocks carry real,
 * useful fields that are exactly as "named" as the resource itself — a block's name is fixed by its
 * key in `block_types`, it's simply anonymous relative to the whole document. So every nested block
 * (`block_types`) and every inline object-shaped attribute type (`["object", {...}]`, the cty
 * encoding SDKv2 providers use for nested-but-not-block attribute values) synthesizes its own named
 * record instead, named by concatenating its parent record's name with its own PascalCased key
 * (`GoogleSqlDatabaseInstance` + `settings` → `GoogleSqlDatabaseInstanceSettings`) — flat and
 * dot-free, since `rushing/laravel-codegen`'s `DataClassGenerator` uses a record's name verbatim as
 * a PHP class name.
 */
class TerraformSchemaHydrator implements SchemaHydrator
{
    /**
     * @param  array<string, mixed>  $jsonSchema  a decoded `tofu providers schema -json` document
     */
    public function hydrate(array $jsonSchema): CodegenModel
    {
        $model = new CodegenModel;

        foreach ($jsonSchema['provider_schemas'] ?? [] as $providerSchema) {
            foreach ($providerSchema['resource_schemas'] ?? [] as $resourceType => $schema) {
                $this->hydrateBlock($model, $this->pascal($resourceType), $schema['block'] ?? []);
            }
        }

        return $model;
    }

    /**
     * @param  array<string, mixed>  $block  a `block` object: `attributes` + `block_types`
     */
    private function hydrateBlock(CodegenModel $model, string $recordName, array $block): void
    {
        $model->record($recordName, function (RecordType $record) use ($model, $recordName, $block) {
            // Required fields are collected separately and added first: DataClassGenerator emits
            // fields as promoted constructor parameters in field order, and PHP requires every
            // required parameter to precede any optional one. Attributes and block_types interleave
            // required/optional in whatever order the provider declares them, so a stable sort is the
            // difference between valid and deprecated-at-minimum generated PHP.
            $required = [];
            $optional = [];

            foreach ($block['attributes'] ?? [] as $attrName => $attrSchema) {
                $type = $this->typeFor($model, $recordName, $attrName, $attrSchema['type'] ?? null);
                $isRequired = $attrSchema['required'] ?? false;
                $entry = [$attrName, $isRequired ? $type : Type::optional($type), $attrSchema['description'] ?? null];
                $isRequired ? $required[] = $entry : $optional[] = $entry;
            }

            foreach ($block['block_types'] ?? [] as $blockName => $blockSchema) {
                $childName = $recordName.$this->pascal($blockName);
                $this->hydrateBlock($model, $childName, $blockSchema['block'] ?? []);

                $ref = Type::ref($childName);
                $type = match ($blockSchema['nesting_mode'] ?? 'single') {
                    'list', 'set' => Type::listOf($ref),
                    'map' => Type::mapOf($ref),
                    default => $ref,
                };
                $isRequired = ($blockSchema['min_items'] ?? 0) >= 1;
                $entry = [$blockName, $isRequired ? $type : Type::optional($type), $blockSchema['block']['description'] ?? null];
                $isRequired ? $required[] = $entry : $optional[] = $entry;
            }

            foreach ([...$required, ...$optional] as [$name, $type, $doc]) {
                $record->field($name, $type, $doc);
            }
        });
    }

    /**
     * Map a single cty type constraint — a scalar keyword ("string") or a `[kind, ...]` array
     * ("list"/"set"/"map" wrap an inner constraint, "object" carries a `{field: constraint}` map) —
     * to a {@see Type}, synthesizing a named record for every "object" encountered along the way.
     */
    private function typeFor(CodegenModel $model, string $parentName, string $fieldName, mixed $ctyType): Type
    {
        if (is_string($ctyType)) {
            return match ($ctyType) {
                'string' => Type::primitive(Primitive::String),
                'number' => Type::primitive(Primitive::Float),
                'bool' => Type::primitive(Primitive::Bool),
                default => Type::primitive(Primitive::Json),
            };
        }

        if (! is_array($ctyType) || ! isset($ctyType[0])) {
            return Type::primitive(Primitive::Json);
        }

        [$kind, $detail] = [$ctyType[0], $ctyType[1] ?? null];

        return match ($kind) {
            'list', 'set' => Type::listOf($this->typeFor($model, $parentName, $fieldName, $detail)),
            'map' => Type::mapOf($this->typeFor($model, $parentName, $fieldName, $detail)),
            'object' => $this->hydrateInlineObject($model, $parentName, $fieldName, is_array($detail) ? $detail : []),
            default => Type::primitive(Primitive::Json),
        };
    }

    /**
     * @param  array<string, mixed>  $fields  the object type's `{field: constraint}` map
     */
    private function hydrateInlineObject(CodegenModel $model, string $parentName, string $fieldName, array $fields): Type
    {
        $name = $parentName.$this->pascal($fieldName);

        $model->record($name, function (RecordType $record) use ($model, $name, $fields) {
            foreach ($fields as $fName => $fType) {
                $record->field($fName, $this->typeFor($model, $name, $fName, $fType));
            }
        });

        return Type::ref($name);
    }

    private function pascal(string $snakeCase): string
    {
        return str_replace('_', '', ucwords($snakeCase, '_'));
    }
}

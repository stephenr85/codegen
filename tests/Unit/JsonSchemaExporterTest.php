<?php

use Rushing\Codegen\Hydration\JsonSchemaExporter;
use Rushing\Codegen\Hydration\JsonSchemaHydrator;
use Rushing\Codegen\Model\CodegenModel;
use Rushing\Codegen\Model\Primitive;
use Rushing\Codegen\Model\RecordType;
use Rushing\Codegen\Model\Type;

it('exports records, enums, refs, lists, maps and optionality to JSON Schema', function () {
    $model = (new CodegenModel)
        ->enum('Status', ['draft', 'published'])
        ->record('Post', fn (RecordType $r) => $r
            ->field('id', Type::primitive(Primitive::Uuid))
            ->field('status', Type::ref('Status'))
            ->field('tags', Type::listOf(Type::primitive(Primitive::String)))
            ->field('meta', Type::mapOf(Type::primitive(Primitive::Int)))
            ->field('title', Type::optional(Type::primitive(Primitive::String))));

    $schema = (new JsonSchemaExporter)->export($model);

    expect($schema['$defs']['Status'])->toBe(['type' => 'string', 'enum' => ['draft', 'published']]);

    $post = $schema['$defs']['Post'];
    expect($post['type'])->toBe('object')
        ->and($post['required'])->toBe(['id', 'status', 'tags', 'meta'])   // title optional → excluded
        ->and($post['properties']['id'])->toBe(['type' => 'string', 'format' => 'uuid'])
        ->and($post['properties']['status'])->toBe(['$ref' => '#/$defs/Status'])
        ->and($post['properties']['tags'])->toBe(['type' => 'array', 'items' => ['type' => 'string']])
        ->and($post['properties']['meta']['additionalProperties'])->toBe(['type' => 'integer'])
        ->and($post['properties']['title'])->toBe(['type' => 'string']);
});

it('round-trips: JSON Schema -> model -> JSON Schema is stable', function () {
    $schema = [
        '$defs' => [
            'Status' => ['type' => 'string', 'enum' => ['draft', 'published']],
            'Post' => [
                'type' => 'object',
                'required' => ['id', 'status', 'tags'],
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'status' => ['$ref' => '#/$defs/Status'],
                    'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'title' => ['type' => 'string'],
                ],
            ],
        ],
    ];

    $model = (new JsonSchemaHydrator)->hydrate($schema)->validate();
    $out = (new JsonSchemaExporter)->export($model);

    // Second pass must be identical to the first export — the model is a stable fixed point.
    $model2 = (new JsonSchemaHydrator)->hydrate($out)->validate();
    $out2 = (new JsonSchemaExporter)->export($model2);

    expect($out2)->toBe($out)
        ->and($out['$defs']['Post']['required'])->toBe(['id', 'status', 'tags'])
        ->and($out['$defs']['Post']['properties']['status'])->toBe(['$ref' => '#/$defs/Status'])
        ->and($out['$defs']['Status']['enum'])->toBe(['draft', 'published']);
});

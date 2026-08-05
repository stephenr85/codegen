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

it('export without an $id is unchanged — a bare $defs document, no $id/$schema header', function () {
    $model = (new CodegenModel)
        ->enum('Status', ['draft', 'published'])
        ->record('Post', fn (RecordType $r) => $r->field('id', Type::primitive(Primitive::Uuid)));

    $schema = (new JsonSchemaExporter)->export($model);

    expect($schema)->toHaveKey('$defs')
        ->and($schema)->not->toHaveKey('$id')
        ->and($schema)->not->toHaveKey('$schema')
        ->and(array_keys($schema))->toBe(['$defs']);
});

it('export with an $id prepends $schema + $id and keeps the same $defs', function () {
    $model = (new CodegenModel)
        ->enum('Status', ['draft', 'published'])
        ->record('Post', fn (RecordType $r) => $r->field('id', Type::primitive(Primitive::Uuid)));

    $bare = (new JsonSchemaExporter)->export($model);
    $bundle = (new JsonSchemaExporter)->export($model, 'https://x/model/1');

    expect(array_keys($bundle))->toBe(['$schema', '$id', '$defs'])
        ->and($bundle['$schema'])->toBe('https://json-schema.org/draft/2020-12/schema')
        ->and($bundle['$id'])->toBe('https://x/model/1')
        ->and($bundle['$defs'])->toBe($bare['$defs']);
});

it('export with the same $id is deterministic — identical arrays across runs (stable fingerprint)', function () {
    $model = (new CodegenModel)
        ->enum('Status', ['draft', 'published'])
        ->record('Post', fn (RecordType $r) => $r
            ->field('id', Type::primitive(Primitive::Uuid))
            ->field('status', Type::ref('Status')));

    $first = (new JsonSchemaExporter)->export($model, 'https://x/model/1');
    $second = (new JsonSchemaExporter)->export($model, 'https://x/model/1');

    expect($second)->toBe($first);
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

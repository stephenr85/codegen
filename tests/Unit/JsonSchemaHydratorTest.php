<?php

use Rushing\Codegen\Hydration\JsonSchemaHydrator;

it('hydrates records, enums, refs, lists, maps and optionality from JSON Schema', function () {
    $schema = [
        '$defs' => [
            'Status' => [
                'type' => 'string',
                'enum' => ['draft', 'published'],
            ],
            'Tag' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ],
            'Post' => [
                'type' => 'object',
                'required' => ['id', 'status'],
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'status' => ['$ref' => '#/$defs/Status'],
                    'title' => ['type' => 'string'],                                  // optional (not required)
                    'tags' => ['type' => 'array', 'items' => ['$ref' => '#/$defs/Tag']],
                    'meta' => ['type' => 'object', 'additionalProperties' => ['type' => 'integer']],
                    'note' => ['type' => ['string', 'null']],                          // nullable
                ],
            ],
        ],
    ];

    $model = (new JsonSchemaHydrator)->hydrate($schema)->validate();
    $array = $model->toArray();

    // Enum
    expect($array['enums'])->toHaveCount(1)
        ->and($array['enums'][0]['name'])->toBe('Status')
        ->and($array['enums'][0]['cases'])->toBe(['draft', 'published']);

    // Post record field kinds
    $post = array_values(array_filter($array['records'], fn ($r) => $r['name'] === 'Post'))[0];
    $byName = array_column($post['fields'], null, 'name');

    expect($byName['id']['type']['primitive'])->toBe('uuid')
        ->and($byName['status']['type'])->toBe(['kind' => 'ref', 'ref' => 'Status'])
        ->and($byName['title']['type']['kind'])->toBe('optional')          // not required → optional
        ->and($byName['tags']['type']['kind'])->toBe('optional')           // not required → optional list
        ->and($byName['tags']['type']['inner']['kind'])->toBe('list')
        ->and($byName['tags']['type']['inner']['inner'])->toBe(['kind' => 'ref', 'ref' => 'Tag'])
        ->and($byName['meta']['type']['kind'])->toBe('optional')
        ->and($byName['meta']['type']['inner']['kind'])->toBe('map')
        ->and($byName['note']['type']['kind'])->toBe('optional');
});

it('treats a titled root object as a record', function () {
    $schema = [
        'title' => 'User',
        'type' => 'object',
        'required' => ['id'],
        'properties' => ['id' => ['type' => 'integer']],
    ];

    $model = (new JsonSchemaHydrator)->hydrate($schema);

    expect($model->records)->toHaveKey('User')
        ->and($model->records['User']->fields[0]->name)->toBe('id');
});

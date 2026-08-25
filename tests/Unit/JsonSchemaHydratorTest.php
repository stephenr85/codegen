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

it('lets the caller name the root record, overriding the title', function () {
    // The root has no `$defs` key to be named by, so historically its name came from `title`. A
    // caller holding the schema's identity (the registry path holds its `$id`) names it instead.
    $schema = [
        'title' => 'Early access request',
        'type' => 'object',
        'required' => ['email'],
        'properties' => ['email' => ['type' => 'string']],
    ];

    $model = (new JsonSchemaHydrator)->hydrate($schema, 'IntakeWaitlistData');

    expect($model->records)->toHaveKey('IntakeWaitlistData')
        ->and($model->records)->not->toHaveKey('Early access request')
        ->and($model->records['IntakeWaitlistData']->fields[0]->name)->toBe('email');
});


it('hydrates no root record when nothing names it', function () {
    // `llm_strict` mode unsets `title`, so such a document reaches here nameless. That must stay a
    // no-op rather than inventing a name — but see CodegenModel::validate(), which is where an
    // ILLEGAL name is caught rather than being carried downstream to the generator.
    $schema = [
        'type' => 'object',
        'properties' => ['email' => ['type' => 'string']],
    ];

    expect((new JsonSchemaHydrator)->hydrate($schema)->records)->toBe([]);
});

<?php

use Rushing\Codegen\Hydration\TerraformSchemaHydrator;

it('hydrates resource_schemas, block_types and inline object attribute types from a tofu providers schema document', function () {
    $document = [
        'format_version' => '1.0',
        'provider_schemas' => [
            'registry.opentofu.org/hashicorp/google' => [
                'resource_schemas' => [
                    'google_sql_database_instance' => [
                        'block' => [
                            'attributes' => [
                                'name' => ['type' => 'string', 'required' => true],
                                'project' => ['type' => 'string', 'optional' => true, 'computed' => true],
                                'tags' => ['type' => ['set', 'string'], 'optional' => true],
                                'labels' => ['type' => ['map', 'string'], 'optional' => true],
                                'server_details' => [
                                    // inline cty object type — no $ref, no block_types entry
                                    'type' => ['list', ['object', ['locked' => 'bool', 'note' => 'string']]],
                                    'computed' => true,
                                ],
                            ],
                            'block_types' => [
                                'settings' => [
                                    'nesting_mode' => 'list',
                                    'min_items' => 1,
                                    'block' => [
                                        'attributes' => [
                                            'tier' => ['type' => 'string', 'required' => true],
                                        ],
                                        'block_types' => [
                                            'ip_configuration' => [
                                                'nesting_mode' => 'list',
                                                'block' => [
                                                    'attributes' => [
                                                        'ipv4_enabled' => ['type' => 'bool', 'optional' => true],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $model = (new TerraformSchemaHydrator)->hydrate($document)->validate();
    $array = $model->toArray();
    $byName = array_column($array['records'], null, 'name');

    // Top-level record name: PascalCased resource type.
    expect($byName)->toHaveKeys([
        'GoogleSqlDatabaseInstance',
        'GoogleSqlDatabaseInstanceSettings',
        'GoogleSqlDatabaseInstanceSettingsIpConfiguration',
        'GoogleSqlDatabaseInstanceServerDetails',
    ]);

    $instance = array_column($byName['GoogleSqlDatabaseInstance']['fields'], null, 'name');
    expect($instance['name']['type'])->toBe(['kind' => 'primitive', 'primitive' => 'string'])              // required
        ->and($instance['project']['type']['kind'])->toBe('optional')                                       // optional+computed -> optional
        ->and($instance['tags']['type']['inner']['kind'])->toBe('list')                                     // set -> list
        ->and($instance['labels']['type']['inner']['kind'])->toBe('map')
        ->and($instance['server_details']['type']['kind'])->toBe('optional')
        ->and($instance['server_details']['type']['inner']['kind'])->toBe('list')
        ->and($instance['server_details']['type']['inner']['inner'])->toBe(['kind' => 'ref', 'ref' => 'GoogleSqlDatabaseInstanceServerDetails'])
        ->and($instance['settings']['type']['kind'])->toBe('list')                                          // min_items:1 -> required, not optional
        ->and($instance['settings']['type']['inner'])->toBe(['kind' => 'ref', 'ref' => 'GoogleSqlDatabaseInstanceSettings']);

    $settings = array_column($byName['GoogleSqlDatabaseInstanceSettings']['fields'], null, 'name');
    expect($settings['tier']['type'])->toBe(['kind' => 'primitive', 'primitive' => 'string'])
        ->and($settings['ip_configuration']['type']['kind'])->toBe('optional')                              // no min_items -> optional
        ->and($settings['ip_configuration']['type']['inner'])->toBe(['kind' => 'list', 'inner' => ['kind' => 'ref', 'ref' => 'GoogleSqlDatabaseInstanceSettingsIpConfiguration']]);

    $serverDetails = array_column($byName['GoogleSqlDatabaseInstanceServerDetails']['fields'], null, 'name');
    expect($serverDetails['locked']['type'])->toBe(['kind' => 'primitive', 'primitive' => 'bool'])
        ->and($serverDetails['note']['type'])->toBe(['kind' => 'primitive', 'primitive' => 'string']);
});

it('hydrates every resource_schemas entry present in the document, across providers', function () {
    $document = [
        'provider_schemas' => [
            'registry.opentofu.org/hashicorp/google' => [
                'resource_schemas' => [
                    'google_sql_user' => [
                        'block' => ['attributes' => ['name' => ['type' => 'string', 'required' => true]]],
                    ],
                ],
            ],
            'registry.opentofu.org/hashicorp/random' => [
                'resource_schemas' => [
                    'random_password' => [
                        'block' => ['attributes' => ['length' => ['type' => 'number', 'required' => true]]],
                    ],
                ],
            ],
        ],
    ];

    $model = (new TerraformSchemaHydrator)->hydrate($document)->validate();

    expect($model->records)->toHaveKeys(['GoogleSqlUser', 'RandomPassword']);
});

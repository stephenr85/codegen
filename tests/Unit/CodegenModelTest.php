<?php

use Rushing\Codegen\Model\CodegenModel;
use Rushing\Codegen\Model\EnumType;
use Rushing\Codegen\Model\Field;
use Rushing\Codegen\Model\ModelValidationException;
use Rushing\Codegen\Model\Primitive;
use Rushing\Codegen\Model\RecordType;
use Rushing\Codegen\Model\Type;

it('builds and serializes a record + operation model', function () {
    $model = (new CodegenModel)
        ->record('User', fn (RecordType $r) => $r
            ->field('id', Type::primitive(Primitive::Uuid))
            ->field('name', Type::primitive(Primitive::String))
            ->field('age', Type::optional(Type::primitive(Primitive::Int))))
        ->operation('listUsers', 'GET', '/users', Type::ref('User'), returnsMany: true);

    $array = $model->toArray();

    expect($array['records'])->toHaveCount(1)
        ->and($array['records'][0]['name'])->toBe('User')
        ->and($array['records'][0]['fields'])->toHaveCount(3)
        ->and($array['records'][0]['fields'][2]['type']['kind'])->toBe('optional')
        ->and($array['operations'][0]['method'])->toBe('GET')
        ->and($array['operations'][0]['returnsMany'])->toBeTrue();
});

it('models enums, unions and maps', function () {
    $model = (new CodegenModel)
        ->enum('Status', ['draft', 'published'])
        ->enum('Priority', fn (EnumType $e) => $e->case('low')->case('high'))
        ->record('Post', fn (RecordType $r) => $r
            ->field('status', Type::ref('Status'))
            ->field('meta', Type::mapOf(Type::primitive(Primitive::String)))
            ->field('body', Type::union([
                Type::primitive(Primitive::String),
                Type::ref('Post'),
            ])));

    $array = $model->toArray();

    expect($array['enums'])->toHaveCount(2)
        ->and($array['enums'][0]['cases'])->toBe(['draft', 'published'])
        ->and($array['enums'][1]['cases'])->toBe(['low', 'high'])
        ->and($array['records'][0]['fields'][1]['type']['kind'])->toBe('map')
        ->and($array['records'][0]['fields'][2]['type']['kind'])->toBe('union')
        ->and($array['records'][0]['fields'][2]['type']['variants'])->toHaveCount(2);
});

it('validates a well-formed model and returns itself', function () {
    $model = (new CodegenModel)
        ->enum('Status', ['draft'])
        ->record('Post', fn (RecordType $r) => $r
            ->field('status', Type::ref('Status')));

    expect($model->validate())->toBe($model);
});

it('rejects dangling refs, empty records and duplicate names', function () {
    $model = (new CodegenModel)
        ->record('Empty')
        ->record('Post', fn (RecordType $r) => $r
            ->field('author', Type::ref('User')))            // undefined
        ->enum('Post', ['x']);                                // duplicate name

    expect(fn () => $model->validate())
        ->toThrow(ModelValidationException::class);

    try {
        $model->validate();
    } catch (ModelValidationException $e) {
        expect($e->getMessage())
            ->toContain('`Post` is declared more than once')
            ->toContain('record `Empty` has no fields')
            ->toContain('undefined type `User`');
    }
});

it('allows external refs to point outside the model without failing validation', function () {
    $model = (new CodegenModel)
        ->operation('listUsers', 'GET', '/users', Type::external('App.Data.User'), returnsMany: true)
        ->record('Local', fn (RecordType $r) => $r
            ->field('external', Type::external('App.Data.Thing'))
            ->field('internalList', Type::listOf(Type::external('App.Data.Item'))));

    expect($model->validate())->toBe($model)
        ->and($model->toArray()['operations'][0]['returns'])->toBe(['kind' => 'external', 'ref' => 'App.Data.User']);
});

it('resolves refs nested inside list/optional/union when validating', function () {
    $model = (new CodegenModel)
        ->record('Tag', fn (RecordType $r) => $r->field('name', Type::primitive(Primitive::String)))
        ->record('Post', fn (RecordType $r) => $r
            ->field('tags', Type::listOf(Type::optional(Type::ref('Tag')))));

    expect($model->validate())->toBe($model);
});

it('refuses a type name that is not a legal identifier', function () {
    // The name becomes a class name in every target language. Caught here, where the model still knows
    // WHICH record is at fault — not in nette's addClass(), four packages downstream of the schema that
    // caused it, where the failure reads as a generator crash.
    $model = (new CodegenModel)
        ->record('Early access request', fn (RecordType $r) => $r->field('email', Type::primitive(Primitive::String)));

    expect(fn () => $model->validate())
        ->toThrow(ModelValidationException::class, 'is not a legal identifier');
});

it('accepts legal identifiers including underscores', function () {
    $model = (new CodegenModel)
        ->record('_Internal2', fn (RecordType $r) => $r->field('a', Type::primitive(Primitive::String)));

    expect($model->validate())->toBeInstanceOf(CodegenModel::class);
});

it('validates named aliases in operation returns and recursive record fields', function () {
    $model = (new CodegenModel)
        ->alias('Node', Type::union([Type::ref('Branch'), Type::literal(null)]))
        ->record('Branch', fn (RecordType $r) => $r->field('children', Type::listOf(Type::ref('Node'))))
        ->operation('getTree', 'GET', '/tree', Type::ref('Node'));

    expect($model->validate())->toBe($model);
});

it('rejects undefined refs nested inside alias intersections and inline record fields', function () {
    $model = (new CodegenModel)->alias('Node', Type::union([
        Type::intersection([
            Type::ref('MissingBranch'),
            Type::record([new Field('child', Type::mapOf(Type::optional(Type::ref('MissingChild'))))]),
        ]),
    ]));

    try {
        $model->validate();
        test()->fail('Expected alias reference validation to fail');
    } catch (ModelValidationException $e) {
        expect($e->getMessage())->toContain('alias `Node` references undefined type `MissingBranch`')
            ->toContain('alias `Node` references undefined type `MissingChild`');
    }
});

it('rejects alias name collisions and illegal alias identifiers', function () {
    $model = (new CodegenModel)
        ->enum('Node', ['leaf'])
        ->alias('Node', Type::literal('leaf'))
        ->alias('invalid-name', Type::literal(null));

    try {
        $model->validate();
        test()->fail('Expected alias name validation to fail');
    } catch (ModelValidationException $e) {
        expect($e->getMessage())->toContain('type name `Node` is declared more than once')
            ->toContain('type name `invalid-name` is not a legal identifier');
    }
});

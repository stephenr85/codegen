<?php

use Rushing\Codegen\GeneratorRegistry;
use Rushing\Codegen\Generators\ClosureGenerator;
use Rushing\Codegen\Model\CodegenModel;
use Rushing\Codegen\Model\Primitive;
use Rushing\Codegen\Model\RecordType;
use Rushing\Codegen\Model\Type;
use Rushing\Popcorn\Registries\Exceptions\RegistryMiss;
use Rushing\Popcorn\Registries\Key;

function fakeModel(): CodegenModel
{
    return (new CodegenModel)
        ->record('User', fn (RecordType $r) => $r
            ->field('id', Type::primitive(Primitive::Uuid)));
}

it('registers a generator and runs the model through its own registry', function () {
    $registry = new GeneratorRegistry;

    $registry->register(new ClosureGenerator('fake-lang', function (array $model, array $options) {
        $name = $model['records'][0]['name'];

        return ["types/{$name}.txt" => "record {$name}"];
    }));

    expect($registry->stacks())->toBe(['fake-lang'])
        ->and($registry->has('fake-lang'))->toBeTrue();

    $files = $registry->generate('fake-lang', fakeModel());

    expect($files)->toBe(['types/User.txt' => 'record User']);
});

it('owns its branch of the keyspace: relative in, absolute out', function () {
    $registry = new GeneratorRegistry;

    $registry->register(new ClosureGenerator('fake-lang', fn (array $model, array $options) => ['x' => 'y']));

    // Registered relative, addressed absolute — this is what lets RegistryIndex route `codegen.
    // generators.fake-lang` here without codegen telling the index anything but its root.
    expect((string) $registry->keys()[0])->toBe('codegen.generators.fake-lang')
        ->and($registry->has(Key::parse('codegen.generators.fake-lang')))->toBeTrue()
        ->and($registry->stacks())->toBe(['fake-lang']);
});

it('a generator IS an invocable — the popcorn seam sees it with no codegen knowledge', function () {
    $registry = new GeneratorRegistry;

    $registry->register(new ClosureGenerator('fake-lang', fn (array $model, array $options) => ['x' => 'y']));

    $out = $registry->resolve('fake-lang')->invoke([
        'model' => fakeModel()->toArray(),
        'options' => [],
    ]);

    expect($out)->toBe(['files' => ['x' => 'y']]);
});

it('misses loudly on an unregistered stack rather than returning an empty file map', function () {
    (new GeneratorRegistry)->generate('no-such-stack', fakeModel());
})->throws(RegistryMiss::class);

<?php

use Rushing\Codegen\GeneratorRegistry;
use Rushing\Codegen\Generators\ClosureGenerator;
use Rushing\Codegen\Model\CodegenModel;
use Rushing\Codegen\Model\Primitive;
use Rushing\Codegen\Model\RecordType;
use Rushing\Codegen\Model\Type;
use Rushing\Popcorn\InvocableRegistry;

function fakeModel(): CodegenModel
{
    return (new CodegenModel)
        ->record('User', fn (RecordType $r) => $r
            ->field('id', Type::primitive(Primitive::Uuid)));
}

it('registers a generator and runs the model through the popcorn registry', function () {
    $registry = new GeneratorRegistry(new InvocableRegistry);

    $registry->register(new ClosureGenerator('fake-lang', function (array $model, array $options) {
        $name = $model['records'][0]['name'];

        return ["types/{$name}.txt" => "record {$name}"];
    }));

    expect($registry->stacks())->toBe(['fake-lang'])
        ->and($registry->has('fake-lang'))->toBeTrue();

    $files = $registry->generate('fake-lang', fakeModel());

    expect($files)->toBe(['types/User.txt' => 'record User']);
});

it('reaches the same generator by name through the underlying InvocableRegistry', function () {
    $invocables = new InvocableRegistry;
    $registry = new GeneratorRegistry($invocables);

    $registry->register(new ClosureGenerator('fake-lang', fn (array $model, array $options) => ['x' => 'y']));

    // A generator IS an invocable — the popcorn seam sees it with no codegen knowledge.
    $out = $invocables->invoke('fake-lang', ['model' => fakeModel()->toArray(), 'options' => []]);

    expect($out)->toBe(['files' => ['x' => 'y']]);
});

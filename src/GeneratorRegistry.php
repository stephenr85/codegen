<?php

namespace Rushing\Codegen;

use Rushing\Codegen\Contracts\Generator;
use Rushing\Codegen\Model\CodegenModel;
use Rushing\Popcorn\InvocableRegistry;
use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\Exceptions\RegistryMiss;
use Rushing\Popcorn\Registries\Gated;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\OnDuplicate;
use Rushing\Popcorn\Registries\Optionality;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryArity;
use Rushing\Popcorn\Registries\RegistryKey;

/**
 * The registry of stacks/languages (codegen-substrate #02) — a generator-typed registry over its own
 * branch of the keyspace. It owns almost nothing: resolution, duplicate policy and enumeration are
 * the kernel's. This class exists only to (a) name the seam in codegen terms and (b) list generators
 * by their {@see Generator::stack()} so a host can enumerate targets.
 *
 * A generator is registered like any invocable — the store does not care whether it is a
 * `LocalInvocable` (native PHP emitter) or a `RunnerInvocable` (subprocess toolchain in another
 * language). That indifference is the whole point.
 *
 * ## It holds ONE collection, and that is the migration
 *
 * This used to hold a `[stack => Generator]` array of its own AND push each generator into the shared
 * {@see InvocableRegistry} — the same entries counted twice, in two places, able to
 * disagree the moment anything was removed from one. Registry-kernel ticket 40 cuts it onto its own
 * root: one flat ordered list, `stacks()` derived from it rather than kept beside it.
 *
 * Dropping the shared singleton is the point of the fan-out and not an incidental tidy — codegen's
 * generators were addressable as bare `fake-lang` in a pool every other package also wrote into, so a
 * host registering a `rust` generator and a host registering a `rust` conduit collided on a name
 * neither package could see the other spelling. They are `codegen.generators.rust` now, and the index
 * routes to this registry because it declared that branch.
 */
#[IsRegistry(
    root: 'codegen.generators',
    of: 'code generators, one per target stack/language',
    arity: RegistryArity::PickOne,
    entryType: Generator::class,
    onDuplicate: OnDuplicate::Supersede,
    optionality: Optionality::Optional,
    note: 'A later registration of the same stack id overrides the earlier one: a host swapping the '
        .'shipped `ts-client` emitter for its own is the seam, not an accident.',
)]
class GeneratorRegistry implements Gated, Registry
{
    private BasicRegistry $entries;

    public function __construct()
    {
        $this->entries = BasicRegistry::for($this);
    }

    /**
     * Register a generator under its own stack id.
     *
     * The parameter is WIDENED from the contract rather than shadowing it — contravariance, the same
     * one-argument self-keying door {@see InvocableRegistry::register()} opens, so
     * every contract caller keeps working.
     */
    public function register(RegistryKey|string|Generator $key, mixed $entry = null, ?string $by = null, ?string $ability = null): static
    {
        if ($key instanceof Generator) {
            $entry = $key;
            $key = $key->stack();
        }

        $this->entries->register($key, $entry, $by, $ability);

        return $this;
    }

    /**
     * The registered stack ids, as callers spelled them — {@see keys()} with the declared root
     * stripped back off, because keys go relative in and absolute out (ticket 20 D2).
     *
     * The un-stamping is the kernel's, not this class's: it is the inverse of `door()` and lives
     * beside it, which is also why a foreign key comes back whole rather than being string-stripped.
     *
     * @return string[]
     */
    public function stacks(): array
    {
        return $this->entries->relativeKeys();
    }

    public function has(RegistryKey|string $key): bool
    {
        return $this->entries->has($key);
    }

    public function resolve(RegistryKey|string $key): mixed
    {
        return $this->entries->resolve($key);
    }

    public function tryResolve(RegistryKey|string $key): mixed
    {
        return $this->entries->tryResolve($key);
    }

    public function matches(RegistryKey|string $key): array
    {
        return $this->entries->matches($key);
    }

    public function keys(): array
    {
        return $this->entries->keys();
    }

    public function unfiltered(): Registry
    {
        return $this->entries->unfiltered();
    }

    public function authorizeWith(?Authorizer $authorizer): static
    {
        $this->entries->authorizeWith($authorizer);

        return $this;
    }

    /**
     * Run one generator against a model, returning its file map (path => contents). The host writes
     * and formats; the generator only emits bytes. See {@see Generator} for the invoke() contract.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, string>
     *
     * @throws RegistryMiss no generator is registered for that stack
     */
    public function generate(string $stack, CodegenModel $model, array $options = []): array
    {
        /** @var Generator $generator */
        $generator = $this->resolve($stack);

        $out = $generator->invoke([
            'model' => $model->toArray(),
            'options' => $options,
        ]);

        /** @var array<string, string> */
        return $out['files'] ?? [];
    }
}

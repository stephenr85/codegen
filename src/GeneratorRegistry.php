<?php

namespace Rushing\Codegen;

use Rushing\Codegen\Contracts\Generator;
use Rushing\Codegen\Model\CodegenModel;
use Rushing\Popcorn\InvocableRegistry;

/**
 * The registry of stacks/languages (codegen-substrate #02) — a thin, generator-typed facade over
 * popcorn's {@see InvocableRegistry}. It deliberately owns almost nothing: registration, name
 * override, resolution, and invocation are popcorn's; sandboxing/grants for out-of-language
 * toolchains are popcorn's `RunnerInvocable`. This class exists only to (a) name the seam in codegen
 * terms and (b) list generators by their {@see Generator::stack()} so a host can enumerate targets.
 *
 * A generator is registered like any invocable — the underlying `InvocableRegistry` does not care
 * whether it is a `LocalInvocable` (native PHP emitter) or a `RunnerInvocable` (subprocess toolchain
 * in another language). That indifference is the whole point.
 */
class GeneratorRegistry
{
    /** @var array<string, Generator> */
    private array $generators = [];

    public function __construct(
        private InvocableRegistry $invocables,
    ) {}

    public function register(Generator $generator): static
    {
        $this->generators[$generator->stack()] = $generator;
        $this->invocables->register($generator);

        return $this;
    }

    /** @return string[] the registered stack ids */
    public function stacks(): array
    {
        return array_keys($this->generators);
    }

    public function has(string $stack): bool
    {
        return isset($this->generators[$stack]);
    }

    /**
     * Run one generator against a model, returning its file map (path => contents). The host writes
     * and formats; the generator only emits bytes. See {@see Generator} for the invoke() contract.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, string>
     */
    public function generate(string $stack, CodegenModel $model, array $options = []): array
    {
        $out = $this->invocables->invoke($stack, [
            'model' => $model->toArray(),
            'options' => $options,
        ]);

        /** @var array<string, string> */
        return $out['files'] ?? [];
    }
}

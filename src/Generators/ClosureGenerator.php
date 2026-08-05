<?php

namespace Rushing\Codegen\Generators;

use Closure;
use Rushing\Codegen\Contracts\Generator;
use Rushing\Popcorn\Binding;

/**
 * A native (in-process) emitter defined by a closure — the ergonomic default for a PHP-authored
 * generator (codegen-substrate #02), the codegen analog of popcorn's `LocalInvocable`. The closure
 * receives the decoded model + options and returns a file map (path => contents). Binding is
 * {@see Binding::Local}: it does its work in-process.
 *
 * Heavier native drivers (the Saloon connector, the Data-class emitter) are their own classes; this
 * covers the common "here is a stack id and a render closure" case without a bespoke class each time.
 */
class ClosureGenerator implements Generator
{
    private Closure $render;

    /**
     * @param  callable(array<string, mixed> $model, array<string, mixed> $options): array<string, string>  $render
     */
    public function __construct(
        private string $stack,
        callable $render,
        private ?string $description = null,
    ) {
        $this->render = $render(...);
    }

    public function name(): string
    {
        return $this->stack;
    }

    public function stack(): string
    {
        return $this->stack;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function binding(): Binding
    {
        return Binding::Local;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function invoke(array $input): array
    {
        return [
            'files' => ($this->render)($input['model'] ?? [], $input['options'] ?? []),
        ];
    }
}

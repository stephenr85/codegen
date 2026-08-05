<?php

namespace Rushing\Codegen\Contracts;

use Rushing\Popcorn\Contracts\Invocable;
use Rushing\Popcorn\Invocables\LocalInvocable;
use Rushing\Popcorn\Invocables\RunnerInvocable;

/**
 * A code generator IS a popcorn {@see Invocable} (codegen-substrate #02): the array-in is the
 * serialized semantic model plus options; the array-out is a file map. Whether the generator runs
 * in-process (a native PHP emitter — a {@see LocalInvocable}) or shells
 * out to another language's toolchain (jco/wasmtime for WIT bindings, `openapi-generator`, a Rust
 * codegen crate — a {@see RunnerInvocable}) is *only* a popcorn
 * `Binding`. Callers resolve a target by name from the registry and never see the difference; the
 * registry of stacks/languages IS a `Rushing\Popcorn\InvocableRegistry`.
 *
 * The wire contract is popcorn's own — this sub-interface adds nothing to `invoke()`; it only names
 * the *stack* a generator targets so a host can list/filter targets. It exists so "a generator" is a
 * legible type, not so it diverges from the invocable seam.
 *
 * ## The invoke() contract
 *
 * Input (array-in):
 *   [
 *     'model'   => array,   // a serialized CodegenModel (see Model\CodegenModel::toArray())
 *     'options' => array,   // target-specific knobs (out-dir hints, feature flags)
 *   ]
 *
 * Output (array-out):
 *   [
 *     'files' => array<string, string>,  // relative path => file contents
 *   ]
 *
 * A generator NEVER writes to disk itself — it returns files; the host (rushing/laravel-codegen)
 * owns placement, formatting (Prettier/Pint), and the write. This keeps generators pure and lets a
 * RunnerInvocable's Grant sandbox an other-language toolchain that only ever emits bytes back.
 */
interface Generator extends Invocable
{
    /**
     * The target stack/language id this generator emits for — e.g. `ts-client`, `php-saloon`,
     * `php-data`, `rust`, `wit-bindings`. The registry key by convention.
     */
    public function stack(): string;
}

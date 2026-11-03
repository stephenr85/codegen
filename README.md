# rushing/codegen

A framework-free kernel for **language-neutral code generation**. One fluent semantic meta-model
(records, operations, type-kinds) — hydrated from the JSON Schema you already emit from your DTOs —
driven to any target by **generators that are [popcorn](https://github.com/stephenr85/php-popcorn)
invocables**.

> "The format is the wheel; you build the wagon." The at-rest format is JSON Schema / OpenAPI
> (adopted, not reinvented). This package is the in-memory meta-model + the generator seam + the
> registry — **not** a new IR and **not** an emitter for one language.

## The one idea

A **generator is a popcorn `Invocable`**: serialized-model-in → file-map-out. So:

- a **native PHP emitter** (Saloon connector, `Data` class) is a `LocalInvocable`;
- an **other-language toolchain** (jco/wasmtime for WIT bindings, `openapi-generator`, a Rust codegen
  crate) is a `RunnerInvocable`, sandboxed by its popcorn `Grant`;

…and the **registry of stacks/languages is a popcorn registry**, `GeneratorRegistry`, keyed by stack. Callers resolve a target
by name and never see which kind it is. This package inherits invocation, override-by-name,
sandboxing, and grants from popcorn and stays tiny.

## Layers

| Package | Seam | This is |
| --- | --- | --- |
| **`rushing/codegen`** (here) | open substrate | meta-model + `Generator` contract + registry + hydration contract |
| `rushing/laravel-codegen` | open binding | artisan command, config, provider, generic emitters (codegen-substrate #04) |
| schemastud (`laravel-data-schemas`) | open foundation | the primitive type-kind vocabulary, promoted down once stable (#08) |
| `splicewire/phport` | **proprietary** | the porting mission: Rust `beam-core` → WASM, WIT bindings, the port (north star) |

## Registries

This package declares one registry:

- [`codegen.generators`](docs/registries/codegen.generators.mdx): one generator per target stack,
  each an invocable that turns a model into a file map.

## Status

Skeleton (codegen-substrate v1).

## Style

House style: no `declare(strict_types=1)`, no `final`, no `readonly`. Pest + Pint.

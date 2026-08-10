> You are in **rushing/codegen** — a framework-free kernel for language-neutral code generation: a fluent semantic meta-model (records, operations, type-kinds) hydrated from JSON Schema, driven to any target by generators that are popcorn invocables.

A PHP package. No framework dependency — the meta-model, hydration contract, and generator registry are usable outside Laravel; `rushing/laravel-codegen` is the Laravel binding.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.

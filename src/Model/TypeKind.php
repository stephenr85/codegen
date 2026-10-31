<?php

namespace Rushing\Codegen\Model;

/**
 * The structural type-kinds every language and every schema can speak — the "Data lens with global
 * awareness" (codegen-substrate #01). This vocabulary is SEMANTIC (declare intent), not SYNTACTIC
 * (build constructs): a driver realizes `Record` as a PHP spatie/laravel-data class, a TS
 * `interface`, or a Rust `struct` — the model never says "class". Because every language has "a
 * record of named fields", strict-mode has almost nothing to throw on; best-effort is coherent.
 *
 * Vocabulary borrowed from Smithy shapes / TypeSpec's type algebra rather than invented ad-hoc.
 *
 * HOME (codegen-substrate #08, decided): this is the canonical home. `rushing/codegen` is already
 * open, so the vocabulary is public here; it is deliberately NOT promoted down into schemastud. If a
 * schemastud-tier package ever wants a value-object type model, it depends UP on `rushing/codegen`
 * rather than the vocabulary being fragmented across the foundation — codegen is the type-vocabulary
 * substrate, not a consumer of it.
 */
enum TypeKind: string
{
    case Primitive = 'primitive';
    case Record = 'record';
    case Enumeration = 'enum';
    case Union = 'union';
    case Intersection = 'intersection';
    case Literal = 'literal';
    case ListOf = 'list';
    case MapOf = 'map';
    case Optional = 'optional';
    case Ref = 'ref';
    case External = 'external';
}

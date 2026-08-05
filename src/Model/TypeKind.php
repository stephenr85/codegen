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
 * NOTE (codegen-substrate #08): this enum is the primitive vocabulary slated to be promoted DOWN
 * into the open schemastud foundation (likely alongside laravel-data-schemas) once the seam proves
 * out. It is born here to avoid premature churn on the most-depended-on packages, not because this
 * is its final home.
 */
enum TypeKind: string
{
    case Primitive = 'primitive';
    case Record = 'record';
    case Enumeration = 'enum';
    case Union = 'union';
    case ListOf = 'list';
    case MapOf = 'map';
    case Optional = 'optional';
    case Ref = 'ref';
    case External = 'external';
}

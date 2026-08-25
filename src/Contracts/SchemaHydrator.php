<?php

namespace Rushing\Codegen\Contracts;

use Rushing\Codegen\Model\CodegenModel;

/**
 * Hydrates the canonical {@see CodegenModel} FROM a source document (codegen-substrate #03). The
 * v1 source is JSON Schema — which the app already emits from its spatie/laravel-data DTOs
 * (`JsonSchemaGenerator`, `schemas:generate`) — so the meta-model is *derived*, rarely hand-built.
 * The route manifest supplies the operation surface on top of the schema-derived types.
 *
 * The reverse direction (model -> JSON Schema / OpenAPI) is the "keep the import flow open" case
 * (codegen-substrate #07, deferred): once the model round-trips, emitting a Data class from a schema
 * inline is just another {@see Generator}. Kept off the v1 critical path but designed for — that is
 * why hydration and emission are separate seams rather than one bidirectional converter.
 */
interface SchemaHydrator
{
    /**
     * ## Why the root name is a PARAMETER and not read off the document
     *
     * Named `$defs` entries carry their own identity: the map key IS the name, and `#/$defs/Foo`
     * addresses it, so `$ref` resolution and record naming agree by construction. The ROOT schema has
     * no such key — it is the document itself — so a hydrator that must name it has to reach into the
     * payload, and the only name-shaped thing there is `title`.
     *
     * `title` cannot carry that weight. JSON Schema defines it as a human-readable label; form
     * renderers display it; `schemastud/laravel-data-schemas` ships a `#[Title]` attribute expressly so
     * a class short-name stops leaking into rendered labels; and its own `SchemaFingerprint` lists
     * `title` as a VOLATILE key — prose you may reword without registering as schema drift. A name the
     * immutability layer calls volatile cannot be the name a class is generated under.
     *
     * So the caller names the root. The caller is the one holding the schema's identity — for the
     * registry-backed path that is the `$id`, which is unique by the registry's write-once rule — and
     * identity is exactly what a root record name should derive from. Passing null keeps the legacy
     * behaviour (fall back to `title`) so a hand-built or `$defs`-only document still hydrates.
     *
     * There is deliberately no in-document override keyword. Naming has two sources here, and the
     * defect this parameter fixes was caused by having too many — a schema that wants to pin its own
     * name is a case to build when one exists, not before.
     *
     * @param  array<string, mixed>  $jsonSchema  a decoded JSON Schema document
     * @param  string|null  $rootName  what to call the root object schema, when the caller knows;
     *                                 null falls back to the document's `title`
     */
    public function hydrate(array $jsonSchema, ?string $rootName = null): CodegenModel;
}

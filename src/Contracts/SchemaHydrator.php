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
     * @param  array<string, mixed>  $jsonSchema  a decoded JSON Schema document
     */
    public function hydrate(array $jsonSchema): CodegenModel;
}

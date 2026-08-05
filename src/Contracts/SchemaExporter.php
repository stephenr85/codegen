<?php

namespace Rushing\Codegen\Contracts;

use Rushing\Codegen\Model\CodegenModel;

/**
 * The inverse of {@see SchemaHydrator}: serializes a {@see CodegenModel} back OUT to a schema document
 * (codegen-substrate #07). This is the "keep the import flow open" round-trip — a model hydrated from
 * JSON Schema, edited in memory, and written back; or a model assembled from DTOs and exported as a
 * portable JSON Schema/OpenAPI others can consume. A host swaps exporters (JSON Schema vs OpenAPI) the
 * way it swaps hydrators.
 */
interface SchemaExporter
{
    /**
     * @param  string|null  $id  when non-empty, stamps a JSON-Schema header (`$schema` + `$id`) so the
     *                           exported document is a registerable bundle; when null, returns the bare
     *                           `$defs` document unchanged (codegen-substrate #07).
     * @return array<string, mixed> a decoded schema document
     */
    public function export(CodegenModel $model, ?string $id = null): array;
}

<?php

namespace Rushing\Codegen\Model;

/**
 * An API-surface operation — the layer above the type shapes that turns the model from "a bag of
 * data types" into "a describable service". Sourced from the route manifest (the app's
 * `RouteManifestSource`). This is what the Saloon-connector driver (codegen-substrate #06) and the
 * TS-client driver (codegen-substrate #05) both consume; the records are shared, the operations are
 * the API facet on top.
 */
class Operation
{
    /**
     * @param  list<string>  $methods  all HTTP verbs this operation answers (a route may allow several,
     *                                 e.g. `['PUT', 'PATCH']`). `$method` is the primary/first.
     * @param  array<string, mixed>  $meta  host-specific facets that are part of the API surface but not
     *                                      the type vocabulary — e.g. `['realm' => 'admin']` (which client
     *                                      answers it). An escape hatch, deliberately untyped, so host
     *                                      concerns ride along without polluting the core model.
     */
    public function __construct(
        public string $name,
        public string $method,
        public string $path,
        public ?Type $returns = null,
        public bool $returnsMany = false,
        public ?string $doc = null,
        public array $methods = [],
        public array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'method' => $this->method,
            'methods' => $this->methods ?: null,
            'path' => $this->path,
            'returns' => $this->returns?->toArray(),
            'returnsMany' => $this->returnsMany ?: null,
            'meta' => $this->meta ?: null,
            'doc' => $this->doc,
        ], fn ($v) => $v !== null);
    }
}

<?php

namespace Rushing\Codegen\Model;

use RuntimeException;

/**
 * Thrown by {@see CodegenModel::validate()} when a model is not safely emittable — a dangling `ref`
 * (a type points at a name no record/enum defines), an empty record, or a duplicate type name.
 * Failing here — before any generator runs — keeps a malformed model from producing broken code in
 * every target at once.
 */
class ModelValidationException extends RuntimeException
{
    /**
     * @param  string[]  $problems
     */
    public static function of(array $problems): static
    {
        return new static("Invalid codegen model:\n  - ".implode("\n  - ", $problems));
    }
}

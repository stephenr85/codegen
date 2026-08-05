<?php

namespace Rushing\Codegen\Model;

/**
 * The scalar leaf-kinds under {@see TypeKind::Primitive}. Deliberately small and portable — each has
 * an honest analog in every target language, so a driver's primitive mapping never hits the
 * "no analog" wall that a syntactic construct (trait, ownership) would. Anything richer is modelled
 * structurally (a `Record`, a `Union`) rather than added as a primitive.
 */
enum Primitive: string
{
    case String = 'string';
    case Int = 'int';
    case Float = 'float';
    case Bool = 'bool';
    case Bytes = 'bytes';
    case Timestamp = 'timestamp';
    case Uuid = 'uuid';
    case Json = 'json';
}

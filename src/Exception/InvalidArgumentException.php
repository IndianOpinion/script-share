<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Exception;

/**
 * A caller passed an argument outside its documented range.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements ScriptShareException
{
}

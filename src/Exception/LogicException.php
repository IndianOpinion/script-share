<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Exception;

/**
 * The library was used in the wrong order, for example add() after finish().
 */
final class LogicException extends \LogicException implements ScriptShareException
{
}

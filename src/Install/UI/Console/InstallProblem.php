<?php

declare(strict_types=1);

namespace SightPress\Core\Install\UI\Console;

/** A step can't go on. The message says what's wrong and how to fix it. */
final class InstallProblem extends \RuntimeException
{
}

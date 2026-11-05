<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Fixtures\App;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/** Listed for prod only, so the test kernel must not register it. */
final class ProdOnlyBundle extends Bundle
{
}

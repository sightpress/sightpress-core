<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Support;

use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Translator;

/** The core's en-GB catalogue, so console tests read the real plugin names. */
final class CoreTranslator
{
    public static function create(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('xlf', new XliffFileLoader());
        $translator->addResource('xlf', \dirname(__DIR__, 2).'/translations/messages+intl-icu.en.xlf', 'en', 'messages+intl-icu');

        return $translator;
    }
}

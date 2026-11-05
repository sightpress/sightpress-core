<?php

declare(strict_types=1);

namespace SightPress\Core\Plugin\Application;

/**
 * One entry of the official plugin catalogue. Data only: the core never loads
 * plugin code from it. Its name and description are translation keys.
 */
final readonly class OfficialPlugin
{
    /**
     * @param list<string> $requires     ids of the official plugins it needs
     * @param string|null  $setupCommand the command for its install-wide settings, if it has any
     */
    public function __construct(
        public string $id,
        public string $package,
        public string $constraint,
        public array $requires = [],
        public ?string $setupCommand = null,
    ) {
    }

    public function nameKey(): string
    {
        return 'plugin.official.'.$this->id.'.name';
    }

    public function descriptionKey(): string
    {
        return 'plugin.official.'.$this->id.'.description';
    }

    /** What to give `composer require`. */
    public function requirement(): string
    {
        return $this->package.':'.$this->constraint;
    }

    /** The command an operator runs to install it later. */
    public function installCommand(): string
    {
        return 'php bin/console sightpress:plugin:install '.$this->id;
    }
}

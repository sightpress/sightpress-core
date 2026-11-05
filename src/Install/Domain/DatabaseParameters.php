<?php

declare(strict_types=1);

namespace SightPress\Core\Install\Domain;

/** Where the PostgreSQL database is. The password is never printed. */
final readonly class DatabaseParameters
{
    public function __construct(
        public string $host,
        public int $port,
        public string $name,
        public string $user,
        #[\SensitiveParameter]
        public string $password,
    ) {
    }

    /** The DATABASE_URL Doctrine reads. It holds the password, so it is a secret. */
    public function url(): string
    {
        $host = str_contains($this->host, ':') && !str_starts_with($this->host, '[') ? '['.$this->host.']' : $this->host;

        return \sprintf('postgresql://%s:%s@%s:%d/%s?serverVersion=17&charset=utf8', rawurlencode($this->user), rawurlencode($this->password), $host, $this->port, rawurlencode($this->name));
    }

    /** How to name the server in messages, without the password. */
    public function describe(): string
    {
        return \sprintf('%s on %s port %d as %s', $this->name, $this->host, $this->port, $this->user);
    }
}

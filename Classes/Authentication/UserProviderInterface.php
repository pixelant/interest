<?php

declare(strict_types=1);

namespace Pixelant\Interest\Authentication;

interface UserProviderInterface
{
    /**
     * Returns if the user with the given credentials is valid.
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function checkCredentials(string $username, string $password): bool;
}

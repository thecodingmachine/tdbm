<?php

namespace TheCodingMachine\TDBM\Fixtures\Traits;

use function password_verify;

trait TestUserTrait
{
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->getPassword());
    }

    public function method1(): string
    {
        return 'TestUserTrait';
    }
}

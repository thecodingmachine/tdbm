<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Fixtures\Interfaces;

interface TestUserInterface
{
    public function getLogin(): string;
    public function getPassword(): ?string;
    public function verifyPassword(string $password): bool;
}

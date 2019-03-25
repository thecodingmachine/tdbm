<?php


namespace TheCodingMachine\TDBM\Fixtures\Interfaces;


interface TestUserInterface
{
    public function getLogin() : string;
    public function getPassword() : ?string;
}

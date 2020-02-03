<?php

namespace Test\Utils;

use PHPUnit\Framework\TestCase;
use TheCodingMachine\TDBM\Utils\StringUtils;

class StringUtilsTest extends TestCase
{
    public function testGetValidVariableName(): void
    {
        $this->assertEquals('threed_view', StringUtils::getValidVariableName('3d_view'));
        $this->assertEquals('one_thousand_four_hundred_thirty_two_var_1432_test', StringUtils::getValidVariableName('1432_var_1432_test'));
    }
}

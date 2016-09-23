<?php


namespace Mouf\Database\TDBM;


use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\VoidCache;

class OrderByAnalyzerTest extends \PHPUnit_Framework_TestCase
{
    public function testAnalyzeOrderBy()
    {
        $analyzer = new OrderByAnalyzer(new VoidCache(), '');
        $results = $analyzer->analyzeOrderBy('`a`, b desc, rand() DESC, masc, mytable.mycol');

        $this->assertCount(5, $results);
        $this->assertEquals([
            'type' => 'colref',
            'table' => null,
            'column' => 'a',
            'direction' => 'ASC'
        ], $results[0]);
        $this->assertEquals([
            'type' => 'colref',
            'table' => null,
            'column' => 'b',
            'direction' => 'DESC'
        ], $results[1]);
        $this->assertEquals([
            'type' => 'expr',
            'expr' => 'rand()',
            'direction' => 'DESC'
        ], $results[2]);
        $this->assertEquals([
            'type' => 'colref',
            'table' => null,
            'column' => 'masc',
            'direction' => 'ASC'
        ], $results[3]);
        $this->assertEquals([
            'type' => 'colref',
            'table' => 'mytable',
            'column' => 'mycol',
            'direction' => 'ASC'
        ], $results[4]);
    }

    public function testExprWithAsc()
    {
        $analyzer = new OrderByAnalyzer(new VoidCache(), '');
        $results = $analyzer->analyzeOrderBy('foodesc + barasc');

        $this->assertCount(1, $results);
        $this->assertEquals([
            'type' => 'expr',
            'expr' => 'foodesc + barasc',
            'direction' => 'ASC'
        ], $results[0]);
    }

    public function testCache()
    {
        $analyzer = new OrderByAnalyzer(new ArrayCache(), '');
        $results = $analyzer->analyzeOrderBy('foo');
        $results2 = $analyzer->analyzeOrderBy('foo');
        // For code coverage purpose
    }

}

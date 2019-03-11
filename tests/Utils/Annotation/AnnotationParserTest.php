<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils\Annotation;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use TheCodingMachine\TDBM\TDBMException;

class AnnotationParserTest extends TestCase
{
    public function testParse()
    {
        $parser = new AnnotationParser([
            'UUID' => UUID::class,
            'Autoincrement' => Autoincrement::class
        ]);
        $column = new Column('foo', Type::getType(Type::STRING), ['comment'=>'@UUID']);
        $table = new Table('bar');
        $annotations = $parser->getColumnAnnotations($column, $table);

        $annotation = $annotations->findAnnotation(UUID::class);
        $this->assertInstanceOf(UUID::class, $annotation);

        $annotationsArray = $annotations->getAnnotations();
        $this->assertCount(1, $annotationsArray);
        $this->assertSame($annotation, $annotationsArray[0]);

        $annotation = $annotations->findAnnotation('not_exist');
        $this->assertNull($annotation);
    }

    public function testParseMultiLine()
    {
        $parser = new AnnotationParser([
            'UUID' => UUID::class,
            'Autoincrement' => Autoincrement::class
        ]);
        $column = new Column('foo', Type::getType(Type::STRING), ['comment'=>"\n@UUID"]);
        $table = new Table('bar');
        $annotations = $parser->getColumnAnnotations($column, $table);

        $annotation = $annotations->findAnnotation(UUID::class);
        $this->assertInstanceOf(UUID::class, $annotation);
    }

    public function testException()
    {
        $parser = new AnnotationParser([
            'UUID' => UUID::class,
            'Autoincrement' => Autoincrement::class
        ]);
        $table = new Table('bar', [], [], [], 0, ['comment'=>"@UUID\n@UUID"]);
        $annotations = $parser->getTableAnnotations($table);

        $this->expectException(TDBMException::class);
        $annotations->findAnnotation(UUID::class);
    }

    public function testParseParameters()
    {
        $parser = new AnnotationParser([
            'UUID' => UUID::class,
            'Autoincrement' => Autoincrement::class
        ]);
        $table = new Table('bar', [], [], [], 0, ['comment'=>'@UUID("v4")']);
        $annotations = $parser->getTableAnnotations($table);

        $annotation = $annotations->findAnnotation(UUID::class);
        $this->assertSame('v4', $annotation->value);
    }

    public function testParseOldUUID()
    {
        $parser = new AnnotationParser([
            'UUID' => UUID::class,
        ]);
        // First generation UUID did not use the Doctrine syntax.
        $table = new Table('bar', [], [], [], 0, ['comment'=>'@UUID v4']);
        $annotations = $parser->getTableAnnotations($table);

        $annotation = $annotations->findAnnotation(UUID::class);
        $this->assertSame('v4', $annotation->value);
    }
}

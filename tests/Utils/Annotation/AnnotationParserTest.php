<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils\Annotation;

use Doctrine\Common\Annotations\AnnotationException;
use TheCodingMachine\TDBM\TDBMException;

class AnnotationParserTest extends \PHPUnit_Framework_TestCase
{
    public function testParse()
    {
        $parser = new AnnotationParser([
            'UUID' => UUID::class,
            'Autoincrement' => Autoincrement::class
        ]);
        $annotations = $parser->parse('@UUID', '');

        $annotation = $annotations->findAnnotation(UUID::class);
        $this->assertInstanceOf(UUID::class, $annotation);

        $annotationsArray = $annotations->getAnnotations();
        $this->assertCount(1, $annotationsArray);
        $this->assertSame($annotation, $annotationsArray[0]);

        $annotation = $annotations->findAnnotation('not_exist');
        $this->assertNull($annotation);
    }

    public function testException()
    {
        $parser = new AnnotationParser([
            'UUID' => UUID::class,
            'Autoincrement' => Autoincrement::class
        ]);
        $annotations = $parser->parse("@UUID\n@UUID", '');

        $this->expectException(TDBMException::class);
        $annotations->findAnnotation(UUID::class);
    }

    public function testParseParameters()
    {
        $parser = new AnnotationParser([
            'UUID' => UUID::class,
            'Autoincrement' => Autoincrement::class
        ]);
        $annotations = $parser->parse('@UUID("v4")', '');

        $annotation = $annotations->findAnnotation(UUID::class);
        $this->assertSame('v4', $annotation->value);
    }

    public function testParseOldUUID()
    {
        $parser = new AnnotationParser([
            'UUID' => UUID::class,
        ]);
        // First generation UUID did not use the Doctrine syntax.
        $annotations = $parser->parse('@UUID v4', '');

        $annotation = $annotations->findAnnotation(UUID::class);
        $this->assertSame('v4', $annotation->value);
    }
}

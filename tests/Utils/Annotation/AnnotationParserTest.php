<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM\Utils\Annotation;

use TheCodingMachine\TDBM\TDBMException;

class AnnotationParserTest extends \PHPUnit_Framework_TestCase
{
    public function testParse()
    {
        $parser = new AnnotationParser();
        $annotations = $parser->parse('@UUID');

        $annotation = $annotations->findAnnotation('UUID');
        $this->assertSame('UUID', $annotation->getAnnotationType());

        $annotationsArray = $annotations->getAnnotations();
        $this->assertCount(1, $annotationsArray);
        $this->assertSame($annotation, $annotationsArray[0]);


        $annotation = $annotations->findAnnotation('not_exist');
        $this->assertNull($annotation);
    }

    public function testException()
    {
        $parser = new AnnotationParser();
        $annotations = $parser->parse("@UUID\n@UUID");

        $this->expectException(TDBMException::class);
        $annotations->findAnnotation('UUID');
    }

    public function testParseComments()
    {
        $parser = new AnnotationParser();
        $annotations = $parser->parse('@Name foobar');

        $annotation = $annotations->findAnnotation('Name');
        $this->assertSame('foobar', $annotation->getAnnotationComment());
    }
}

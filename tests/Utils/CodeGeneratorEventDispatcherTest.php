<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Index;
use PHPUnit\Framework\TestCase;
use TheCodingMachine\TDBM\ConfigurationInterface;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\FileGenerator;
use Laminas\Code\Generator\MethodGenerator;

class CodeGeneratorEventDispatcherTest extends TestCase
{
    /**
     * @var CodeGeneratorEventDispatcher
     */
    private $dispatcher;
    private $method1;
    private $method2;
    private $method3;
    private $method4;
    private $method5;
    private $beanDescriptor;
    private $configuration;
    private $class;
    private $file;
    private $pivotTableMethodsDescriptor;
    private $beanPropertyDescriptor;
    private $nullDispatcher;

    public function setUp(): void
    {
        $this->dispatcher = new CodeGeneratorEventDispatcher([new BaseCodeGeneratorListener()]);
        $this->nullDispatcher = new CodeGeneratorEventDispatcher([new class implements CodeGeneratorListenerInterface {
            public function onBaseBeanGenerated(FileGenerator $fileGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration): ?FileGenerator
            {
                return null;
            }

            public function onBaseBeanConstructorGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseBeanPropertyGenerated(?MethodGenerator $getter, ?MethodGenerator $setter, AbstractBeanPropertyDescriptor $propertyDescriptor, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): array
            {
                return [null, null];
            }

            public function onBaseBeanOneToManyGenerated(MethodGenerator $getter, DirectForeignKeyMethodDescriptor $directForeignKeyMethodDescriptor, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseBeanManyToManyGenerated(?MethodGenerator $getter, ?MethodGenerator $adder, ?MethodGenerator $remover, ?MethodGenerator $hasser, ?MethodGenerator $setter, PivotTableMethodsDescriptor $pivotTableMethodsDescriptor, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): array
            {
                return [null, null, null, null, null];
            }

            public function onBaseBeanJsonSerializeGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseBeanCloneGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseDaoGenerated(FileGenerator $fileGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration): ?FileGenerator
            {
                return null;
            }

            public function onBaseDaoConstructorGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseDaoSaveGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseDaoFindAllGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseDaoGetByIdGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseDaoDeleteGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseDaoFindGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseDaoFindFromSqlGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseDaoFindFromRawSqlGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseDaoFindOneGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseDaoFindOneFromSqlGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseDaoSetDefaultSortGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseDaoFindByIndexGenerated(MethodGenerator $methodGenerator, Index $index, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            public function onBaseResultIteratorGenerated(FileGenerator $fileGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration): ?FileGenerator
            {
                return null;
            }

            /**
             * @param BeanDescriptor[] $beanDescriptors
             */
            public function onDaoFactoryGenerated(FileGenerator $fileGenerator, array $beanDescriptors, ConfigurationInterface $configuration): ?FileGenerator
            {
                return null;
            }

            /**
             * @param BeanDescriptor[] $beanDescriptors
             */
            public function onDaoFactoryConstructorGenerated(MethodGenerator $methodGenerator, array $beanDescriptors, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            /**
             * @param BeanDescriptor[] $beanDescriptor
             */
            public function onDaoFactoryGetterGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }

            /**
             * @param BeanDescriptor[] $beanDescriptor
             */
            public function onDaoFactorySetterGenerated(MethodGenerator $methodGenerator, BeanDescriptor $beanDescriptor, ConfigurationInterface $configuration, ClassGenerator $classGenerator): ?MethodGenerator
            {
                return null;
            }
        }]);
        $this->method1 = new MethodGenerator();
        $this->method2 = new MethodGenerator();
        $this->method3 = new MethodGenerator();
        $this->method4 = new MethodGenerator();
        $this->method5 = new MethodGenerator();
        $this->beanDescriptor = $this->getMockBuilder(BeanDescriptor::class)->disableOriginalConstructor()->getMock();
        $this->configuration = $this->getMockBuilder(ConfigurationInterface::class)->disableOriginalConstructor()->getMock();
        $this->pivotTableMethodsDescriptor = $this->getMockBuilder(PivotTableMethodsDescriptor::class)->disableOriginalConstructor()->getMock();
        $this->beanPropertyDescriptor = $this->getMockBuilder(ScalarBeanPropertyDescriptor::class)->disableOriginalConstructor()->getMock();
        $this->class = new ClassGenerator();
        $this->file = new FileGenerator();
    }

    public function testOnBaseDaoFindAllGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onBaseDaoFindAllGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseDaoFindAllGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseBeanJsonSerializeGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onBaseBeanJsonSerializeGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseBeanJsonSerializeGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseDaoConstructorGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onBaseDaoConstructorGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseDaoConstructorGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseDaoFindOneFromSqlGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onBaseDaoFindOneFromSqlGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseDaoFindOneFromSqlGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseDaoGenerated(): void
    {
        $this->assertSame($this->file, $this->dispatcher->onBaseDaoGenerated($this->file, $this->beanDescriptor, $this->configuration));
        $this->assertSame(null, $this->nullDispatcher->onBaseDaoGenerated($this->file, $this->beanDescriptor, $this->configuration));
    }

    public function testOnBaseDaoSetDefaultSortGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onBaseDaoSetDefaultSortGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseDaoSetDefaultSortGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseDaoSaveGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onBaseDaoSaveGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseDaoSaveGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseDaoGetByIdGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onBaseDaoGetByIdGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseDaoGetByIdGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseBeanCloneGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onBaseBeanCloneGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseBeanCloneGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseDaoFindOneGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onBaseDaoFindOneGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseDaoFindOneGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseBeanPropertyGenerated(): void
    {
        $this->assertSame([$this->method1, $this->method2], $this->dispatcher->onBaseBeanPropertyGenerated($this->method1, $this->method2, $this->beanPropertyDescriptor, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame([null, null], $this->nullDispatcher->onBaseBeanPropertyGenerated(null, null, $this->beanPropertyDescriptor, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseDaoFindGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onBaseDaoFindGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseDaoFindGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseBeanOneToManyGenerated(): void
    {
        $directForeignKeyMethodDescriptor = $this->getMockBuilder(DirectForeignKeyMethodDescriptor::class)->disableOriginalConstructor()->getMock();
        $this->assertSame($this->method1, $this->dispatcher->onBaseBeanOneToManyGenerated($this->method1, $directForeignKeyMethodDescriptor, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseBeanOneToManyGenerated($this->method1, $directForeignKeyMethodDescriptor, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseDaoFindFromSqlGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onBaseDaoFindFromSqlGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseDaoFindFromSqlGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseBeanConstructorGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onBaseBeanConstructorGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseBeanConstructorGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseDaoFindFromRawSqlGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onBaseDaoFindFromRawSqlGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseDaoFindFromRawSqlGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseDaoDeleteGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onBaseDaoDeleteGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseDaoDeleteGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseBeanGenerated(): void
    {
        $this->assertSame($this->file, $this->dispatcher->onBaseBeanGenerated($this->file, $this->beanDescriptor, $this->configuration));
        $this->assertSame(null, $this->nullDispatcher->onBaseBeanGenerated($this->file, $this->beanDescriptor, $this->configuration));
    }

    public function testOnBaseBeanManyToManyGenerated(): void
    {
        $this->assertSame([$this->method1, $this->method2, $this->method3, $this->method4, $this->method5], $this->dispatcher->onBaseBeanManyToManyGenerated($this->method1, $this->method2, $this->method3, $this->method4, $this->method5, $this->pivotTableMethodsDescriptor, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame([null, null, null, null, null], $this->nullDispatcher->onBaseBeanManyToManyGenerated(null, null, null, null, null, $this->pivotTableMethodsDescriptor, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseDaoFindByIndexGenerated(): void
    {
        $index = $this->getMockBuilder(Index::class)->disableOriginalConstructor()->getMock();
        $this->assertSame($this->method1, $this->dispatcher->onBaseDaoFindByIndexGenerated($this->method1, $index, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onBaseDaoFindByIndexGenerated($this->method1, $index, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnBaseResultIteratorGenerated(): void
    {
        $this->assertSame($this->file, $this->dispatcher->onBaseResultIteratorGenerated($this->file, $this->beanDescriptor, $this->configuration));
        $this->assertSame(null, $this->nullDispatcher->onBaseResultIteratorGenerated($this->file, $this->beanDescriptor, $this->configuration));
    }

    public function testOnDaoFactoryGenerated(): void
    {
        $this->assertSame($this->file, $this->dispatcher->onDaoFactoryGenerated($this->file, [], $this->configuration));
        $this->assertSame(null, $this->nullDispatcher->onDaoFactoryGenerated($this->file, [], $this->configuration));
    }

    public function testOnDaoFactoryConstructorGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onDaoFactoryConstructorGenerated($this->method1, [], $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onDaoFactoryConstructorGenerated($this->method1, [], $this->configuration, $this->class));
    }

    public function testOnDaoFactoryGetterGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onDaoFactoryGetterGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onDaoFactoryGetterGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }

    public function testOnDaoFactorySetterGenerated(): void
    {
        $this->assertSame($this->method1, $this->dispatcher->onDaoFactorySetterGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
        $this->assertSame(null, $this->nullDispatcher->onDaoFactorySetterGenerated($this->method1, $this->beanDescriptor, $this->configuration, $this->class));
    }
}

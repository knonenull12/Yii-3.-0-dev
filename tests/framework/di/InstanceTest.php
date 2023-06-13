<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yiiunit\framework\di;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\caching\CacheInterface;
use yii\caching\DbCache;
use yii\db\Connection;
use yii\di\Container;
use yii\di\Instance;
use yiiunit\TestCase;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 * @group di
 */
class InstanceTest extends TestCase
{
    protected function tearDown()
    {
        parent::tearDown();
        Yii::$container = new Container();
    }

    public function testOf()
    {
        $container = new Container();
        $className = Component::class;
        $instance = Instance::of($className);

        $this->assertInstanceOf(Instance::class, $instance);
        $this->assertInstanceOf(Component::class, $instance->get($container));
        $this->assertInstanceOf(Component::class, Instance::ensure($instance, $className, $container));
        $this->assertNotSame($instance->get($container), Instance::ensure($instance, $className, $container));
    }

    public function testEnsure()
    {
        $container = new Container();
        $container->set('db', [
            '__class' => Connection::class,
            'dsn' => 'test',
        ]);

        $this->assertInstanceOf(Connection::class, Instance::ensure('db', Connection::class, $container));
        $this->assertInstanceOf(Connection::class, Instance::ensure(new Connection(), Connection::class, $container));
        $this->assertInstanceOf(Connection::class, Instance::ensure(['__class' => Connection::class, 'dsn' => 'test'], Connection::class, $container));
    }

    /**
     * ensure an InvalidConfigException is thrown when a component does not exist.
     */
    public function testEnsure_NonExistingComponentException()
    {
        $container = new Container();
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessageRegExp('/^Failed to instantiate component or class/i');
        Instance::ensure('cache', CacheInterface::class, $container);
    }

    /**
     * ensure an InvalidConfigException is thrown when a class does not exist.
     */
    public function testEnsure_NonExistingClassException()
    {
        $container = new Container();
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessageRegExp('/^Failed to instantiate component or class/i');
        Instance::ensure('yii\cache\DoesNotExist', CacheInterface::class, $container);
    }

    public function testEnsure_WithoutType()
    {
        $container = new Container();
        $container->set('db', [
            '__class' => Connection::class,
            'dsn' => 'test',
        ]);

        $this->assertInstanceOf(Connection::class, Instance::ensure('db', null, $container));
        $this->assertInstanceOf(Connection::class, Instance::ensure(new Connection, null, $container));
        $this->assertInstanceOf(Connection::class, Instance::ensure(['__class' => Connection::class, 'dsn' => 'test'], null, $container));
    }

    public function testEnsure_MinimalSettings()
    {
        Yii::$container->set('db', [
            '__class' => Connection::class,
            'dsn' => 'test',
        ]);

        $this->assertInstanceOf(Connection::class, Instance::ensure('db'));
        $this->assertInstanceOf(Connection::class, Instance::ensure(new Connection()));
        $this->assertInstanceOf(Connection::class, Instance::ensure(['__class' => Connection::class, 'dsn' => 'test']));
        Yii::$container = new Container();
    }

    public function testExceptionRefersTo()
    {
        $container = new Container();
        $container->set('db', [
            '__class' => Connection::class,
            'dsn' => 'test',
        ]);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('"db" refers to a yii\db\Connection component. yii\base\Widget is expected.');

        Instance::ensure('db', Widget::class, $container);
    }

    public function testExceptionInvalidDataType()
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Invalid data type: yii\db\Connection. yii\base\Widget is expected.');
        Instance::ensure(new Connection(), Widget::class);
    }

    public function testExceptionComponentIsNotSpecified()
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('The required component is not specified.');
        Instance::ensure('');
    }

    public function testGet()
    {
        $this->mockApplication([
            'components' => [
                'db' => [
                    '__class' => Connection::class,
                    'dsn' => 'test',
                ],
            ],
        ]);

        $container = Instance::of('db');

        $this->assertInstanceOf(Connection::class, $container->get());

        $this->destroyApplication();
    }

    /**
     * This tests the usage example given in yii\di\Instance class PHPDoc.
     */
    public function testLazyInitializationExample()
    {
        Yii::$container = new Container();
        Yii::$container->set('cache', [
            '__class' => DbCache::class,
            'db' => Instance::of('db'),
        ]);
        Yii::$container->set('db', [
            '__class' => Connection::class,
            'dsn' => 'sqlite:path/to/file.db',
        ]);

        $this->assertInstanceOf(DbCache::class, $cache = Yii::$container->get('cache'));
        $this->assertInstanceOf(Connection::class, $db = $cache->db);
        $this->assertEquals('sqlite:path/to/file.db', $db->dsn);
    }

    public function testRestoreAfterVarExport()
    {
        $instance = Instance::of('something');
        $export = var_export($instance, true);

        $this->assertRegExp(<<<'PHP'
@yii\\di\\Instance::__set_state\(array\(\s+'id' => 'something',\s+\)\)@
PHP
        , $export);

        $this->assertEquals($instance, Instance::__set_state([
            'id' => 'something',
        ]));
    }

    public function testRestoreAfterVarExportRequiresId()
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Failed to instantiate class "Instance". Required parameter "id" is missing');

        Instance::__set_state([]);
    }

    public function testExceptionInvalidDataTypeInArray()
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Invalid data type: yii\db\Connection. yii\base\Widget is expected.');
        Instance::ensure([
            '__class' => Connection::class,
        ], Widget::class);
    }
}
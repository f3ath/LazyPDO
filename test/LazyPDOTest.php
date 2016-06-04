<?php
namespace F3\LazyPDO;

use PDO;
use ReflectionClass;
use RuntimeException;

/**
 * LazyPDOTest
 *
 * @package   LazyPDO
 * @version   $id$
 * @copyright Alexey Karapetov
 * @author    Alexey Karapetov <karapetov@gmail.com>
 * @license   http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */
class LazyPDOTest extends \PHPUnit_Framework_TestCase
{
    private $pdo;

    private $lazy;

    protected function setUp()
    {
        $this->pdo = $this->getMockBuilder('stdClass')
            ->setMethods(array(
                'setAttribute',
                'inTransaction',
                'beginTransaction',
                'getAttribute',
                'commit',
                'rollBack',
                'errorCode',
                'errorInfo',
                'exec',
                'prepare',
                'quote',
                'query',
                'lastInsertId',
            ))
            ->getMock();
        $this->lazy = $this->getMockBuilder('F3\\LazyPDO\\LazyPDO')
            ->setMethods(array('getPDO'))
            ->setConstructorArgs(array('dsn', 'user', 'pass', array('key' => 'val')))
            ->getMock();
        $this->lazy->expects($this->any())
            ->method('getPDO')
            ->will($this->returnValue($this->pdo));
    }

    public function booleanValuesProvider()
    {
        return array(
            array(true),
            array(false),
        );
    }

    public function intValuesProvider()
    {
        return array(
            array(0),
            array(-42),
            array(42),
        );
    }

    public function nullValuesProvider()
    {
        return array(
            array(null, null),
        );
    }

    public function intOrNullValuesProvider()
    {
        return array_merge(
            $this->intValuesProvider(),
            $this->nullValuesProvider()
        );
    }

    public function testSetAttributeShouldBePassedToRealPDOAndGatheredInOptionsIfOk()
    {
        $this->pdo->expects($this->once())
            ->method('setAttribute')
            ->with('my_attr', 'my_value')
            ->will($this->returnValue(true));
        $this->assertTrue($this->lazy->setAttribute('my_attr', 'my_value'));
        $props = unserialize($this->lazy->serialize());
        $this->assertEquals('my_value', $props[3]['my_attr']);
    }

    public function testSetAttributeShouldNotBeGatheredOnFail()
    {
        $this->pdo->expects($this->once())
            ->method('setAttribute')
            ->with('my_attr', 'my_value')
            ->will($this->returnValue(false));
        $this->assertFalse($this->lazy->setAttribute('my_attr', 'my_value'));
        $props = unserialize($this->lazy->serialize());
        $this->assertArrayNotHasKey('my_attr', $props[3]);
    }

    public function testGetPDO()
    {
        if (false === extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not loaded');
        }
        $class = new ReflectionClass('F3\\LazyPDO\\LazyPDO');
        $method = $class->getMethod('getPDO');
        $method->setAccessible(true);

        $dsn = 'sqlite::memory:';
        $lazy = new LazyPDO($dsn, 'user', 'pass', array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        $pdo = $method->invoke($lazy);
        $this->assertInstanceOf('PDO', $pdo);
        $this->assertThat($pdo, $this->identicalTo($method->invoke($lazy)));
        $this->assertEquals(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Can not serialize in transaction
     */
    public function testSerializeShouldThrowExceptionInTransaction()
    {
        $lazy = $this->getMockBuilder('F3\\LazyPDO\\LazyPDO')
            ->setConstructorArgs(array('dsn', 'user', 'pass', array()))
            ->setMethods(array('inTransaction'))
            ->getMock();
        $lazy->expects($this->once())
            ->method('inTransaction')
            ->will($this->returnValue(true));
        $lazy->serialize();
    }

    public function testSerialize()
    {
        $dsn = 'sqlite::memory:';
        $lazy = new LazyPDO($dsn, 'user', 'pass');
        $serialized = serialize($lazy);
        $this->assertEquals('C:18:"F3\\LazyPDO\\LazyPDO":73:{a:4:{i:0;s:' . mb_strlen($dsn) . ':"' . $dsn . '";i:1;s:4:"user";i:2;s:4:"pass";i:3;a:0:{}}}', $serialized);
        $this->assertEquals($lazy, unserialize($serialized));
    }

    public function testSerializationShouldPreserveAttributes()
    {
        if (false === extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not loaded');
        }
        $dsn = 'sqlite::memory:';
        $lazy = new LazyPDO($dsn, 'user', 'pass', array());
        $this->assertNotEquals(PDO::ERRMODE_EXCEPTION, $lazy->getAttribute(PDO::ATTR_ERRMODE));
        $lazy->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->assertEquals(PDO::ERRMODE_EXCEPTION, $lazy->getAttribute(PDO::ATTR_ERRMODE));
        $lazy = unserialize(serialize($lazy));
        $this->assertEquals(PDO::ERRMODE_EXCEPTION, $lazy->getAttribute(PDO::ATTR_ERRMODE));
    }
}

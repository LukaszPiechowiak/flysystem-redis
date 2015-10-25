<?php

use Predis\Client;
use League\Flysystem\Config;
use Danhunsaker\Flysystem\Redis\RedisAdapter;

/**
 * @coversDefaultClass \Danhunsaker\Flysystem\Redis\RedisAdapter
 */
class RedisAdapterTests extends \PHPUnit_Framework_TestCase
{
    /**
     * The Redis adapter.
     *
     * @var \Danhunsaker\Flysystem\Redis\RedisAdapter;
     */
    protected $adapter;

    public function setUp()
    {
        $this->adapter = new RedisAdapter(new Client(), 'flysystem-test:');
        $this->adapter->write('file.txt', 'contents', new Config());
    }

    public function tearDown()
    {
        $this->adapter->deleteDir('');
    }
    
    /**
     * @covers ::write
     */
    public function testWrite()
    {
        $utf_8_test = file_get_contents(__DIR__ . '/utf-8-test.txt');
        $quickbrown = file_get_contents(__DIR__ . '/quickbrown.txt');
        
        $result = $this->adapter->write('new_file.txt', 'new contents', new Config());
        $this->assertSame('new contents', $result['contents']);
        $result = $this->adapter->write('utf-8-test.txt', $utf_8_test, new Config());
        $this->assertSame($utf_8_test, mb_convert_encoding($result['contents'], mb_internal_encoding(), 'UTF-8'));
        $this->assertSame($utf_8_test, mb_convert_encoding($this->adapter->read('utf-8-test.txt')['contents'], mb_internal_encoding(), 'UTF-8'));
        $result = $this->adapter->write('quickbrown.txt', $quickbrown, new Config());
        $this->assertSame($quickbrown, $result['contents']);
        $this->assertSame($quickbrown, $this->adapter->read('quickbrown.txt')['contents']);
        $this->assertFalse($this->adapter->write('file.txt/new_file.txt', 'contents', new Config()));
    }

    /**
     * @covers ::copy
     */
    public function testCopy()
    {
        $this->assertTrue($this->adapter->copy('file.txt', 'dir/new_file.txt'));
        $this->assertSame('contents', $this->adapter->read('dir/new_file.txt', new Config())['contents']);

        $this->assertFalse($this->adapter->copy('file.txt', 'dir/new_file.txt/other.txt'));
    }

    /**
     * @covers ::createDir
     */
    public function testCreateDir()
    {
        $result = $this->adapter->createDir('dir/subdir', new Config());
        $this->assertSame(4, count($result));
        $this->assertSame('dir/subdir', $result['path']);
        $this->assertSame('dir', $result['type']);
        $this->assertSame('public', $result['visibility']);
        $this->assertTrue(is_int($result['timestamp']));
        $this->assertTrue($this->adapter->has('dir'));
        $this->assertTrue($this->adapter->has('dir/subdir'));

        $result = $this->adapter->createDir('dir', new Config());
        $this->assertSame(4, count($result));
        $this->assertSame('dir', $result['path']);
        $this->assertSame('dir', $result['type']);
        $this->assertSame('public', $result['visibility']);
        $this->assertTrue(is_int($result['timestamp']));

        $this->assertFalse($this->adapter->createDir('file.txt', new Config()));
        $this->assertFalse($this->adapter->createDir('file.txt/dir', new Config()));
    }

    /**
     * @covers ::delete
     * @covers ::hasFile
     */
    public function testDelete()
    {
        $this->assertTrue($this->adapter->delete('file.txt'));
        $this->assertFalse($this->adapter->has('file.txt'));
        $this->assertFalse($this->adapter->delete('file.txt'));
    }

    /**
     * @covers ::deleteDir
     * @covers ::hasDirectory
     */
    public function testDeleteDir()
    {
        $this->adapter->createDir('dir/subdir', new Config());
        $this->assertTrue($this->adapter->deleteDir('dir'));
        $this->assertFalse($this->adapter->has('dir/subdir'));
        $this->assertFalse($this->adapter->has('dir'));

        $this->assertFalse($this->adapter->deleteDir('dir'));
    }

    /**
     * @covers ::getMetaData
     */
    public function testGetMetadata()
    {
        $meta = $this->adapter->getMetadata('file.txt');

        $this->assertSame(6, count($meta));
        $this->assertSame('file.txt', $meta['path']);
        $this->assertSame('file', $meta['type']);
        $this->assertSame(8, $meta['size']);
        $this->assertSame('public', $meta['visibility']);
        $this->assertTrue(is_int($meta['timestamp']));
        $this->assertSame('text/plain', $meta['mimetype']);
    }

    /**
     * @covers ::getMimetype
     */
    public function testGetMimetype()
    {
        $meta = $this->adapter->getMimetype('file.txt');
        $this->assertSame('text/plain', $meta['mimetype']);
    }

    /**
     * @covers ::getSize
     */
    public function testGetSize()
    {
        $meta = $this->adapter->getSize('file.txt');
        $this->assertSame(8, $meta['size']);
    }

    /**
     * @covers ::getTimestamp
     */
    public function testGetTimestamp()
    {
        $meta = $this->adapter->getTimestamp('file.txt');
        $this->assertTrue(is_int($meta['timestamp']));
    }

    /**
     * @covers ::getVisibility
     */
    public function testGetVisibility()
    {
        $this->assertSame('public', $this->adapter->getVisibility('file.txt')['visibility']);
    }

    /**
     * @covers ::has
     */
    public function testHas()
    {
        $this->assertTrue($this->adapter->has('file.txt'));
        $this->assertFalse($this->adapter->has('no_file.txt'));
    }

    /**
     * @covers ::listContents
     * @covers ::doListContents
     * @covers ::pathIsInDirectory
     */
    public function testListContents()
    {
        $result = $this->adapter->listContents('');
        $this->assertSame(1, count($result));
        $this->assertSame('file.txt', $result[0]['path']);

        $this->adapter->write('dir/file.txt', 'contents', new Config());
        $this->adapter->write('dir/subdir/file.txt', 'contents', new Config());

        $result = $this->adapter->listContents('', true);
        $this->assertSame(5, count($result));
        $this->assertSame('dir', $result[0]['path']);
        $this->assertSame('dir/subdir', $result[1]['path']);
        $this->assertSame('dir/subdir/file.txt', $result[2]['path']);
        $this->assertSame('dir/file.txt', $result[3]['path']);
        $this->assertSame('file.txt', $result[4]['path']);

        $result = $this->adapter->listContents('');
        $this->assertSame(2, count($result));
        $this->assertSame('dir', $result[0]['path']);
        $this->assertSame('file.txt', $result[1]['path']);

        $result = $this->adapter->listContents('dir', true);
        $this->assertSame(3, count($result));
        $this->assertSame('dir/subdir', $result[0]['path']);
        $this->assertSame('dir/subdir/file.txt', $result[1]['path']);
        $this->assertSame('dir/file.txt', $result[2]['path']);

        $result = $this->adapter->listContents('dir');
        $this->assertSame(2, count($result));
        $this->assertSame('dir/subdir', $result[0]['path']);
        $this->assertSame('dir/file.txt', $result[1]['path']);

        $this->assertSame([], $this->adapter->listContents('no_dir'));
    }

    /**
     * @covers ::read
     */
    public function testRead()
    {
        $this->assertSame('contents', $this->adapter->read('file.txt')['contents']);
        $this->assertSame('file.txt', $this->adapter->read('file.txt')['path']);
    }

    /**
     * @covers ::readStream
     */
    public function testReadStream()
    {
        $result = $this->adapter->readStream('file.txt');

        $this->assertSame('contents', stream_get_contents($result['stream']));
        $this->assertSame('file.txt', $result['path']);
    }

    /**
     * @covers ::rename
     */
    public function testRename()
    {
        $this->assertTrue($this->adapter->rename('file.txt', 'dir/subdir/file.txt'));
        $this->assertFalse($this->adapter->has('file.txt'));
        $this->assertTrue($this->adapter->has('dir'));
        $this->assertTrue($this->adapter->has('dir/subdir'));
        $this->assertTrue($this->adapter->has('dir/subdir/file.txt'));

        $this->assertFalse($this->adapter->rename('dir/subdir/file.txt', 'dir/subdir/file.txt/new_file.txt'));
    }

    /**
     * @covers ::setVisibility
     */
    public function testSetVisibility()
    {
        $result = $this->adapter->setVisibility('file.txt', 'private');
        $this->assertSame('private', $result['visibility']);
        $this->assertSame('private', $this->adapter->getVisibility('file.txt')['visibility']);

        $this->assertFalse($this->adapter->setVisibility('no_file.txt', 'public'));
    }

    /**
     * @covers ::update
     */
    public function testUpdate()
    {
        $result = $this->adapter->update('file.txt', 'new contents', new Config(['visibility' => 'private']));
        $this->assertSame('new contents', $result['contents']);
        $this->assertSame('file.txt', $result['path']);
        $this->assertSame('private', $result['visibility']);
        $this->assertSame('new contents', $this->adapter->read('file.txt', new Config())['contents']);

        $this->assertFalse($this->adapter->update('new_file.txt', 'contents', new Config()));
    }
}

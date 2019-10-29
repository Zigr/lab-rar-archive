<?php

use PHPUnit\Framework\TestCase;
use zigr\Flysystem\RarArchive\RarArchiveAdapter as FreeRar;

/**
 *
 * @author ZIgr <zaporozhets.igor at gmail.coml>
 * @date Oct 21, 2019
 * @encoding UTF-8
 *  
 */
class FreeRarAdapterTest extends TestCase
{

    protected $filemame = 'developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.rar';

    public function rarProvider()
    {
        $location = __DIR__ . '/data/' . $this->filemame;
        return [
            [new FreeRar($location, null, null)]
        ];
    }

    /**
     * @dataProvider rarProvider
     */
    public function testAdapterInstance(FreeRar $rarAdapter)
    {
        $this->assertInstanceOf('zigr\Flysystem\RarArchive\RarArchiveAdapter', $rarAdapter);
    }

    /**
     * @dataProvider rarProvider
     */
    public function testGetArchive(FreeRar $rar)
    {
        $this->assertInstanceOf('RarArchive', $rar->getArchive());
    }

    /**
     * @covers FreeRarArchiveAdapter::listContents
     * @dataProvider rarProvider
     */
    public function testListContents(FreeRar $rar)
    {
        $list = $rar->listContents();
        $this->assertCount(19, $list);
        
        $list = $rar->listContents('developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.rar', false);
        $this->assertCount(19, $list);
        
        $list = $rar->listContents('developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.rar', true);
        $this->assertCount(19, $list);
        
        $list = $rar->listContents('developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.rar/developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.files/');
        $this->assertCount(13, $list);
        
        $list = $rar->listContents('developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.rar/developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.files/', true);
        $this->assertCount(17, $list);
        
        $list = $rar->listContents('developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.files/Ad.files');
        $this->assertEquals(2, count($list));
        
        $list = $rar->listContents('developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.files/Ad.files/');
        $this->assertEquals(2, count($list));

        $list = $rar->listContents('developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.files/Ad.files/style.css');
        $this->assertEquals(0, count($list));
    }

    /**
     * @covers FreeRarArchiveAdapter::has
     * @dataProvider rarProvider
     */
    public function testEntryExists(FreeRar $rar)
    {
        $entry = 'developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.files';
        $this->assertTrue($rar->has($entry));

        $entry = 'developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.rar/developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.files';
        $this->assertTrue($rar->has($entry));
        
        $entry = 'developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.rar/developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.files/';
        $this->assertTrue($rar->has($entry));
        
        $entry = 'developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.files/Ad.files/style.css';
        $this->assertTrue($rar->has($entry));
    }

    /**
     * @covers FreeRarArchiveAdapter::read
     * @dataProvider rarProvider
     */
    public function testReadEntry(FreeRar $rar)
    {
        $entry = 'developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.files';
        $this->assertFalse($rar->read($entry));

        $entry = 'developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.files/Ad(1).htm';
        $contentsRead = $rar->read($entry);
        $this->assertInternalType('array', $contentsRead);
        $this->assertArrayHasKey('type', $contentsRead);
        $this->assertArrayHasKey('path', $contentsRead);
        $this->assertArrayHasKey('contents', $contentsRead);

        $rar->setPathPrefix(dirname(__FILE__) . '/data');
        $filecontents = file_get_contents($rar->getWrapperString($entry));
        $this->assertSame($filecontents, $contentsRead['contents']);
    }

    /**
     * @covers FreeRarArchiveAdapter::readStream
     * @dataProvider rarProvider
     */
    public function testReadSreamedEntry(FreeRar $rar)
    {
        $entry = 'developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.files/Ad.files';
        $this->assertFalse($rar->readStream($entry));

        $entry = 'developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.files/Ad.files/style.css';
        $contentsRead = $rar->readStream($entry);
        $this->assertInternalType('array', $contentsRead);
        $this->assertArrayHasKey('type', $contentsRead);
        $this->assertArrayHasKey('path', $contentsRead);
        $this->assertArrayHasKey('stream', $contentsRead);
        $this->assertArrayHasKey('wrapper', $contentsRead);

        $this->assertTrue(is_resource($contentsRead['stream']));
    }

    /**
     * @covers FreeRarArchiveAdapter::openArchive
     * @dataProvider rarProvider
     * 
     */
    public function testRarExceptionOnOpen(FreeRar $rar)
    {
        $location = dirname(__FILE__) . "/data/not_existent.rar";
        $rar->setUsingExceptions(true);
        try
        {
            $rar->openArchive($location);
        } catch (\RarException $ex)
        {
            $this->assertTrue($ex instanceof \RarException);
        }
    }

    /**
     * @covers FreeRarArchiveAdapter::openArchive
     * @dataProvider rarProvider
     * 
     */
    public function testRarErrorOnOpen(FreeRar $rar)
    {
        $location = dirname(__FILE__) . "/data/not_existent.rar";
        $rar->setUsingExceptions(false);
        try
        {
            $rar->openArchive($location);
        } catch (\RarException $ex)
        {
            $this->assertTrue($ex instanceof RarException);
        }
    }
}

<?php

use Cms\Classes\CmsObject;
use Cms\Classes\Theme;

class TestCmsObject extends CmsObject
{
    protected $dirName = 'testobjects';

    protected $allowedExtensions = ['htm', 'html'];
}

class TestTemporaryCmsObject extends CmsObject
{
    protected $dirName = 'temporary';
}

class TestPages extends CmsObject
{
    protected $dirName = 'pages';
}

class CmsObjectTest extends TestCase
{
    public function testLoad()
    {
        $theme = Theme::load('test');

        $obj = TestCmsObject::load($theme, 'plain.html');
        $this->assertEquals('<p>This is a test HTML content file.</p>', $obj->getContent());
        $this->assertEquals('plain.html', $obj->getFileName());

        $path = str_replace('/', DIRECTORY_SEPARATOR, $theme->getPath().'/testobjects/plain.html');
        $this->assertEquals($path, $obj->getFilePath());
        $this->assertEquals(filemtime($path), $obj->mtime);
    }

    public function testLoadFromSubdirectory()
    {
        $theme = Theme::load('test');

        $obj = TestCmsObject::load($theme, 'subdir/obj.html');
        $this->assertEquals('<p>This is an object in a subdirectory.</p>', $obj->getContent());
        $this->assertEquals('subdir/obj.html', $obj->getFileName());

        $path = str_replace('/', DIRECTORY_SEPARATOR, $theme->getPath().'/testobjects/subdir/obj.html');
        $this->assertEquals($path, $obj->getFilePath());
        $this->assertEquals(filemtime($path), $obj->mtime);
    }

    public function testValidateLoadInvalidTheme()
    {
        $theme = Theme::load('none');

        $this->assertNull(TestCmsObject::load($theme, 'plain.html'));
    }

    public function testValidateLoadInvalidFile()
    {
        $theme = Theme::load('test');

        $this->assertNull(TestCmsObject::load($theme, 'none'));
    }

    public function testCache()
    {
        $theme = Theme::load('test');
        $themePath = $theme->getPath();

        $filePath = $themePath .= '/temporary/test.htm';
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $this->assertFileNotExists($filePath);

        file_put_contents($filePath, '<p>Test content</p>');

        /*
         * First try - the object should be loaded from the file
         */
        $obj = TestTemporaryCmsObject::loadCached($theme, 'test.htm');
        $this->assertFalse($obj->isLoadedFromCache());
        $this->assertEquals('<p>Test content</p>', $obj->getContent());
        $this->assertEquals('test.htm', $obj->getFileName());
        $this->assertEquals(filemtime($filePath), $obj->mtime);

        /*
         * Second try - the object should be loaded from the cache
         */
        CmsObject::clearInternalCache();

        $obj = TestTemporaryCmsObject::loadCached($theme, 'test.htm');
        $this->assertTrue($obj->isLoadedFromCache());
        $this->assertEquals('<p>Test content</p>', $obj->getContent());
        $this->assertEquals('test.htm', $obj->getFileName());
        $this->assertEquals(filemtime($filePath), $obj->mtime);

        /*
         * Modify the file. The object should be loaded from the disk and re-cached.
         */
        sleep(1); // Sleep a second in order to have the update file modification time
        file_put_contents($filePath, '<p>Updated test content</p>');
        clearstatcache(); // The filemtime() function caches its value within a request, so we should clear its cache.

        CmsObject::clearInternalCache();
        $obj = TestTemporaryCmsObject::loadCached($theme, 'test.htm');
        $this->assertFalse($obj->isLoadedFromCache());
        $this->assertEquals('<p>Updated test content</p>', $obj->getContent());
        $this->assertEquals(filemtime($filePath), $obj->mtime);

        CmsObject::clearInternalCache();
        $obj = TestTemporaryCmsObject::loadCached($theme, 'test.htm');
        $this->assertTrue($obj->isLoadedFromCache());
        $this->assertEquals('<p>Updated test content</p>', $obj->getContent());
        $this->assertEquals(filemtime($filePath), $obj->mtime);

        /*
         * Delete the file. The loadCached() should return null
         */
        @unlink($filePath);
        $this->assertFileNotExists($filePath);

        CmsObject::clearInternalCache();
        $obj = TestTemporaryCmsObject::loadCached($theme, 'test.htm');
        $this->assertNull($obj);
    }

    public function testFillFillable()
    {
        $theme = Theme::load('apitest');

        $testContents = 'mytestcontent';
        $obj = TestCmsObject::inTheme($theme);
        $obj->fill([
            'fileName' => 'mytestobj',
            'content' => $testContents
        ]);

        $this->assertEquals($testContents, $obj->getContent());
        $this->assertEquals('mytestobj.htm', $obj->getFileName());
    }

    public function testFillNotFillable()
    {
        $theme = Theme::load('apitest');

        $testContents = 'mytestcontent';
        $obj = TestCmsObject::inTheme($theme);
        $obj->fill([
            'something' => 'mytestobj',
            'content' => $testContents
        ]);

        $this->assertNull($obj->something);
    }

    public function testFillInvalidFileNameSymbol()
    {
        $this->expectException(\Winter\Storm\Exception\ValidationException::class);
        $this->expectExceptionMessage('Invalid file name');

        $theme = Theme::load('apitest');

        $testContents = 'mytestcontent';
        $obj = TestCmsObject::inTheme($theme);
        $obj->fill([
            'fileName' => '@name'
        ]);
        $obj->save();
    }

    public function testFillInvalidFileNamePath()
    {
        $this->expectException(\Winter\Storm\Exception\ValidationException::class);
        $this->expectExceptionMessage('Invalid file name');

        $theme = Theme::load('apitest');

        $testContents = 'mytestcontent';
        $obj = TestCmsObject::inTheme($theme);
        $obj->fill([
            'fileName' => '../somefile'
        ]);
        $obj->save();
    }

    public function testFillInvalidFileSlash()
    {
        $this->expectException(\Winter\Storm\Exception\ValidationException::class);
        $this->expectExceptionMessage('Invalid file name');

        $theme = Theme::load('apitest');

        $testContents = 'mytestcontent';
        $obj = TestCmsObject::inTheme($theme);
        $obj->fill([
            'fileName' => '/somefile'
        ]);
        $obj->save();
    }

    public function testFillEmptyFileName()
    {
        $this->expectException(\Winter\Storm\Exception\ValidationException::class);
        $this->expectExceptionMessage('The File Name field is required');

        $theme = Theme::load('apitest');

        $testContents = 'mytestcontent';
        $obj = TestCmsObject::inTheme($theme);
        $obj->fill([
            'fileName' => ' '
        ]);
        $obj->save();
    }

    public function testSave()
    {
        $theme = Theme::load('apitest');

        $destFilePath = $theme->getPath().'/testobjects/mytestobj.htm';
        if (file_exists($destFilePath)) {
            unlink($destFilePath);
        }

        $this->assertFileNotExists($destFilePath);

        $testContents = 'mytestcontent';
        $obj = TestCmsObject::inTheme($theme);
        $obj->fill([
            'fileName' => 'mytestobj',
            'content' => $testContents
        ]);
        $obj->save();

        $this->assertFileExists($destFilePath);
        $this->assertEquals($testContents, file_get_contents($destFilePath));
    }

    /**
     * @depends testSave
     */
    public function testRename()
    {
        $theme = Theme::load('apitest');

        $srcFilePath = $theme->getPath().'/testobjects/mytestobj.htm';
        $this->assertFileExists($srcFilePath);

        $destFilePath = $theme->getPath().'/testobjects/anotherobj.htm';
        if (file_exists($destFilePath)) {
            unlink($destFilePath);
        }

        $testContents = 'mytestcontent';
        $obj = TestCmsObject::load($theme, 'mytestobj.htm');
        $this->assertEquals($testContents, $obj->getContent());

        $obj->fill([
            'fileName' => 'anotherobj'
        ]);
        $obj->save();

        $this->assertFileNotExists($srcFilePath);
        $this->assertFileExists($destFilePath);
        $this->assertEquals($testContents, file_get_contents($destFilePath));
    }

    /**
     * @depends testRename
     */
    public function testRenameToExistingFile()
    {
        $this->expectException(\Winter\Storm\Exception\ApplicationException::class);
        $this->expectExceptionMessageMatches('/already\sexists/');

        $theme = Theme::load('apitest');

        $srcFilePath = $theme->getPath().'/testobjects/anotherobj.htm';
        $this->assertFileExists($srcFilePath);

        $destFilePath = $theme->getPath().'/testobjects/existingobj.htm';
        if (!file_exists($destFilePath)) {
            file_put_contents($destFilePath, 'str');
        }
        $this->assertFileExists($destFilePath);

        $obj = TestCmsObject::load($theme, 'anotherobj.htm');
        $obj->fill(['fileName' => 'existingobj']);
        $obj->save();
    }

    /**
     * @depends testRename
     */
    public function testSaveSameName()
    {
        $theme = Theme::load('apitest');

        $filePath = $theme->getPath().'/testobjects/anotherobj.htm';
        $this->assertFileExists($filePath);

        $testContents = 'new content';
        $obj = TestCmsObject::load($theme, 'anotherobj.htm');

        $obj->fill([
            'fileName' => 'anotherobj',
            'content' => $testContents
        ]);
        $obj->save();

        $this->assertFileExists($filePath);
        $this->assertEquals($testContents, file_get_contents($filePath));
    }

    public function testSaveNewDir()
    {
        $theme = Theme::load('apitest');

        $destFilePath = $theme->getPath().'/testobjects/testsubdir/mytestobj.htm';
        if (file_exists($destFilePath)) {
            unlink($destFilePath);
        }

        $destDirPath = dirname($destFilePath);
        if (file_exists($destDirPath) && is_dir($destDirPath)) {
            rmdir($destDirPath);
        }

        $this->assertFileNotExists($destFilePath);
        $this->assertFileNotExists($destDirPath);

        $testContents = 'mytestcontent';
        $obj = TestCmsObject::inTheme($theme);
        $obj->fill([
            'fileName' => 'testsubdir/mytestobj.htm',
            'content' => $testContents
        ]);
        $obj->save();

        $this->assertFileExists($destFilePath);
        $this->assertEquals($testContents, file_get_contents($destFilePath));
    }

    public function testPathResolveOutOfBounds()
    {
        $theme = Theme::load('testpaths');

        $this->assertEquals(2, TestPages::listInTheme($theme, true)->count());

        $link = $theme->getPath() . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'link.htm';
        $target = $theme->getPath() . DIRECTORY_SEPARATOR . 'test.htm';

        file_put_contents($target, implode(PHP_EOL, [
            'url = "/test-page"',
            '==',
            '<h1>This page is test</h1>'
        ]));

        symlink($target, $link);

        $this->expectException(Winter\Storm\Halcyon\Exception\InvalidFileNameException::class);

        try {
            TestPages::listInTheme($theme, true);
        } finally {
            unlink($link);
            unlink($target);
        }
    }

    public function testPathResolveInBounds()
    {
        $theme = Theme::load('testpaths');

        $this->assertEquals(2, TestPages::listInTheme($theme, true)->count());

        $link = $theme->getPath() .
            DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . 'link.htm';

        $target = $theme->getPath() . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'test.htm';

        file_put_contents($target, implode(PHP_EOL, [
            'url = "/test-page"',
            '==',
            '<h1>This page is test</h1>'
        ]));

        symlink($target, $link);

        $this->assertEquals(4, TestPages::listInTheme($theme, true)->count());

        unlink($link);
        unlink($target);
    }

    public function testListInThemeArray()
    {
        $theme = Theme::load('testpaths');

        $array = TestPages::listInThemeArray($theme);

        usort($array, function ($a, $b) {
            return $a['file'] <=> $b['file'];
        });

        $this->assertIsArray($array);
        $this->assertCount(2, $array);

        $this->assertArrayHasKey('file', $array[0]);
        $this->assertArrayHasKey('pattern', $array[0]);
        $this->assertEquals('a/a-page.htm', $array[0]['file']);
        $this->assertEquals('/apage', $array[0]['pattern']);

        $this->assertArrayHasKey('file', $array[1]);
        $this->assertArrayHasKey('pattern', $array[1]);
        $this->assertEquals('root-page.htm', $array[1]['file']);
        $this->assertEquals('/root-page', $array[1]['pattern']);
    }

    public function testListInThemeArrayExtra()
    {
        $theme = Theme::load('testpaths');

        $array = TestPages::listInThemeArray($theme, [
            'settings.layout' => 'layout',
            'settings.section.test' => 'testKey'
        ]);

        usort($array, function ($a, $b) {
            return $a['file'] <=> $b['file'];
        });

        $this->assertIsArray($array);
        $this->assertCount(2, $array);

        $this->assertArrayHasKey('layout', $array[0]);
        $this->assertArrayHasKey('testKey', $array[0]);
        $this->assertEquals('a/a-layout', $array[0]['layout']);
        $this->assertEquals('a page test', $array[0]['testKey']);

        $this->assertArrayHasKey('layout', $array[1]);
        $this->assertArrayHasKey('testKey', $array[1]);
        $this->assertNull($array[1]['layout']);
        $this->assertEquals('root page test', $array[1]['testKey']);
    }
}

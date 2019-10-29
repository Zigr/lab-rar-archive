# lab-rar-archive
RarArchive Adapter - PHP RarArchive extension integration with [flysystem](https://github.com/thephpleague/flysystem)<br />
Supports: PHP >=5.3.0, [PECL rar](https://pecl.php.net/package/rar) >= 2.0.0

### Usage with Filesystem: ###

```php
use League\Flysystem\Filesystem;
use zigr\Flysystem\RarArchive\RarArchiveAdapter as FreeRar;

$filesystem = new Filesystem(new FreeRar(__DIR__.'/path/to/archive.rar'));

```

### Usage with MountManager: ###

**Extract to local folder:**
```php
use League\Flysystem\Filesystem;
use League\Flysystem\ZipArchive\ZipArchiveAdapter as Zip;
use zigr\Flysystem\RarArchive\RarArchiveAdapter as FreeRar;

$rarDir = realpath(__DIR__ . '/tests/unit/data');
$rarFilename = 'developerlife - Tutorials » GWT Tutorial - Deploying GWT Apps.rar';
$rar = new Filesystem(new FreeRar($rarDir . '/' . $rarFilename));

$zipDir = realpath(__DIR__ . '/tests/unit/data');
$zipFilename = $zipDir . '/' . basename($rarFilename, '.rar') . '.zip';
$zip = new Filesystem(new Zip($zipFilename));

$localDir = realpath(__DIR__ . '/tests/unit/data');
$local = new Filesystem(new \League\Flysystem\Adapter\Local($localDir));

$manager = new \League\Flysystem\MountManager([
    'local' => $local,
    'zip' => $zip,
    // rar:// prefix is also possible, but there is the same stream wrapper registered 
    // with rar extension since PECL rar >= 3.0.0 version 
    'myrar' => $rar,
        ]);
try
{
    $folderToExtract = basename($rarFilename, '.rar');
    $list = $manager->listContents("myrar://$rarFilename", true);
    foreach ($list as $entry)
    {
        if ($entry['type'] == 'file')
        {
            $contents = $manager->readStream("myrar://{$entry['path']}");
            if (is_resource($contents))
            {
                $manager->writeStream("local://{$folderToExtract}/{$entry['subpath']}", $contents);
            }
        }
    }
} catch (\Exception $ex)
{
    echo $ex->getMessage() . PHP_EOL;
}finally
{
    $rar->getAdapter()->getArchive()->close();  
}
```

**Convert rar to zip:**
```php
try
{
    $rar->getAdapter()->openArchive($rarDir . '/' . $rarFilename);
    $rarContents = $manager->listContents("myrar://$rarFilename", true);
    // The same as previous but implicit so unclear
    //$rarContents = $manager->listContents("myrar://", true);

    foreach ($rarContents as $entry)
    {
        if ($entry['type'] == 'file')
        {
            $entryStream = $manager->readStream("myrar://{$entry['path']}");
            if (is_resource($entryStream))
            {
                $manager->writeStream("zip://{$entry['subpath']}", $entryStream);
            }
        }
    }
} catch (Exception $ex)
{
    echo $ex->getMessage() . PHP_EOL;
} finally
{
    $zip->getAdapter()->getArchive()->close();
    // close rar archive and clear state
    $rar->getAdapter()->close();
}
```
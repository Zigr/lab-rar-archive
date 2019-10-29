<?php

namespace zigr\Flysystem\RarArchive;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Util;
use zigr\Flysystem\RarArchive\UnsupportedOperationException;
use \RarArchive;
use \RarEntry;
use \RarException;

/**
 * @date Oct 20, 2019
 * @author ZIgr <zaporozhets.igor at gmail.coml>
 * @license http://www.apache.org/licenses/ Apache License 2.0
 * @encoding UTF-8
 * @version 0.2.1

 * Adapter for working with rar archives. Supports  read operations 
 * with rar archive files as the PHP extension library: https://pecl.php.net/package/rar does.
 * Current version supports PECL rar >= 2.0.0 extension.
 */
class RarArchiveAdapter extends AbstractAdapter
{

    /** @var RarArchive */
    protected $archive;
    protected $password;
    protected $entries;
    protected $usingExeptions = false;
    protected $volume_callback = null;
    protected $rarRoot;
    protected $prevErrHandler;

    /**
     * @param string      $filename
     * @param string|null $prefix
     * @param string|null $password A plain password to the archive as long as present.
     * @param bool $isUsingExceptions Error handling. @see setUsingExceptions()
     * 
     * @throws RarException on open archive error it doesn't matter if the value of $isUsingExceptions is set
     */
    public function __construct($filename, $password = null, $prefix = null, $isUsingExceptions = false)
    {
        $this->setUsingExceptions($isUsingExceptions);
        if (!static::isSupported())
        {
            throw UnsupportedOperationException::forRarExtension(static::class);
        }
        $this->setPathPrefix($prefix);
        $this->openArchive($filename, $password);
    }

    public function __destruct()
    {
        if ($this->prevErrHandler)
        {
            set_error_handler($this->prevErrHandler);
        }
    }

    /**
     * 
     * @return int 0 - on success; 1 - on error
     */
    public function close()
    {
        $result = $this->archive->close();
        unset($this->entries);
        unset($this->archive);
        unset($this->password);
        unset($this->volume_callback);
        unset($this->rarRoot);
        if ($this->prevErrHandler)
        {
            set_error_handler($this->prevErrHandler);
        }
        return ($result) ? 0 : 1;
    }

    /**
     * @link https://www.php.net/manual/en/rarexception.setusingexceptions.php 
     * Activates and deactivates error handling with exceptions
     * 
     * @param bool $flag
     * @return $this
     */
    public function setUsingExceptions($isUsingExceptions = true)
    {
        RarException::setUsingExceptions($isUsingExceptions);
        $this->usingExeptions = $isUsingExceptions;
        if (!$isUsingExceptions)
        {
            $this->prevErrHandler = set_error_handler([$this, 'errorHandler'], E_ERROR | E_RECOVERABLE_ERROR | E_WARNING | E_USER_WARNING);
        }
        return $this;
    }

    /**
     * 
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param string|int $errline
     * @param array $errcontext
     * @return boolean|string
     * @throws RarException on open archive error 
     */
    public function errorHandler($errno, $errstr, $errfile = null, $errline = null, $errcontext = null)
    {
        $str = sprintf("%s;%s;%s;in: %s:%s\n", (new \DateTime())->format('Y-m-d H:i:s'), $errstr, $errno, $errfile, $errline);
        if (strlen(stristr($errstr, 'file open error')) > 0)
        {
            throw new RarException($str);
        }
        return $str;
    }

    public function setVolumeCallback(callable $cb)
    {
        $this->volume_callback = $cb;
        return $this;
    }

    /**
     * Sets current password. May be for the whole file as well as for an individual
     * archive entry
     * @param string $password
     * @return $this
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    public static function isSupported()
    {
        return extension_loaded('rar') && substr(static::version(), 0, 1) >= 2;
    }

    public static function version()
    {
        return phpversion("rar");
    }

    /**
     * Open a rar file.
     *
     * @param string $path Absolute with leading slash or relative
     * @param string $password A plain password. @see https://www.php.net/manual/en/rararchive.open.php
     * @throws Exception @see setUsingExceptions()
     */
    public function openArchive($path, $password = null)
    {
        $location = ($this->getPathPrefix()) ? $this->applyPathPrefix($path) : $path;
        $location = str_replace('/', DIRECTORY_SEPARATOR, $location);
        $this->archive = RarArchive::open($location, $password, $this->volume_callback);
        $this->rarRoot = basename($path);
        $this->entries = $this->archive->getEntries();
    }

    /**
     * for use of RarArchive extended functionality:
     * to check whether archive isBroken, isEncrypted, etc.
     * @return \RarArchive
     */
    public function getArchive()
    {
        return $this->archive;
    }

    /**
     * {@inheritdoc}
     * @return array
     * <pre>
      +-------------+------------------------------------------------------------------+--------+
      | key         | name                                                             | type   |
      +-------------+------------------------------------------------------------------+--------+
      | path        | path to the file or dir                                          | string |
      | type        | file or dir                                                      | string |
      | mimetype    | mime type of the archive entry                                   | string |
      | timestamp   | mime type of the archive entry                                   | int    |
      | size        | size of a file, 0 for a dir                                      | int    |
      | EXTRA       |------------------------------------------------------------------+--------|
      | crc         | CRC of the archive entry                                         | string |
      | index       | Entry position in the archive index                              | int    |
      | comp_method | the method number used when adding current archive entry         | int    |
      | comp_size   | packed size of the archive entry                                 | int    |
      | wrapper     | rar://<url encoded archive name>[*][#[<url encoded entry name>]] | string |
      +-------------+------------------------------------------------------------------+--------+
     * </pre>
     */
    public function getMetadata($path)
    {
        $entryItem = $this->getEntry($path);
        $entry = reset($entryItem);
        return !empty($entry) ? $this->normalizeObject($entry, key($entryItem)) : false;
    }

    protected function getEntry($path)
    {
        $location = $this->removeArchPrefix($path);
        
        $entries = array_filter($this->entries, function($entry) use($location) {
            return $entry->getName() == $location;
        });
        return $entries;
    }

    /**
     * @link  https://www.php.net/manual/en/wrappers.rar.php
     * @param string $entryName Archive entry name
     * @return string wrapper string to reference in a stream
     */
    public function getWrapperString($entryName)
    {
        $encArchName = rawurlencode($this->applyPathPrefix($this->rarRoot));
        $encEntryName = rawurlencode($entryName);
        $wrapperFormat = 'rar://<url encoded archive name>#/<url encoded entry name>';
        $result = str_replace(['<url encoded archive name>', '<url encoded entry name>'], [$encArchName, $encEntryName], $wrapperFormat);
        return $result;
    }

    /**
     * {@inheritdoc}
     * @return array|false
     * <pre>
      +----------+--------------------------------+--------+
      | key      | name                           | type   |
      +----------+--------------------------------+--------+
      | path     | path to the file or dir        | string |
      | type     | file or dir                    | string |
      | mimetype | mime type of the archive entry | string |
      +----------+--------------------------------+--------+
     * </pre>
     */
    public function getMimetype($path)
    {
        $data = $this->read($path);
        if (!$data)
        {
            return false;
        }

        $mimetype = Util::guessMimeType($path, $data['contents']);

        return ['path' => $path, 'type' => 'file', 'mimetype' => $mimetype];
    }

    /**
     * gets mime time from stream
     * @param string $path
     * @return array
     * <pre>
      +----------+------------------------------------------------------------------+--------+
      | key      | name                                                             | type   |
      +----------+------------------------------------------------------------------+--------+
      | path     | path to the file or dir                                          | string |
      | type     | file or dir                                                      | string |
      | mimetype | mime type of the archive entry                                   | string |
      | wrapper  | rar://<url encoded archive name>[*][#[<url encoded entry name>]] | string |
      +----------+------------------------------------------------------------------+--------+
     * </pre>
     *  
     */
    public function getMimeFromStream($path)
    {
        $options = [
            'rar' => [
                'open_password' => $this->password,
                'file_password' => $this->password,
                'volume_callback' => $this->volume_callback,
            ]
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $entries = $this->getEntry($path);
        $context = stream_context_create($options);
        $entry = reset($entries);
        if (!$entry->isDirectory())
        {
            $wrapperString = $this->getWrapperString($entry->getName());
            $mimetype = finfo_file($finfo, $wrapperString, FILEINFO_MIME_TYPE, $context);
        }

        return ['path' => $path, 'type' => 'file', 'mimetype' => $mimetype, 'wrapper' => $wrapperString];
    }

    /**
     * {@inheritdoc}
     * @see getMetadata() for a return list
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     * @see getMetadata() for a return list
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getVisibility($path)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function has($path)
    {
        $entries = $this->getEntry($path);
        return count($entries) > 0;
    }

    /**
     * Removes archive root prefix from the $path
     * @param string $path
     * @return string
     */
    public function removeArchPrefix($path)
    {
        $location = rtrim(parent::removePathPrefix($path), '\\/');
        $pos = strpos($location, $this->rarRoot);
        return ($pos !== false) ? substr($location, strlen($this->rarRoot) + 1) : $location;
    }

    /**
     * @todo Add case sensitivity configuration
     */
    protected function getEntries($path = null, $recurcive = false)
    {
        $rarPath = $this->removeArchPrefix($path);
        if (empty($rarPath))
        {
            return $this->entries;
        }

        return array_filter($this->entries, function($entry) use($rarPath, $recurcive) {
            $archPrefix = $rarPath;
            $len = strlen($archPrefix);
            if (!$recurcive && strpos($entry->getName(), '/', $len + 1) > 0)
            {
                return false;
            }
            if ($archPrefix && (substr($entry->getName(), 0, $len) !== $archPrefix || $entry->getName() === $archPrefix))
            {
                return false;
            }
            return true;
        });
    }

    /**
     * {@inheritdoc}
     * @see getMetadata() for a return list
     */
    public function listContents($directory = '', $recursive = false)
    {
        $entries = $this->getEntries($directory, $recursive);
        $pathPrefix = $this->getPathPrefix();
        $prefixLength = strlen($pathPrefix);
        return array_filter(array_map(function ($item) use ($pathPrefix, $prefixLength) {
                    if ($pathPrefix && (substr($item->getName(), 0, $prefixLength) !== $pathPrefix || $item->getName() === $pathPrefix))
                    {
                        return false;
                    }
                    return $this->normalizeObject($item);
                }, $entries));
    }

    /**
     * {@inheritdoc}
     * @return array|false Description:
     * <pre>
      +----------+-------------------------+--------+
      | key      | name                    | type   |
      +----------+-------------------------+--------+
      | path     | path to the file or dir | string |
      | type     | file or dir             | string |
      | contents | file contents           | string |
      +----------+-------------------------+--------+
     * </pre>
     */
    public function read($path)
    {
        $entryItem = $this->getEntry($path);
        $entry = reset($entryItem);

        if ($entry->isDirectory())
        {
            return false;
        }
        $contents = $this->entryReadStream($entry);
        if ($contents === false)
        {
            return false;
        }

        return $contents !== false ? ['type' => 'file', 'path' => $path, 'contents' => $contents] : false;
    }

    /**
     * Reads entry stream and also checks CRC entry stream integrity
     * @param \RarEntry $entry
     * @return string Stream contents
     * @throws \RarException
     */
    protected function entryReadStream($entry)
    {
        $contents = '';
        $stream = $entry->getStream();
        if ($stream === false)
        {
            return false;
        }
        $crc = hash_init('crc32b');
        while (!feof($stream))
        {
            $buff = fread($stream, 8192);
            if ($buff !== false)
            {
                hash_update($crc, $buff);
                $contents .= $buff;
            } else
            {//fread error
                fclose($stream);
                $message = $this->rarRoot . '/' . $entry->getName() . ': Stream read error';
                if (empty($this->usingExeptions))
                {
                    trigger_error($message, E_USER_WARNING);
                } else
                {
                    throw new \RarException($this->rarRoot . '/' . $entry->getName() . ': Stream read error', 18, null);
                }
            }
        }
        fclose($stream);

        $gotCrc = hash_final($crc);
        $needCrc = $entry->getCrc();
        if (hexdec($gotCrc) != hexdec($needCrc))
        {
            $message = sprintf("%s;CRC of entry  was incorrect. Need: [%s], got [%s]", $this->rarRoot . '/' . $entry->getName(), $needCrc, $gotCrc);
            trigger_error($message, E_USER_WARNING); // may proceed on
        }

        return $contents;
    }

    /**
     * {@inheritdoc}
     * <pre>
      +---------+------------------------------------------------------------------+----------+
      | key     | name                                                             | type     |
      +---------+------------------------------------------------------------------+----------+
      | path    | path to the file or dir                                          | string   |
      | type    | file or dir                                                      | string   |
      | stream  | file contents                                                    | resource |
      | wrapper | rar://<url encoded archive name>[*][#[<url encoded entry name>]] | string   |
      +---------+------------------------------------------------------------------+----------+
      </pre>
     */
    public function readStream($path)
    {
        $entryItem = $this->getEntry($path);
        $entry = reset($entryItem);
        if (empty($entry) || $entry->isDirectory())
        {
            return false;
        }
        $this->entryReadStream($entry); // check for integrity
        $stream = $entry->getStream($this->password);
        return $stream !== false ? [
            'type' => 'file',
            'path' => $entry->getName(),
            'stream' => $stream,
            'wrapper' => $this->getWrapperString($entry)
                ] : false;
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibility($path, $visibility)
    {
        return false;
    }

    /**
     * Normalize a rar response.
     * @param RarEntry $object
     * @return array
     */
    protected function normalizeObject($object, $position = null)
    {
        $type = $object->isDirectory() ? 'dir' : 'file';
        $info = [
            'path' => $this->rarRoot . '/' . $object->getName(), //with archive file root
            'subpath' =>  $object->getName(), // relative to archive root
            'type' => $type,
            'index' => method_exists('\RarEntry', 'getPosition') ? $object->getPosition() : $position,
            'timestamp' => (false !== $object->getFileTime()) ? strtotime($object->getFileTime()) : 0,
            'comp_method' => $object->getMethod(),
        ];
        if ($type == 'dir')
        {
            return $info;
        } else
        {
            $mimeInfo = $this->getMimetype($object->getName());
            $info += [
                'mimetype' => $mimeInfo['mimetype'],
                'crc' => $object->getCrc(),
                'size' => $object->getUnpackedSize(),
                'comp_size' => $object->getPackedSize(),
            ];
        }
        return $info;
    }

    protected function raiseUsupportedException($method)
    {
        $operation = ltrim(str_replace(__NAMESPACE__, '', $method), '\\/');
        throw UnsupportedOperationException::forRarModification($operation);
    }

    /**
     * {@inheritdoc}
     */
    public function copy($path, $newpath)
    {
        $this->raiseUsupportedException(__METHOD__);
    }

    public function createDir($dirname, \League\Flysystem\Config $config)
    {
        $this->raiseUsupportedException(__METHOD__);
    }

    public function delete($path)
    {
        $this->raiseUsupportedException(__METHOD__);
    }

    public function deleteDir($dirname)
    {
        $this->raiseUsupportedException(__METHOD__);
    }

    public function rename($path, $newpath)
    {
        $this->raiseUsupportedException(__METHOD__);
    }

    public function update($path, $contents, \League\Flysystem\Config $config)
    {
        $this->raiseUsupportedException(__METHOD__);
    }

    public function updateStream($path, $resource, \League\Flysystem\Config $config)
    {
        $this->raiseUsupportedException(__METHOD__);
    }

    public function write($path, $contents, \League\Flysystem\Config $config)
    {
        $this->raiseUsupportedException(__METHOD__);
    }

    public function writeStream($path, $resource, \League\Flysystem\Config $config)
    {
        $this->raiseUsupportedException(__METHOD__);
    }

}

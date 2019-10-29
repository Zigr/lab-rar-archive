<?php

namespace zigr\Flysystem\RarArchive;

use League\Flysystem\NotSupportedException;

/**
 * Description of UnsupportedOperationException
 *
 * @author ZIgr <zaporozhets.igor at gmail.coml>
 * @date Oct 21, 2019
 * @encoding UTF-8
 *  
 */
class UnsupportedOperationException extends NotSupportedException
{

    /**
     * Creates a new exception for rar modification operations.
     *
     * @param string $operation
     *
     * @return static
     */
    public static function forRarModification($operation)
    {
        $message = "Operation '$operation' is not supported due to the rar license terms.\nSee http://www.rarlabs.com";
        return new static($message);
    }

        /**
     * Creates a new exception if no rar extension is installed or extension.
     *
     * @param string $operation
     *
     * @return static
     */
    public static function forRarExtension($className){
        $message = "'$className' needs rar extension version >= 2.0 to be installed.\nSee https://www.php.net/manual/en/rar.installation.php for details.";
        return new static($message);
    }
}

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * Core file system class definition.
 *
 * @package   core_files
 * @copyright 2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * File system class used for low level access to real files in filedir.
 *
 * @package   core_files
 * @category  files
 * @copyright 2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.9
 */
class file_system {

    protected $fs = null;

    protected $filedir = null;

    public function __construct($filedir, $dirpermissions, $filepermissions, file_storage $fs = null) {
        $this->filedir          = $filedir;
        $this->dirpermissions   = $dirpermissions;
        $this->filepermissions  = $filepermissions;

        if ($fs) {
            $this->fs = $fs;
        } else {
            $this->fs = get_file_storage();
        }
    }

    public static function instance($filedir = null, $dirpermissions = null, $filepermissions = null, file_storage $fs = null) {
        global $CFG;

        static $instance = null;

        if ($instance === null) {
            if (empty($filedir)) {
                // If the filedir is not present, then the file_storage has not been instantiated. Set that up first.
                get_file_storage();
                return self::instance();
            }
            if (!empty($CFG->filesystem_handler_class)) {
                $class = $CFG->filesystem_handler_class;
            } else {
                $class = get_class();
            }
            $instance = new $class($filedir, $dirpermissions, $filepermissions, $fs);
        }

        return $instance;
    }

    public function upload_moodle_data() {
        return;
    }

    ////////////////////////////////////////////////////////////////////////////
    // old stored_file stuff
    ////////////////////////////////////////////////////////////////////////////

    public function readfile(stored_file $file) {
        $this->ensure_readable($file);
        $path = $this->get_fullpath_from_storedfile($file, true);
        readfile_allow_large($path, $file->get_filesize());
    }

    /**
     * Get file pathname by contenthash
     *
     * NOTE, this function is not calling sync_external_file, it assume the contenthash is current
     * Protected - developers must not gain direct access to this function.
     *
     * @return string full path to pool file with file content
     */
    protected function get_fullpath_from_storedfile(stored_file $file, $sync = false) {
        if ($sync) {
            $file->sync_external_file();
        }
        // Detect is local file or not.
        return $this->get_fullpath_from_hash($file->get_contenthash());
    }

    /**
     * Return path to file with given hash.
     *
     * NOTE: must not be public, files in pool must not be modified
     *
     * @param string $contenthash content hash
     * @return string expected file location
     */
    protected function get_fulldir_from_hash($contenthash) {
        return $this->filedir . DIRECTORY_SEPARATOR . $this->get_contentdir_from_hash($contenthash);
    }

    /**
     * Return path to file with given hash.
     *
     * NOTE: must not be public, files in pool must not be modified
     *
     * @param string $contenthash content hash
     * @return string expected file location
     */
    protected function get_fullpath_from_hash($contenthash) {
        return $this->filedir . DIRECTORY_SEPARATOR . $this->get_contentpath_from_hash($contenthash);
    }

    protected function get_contentdir_from_hash($contenthash) {
        $l1 = $contenthash[0].$contenthash[1];
        $l2 = $contenthash[2].$contenthash[3];
        return "$l1/$l2";
    }

    protected function get_contentpath_from_hash($contenthash) {
        return $this->get_contentdir_from_hash($contenthash) . "/$contenthash";
    }

    /**
     * Determine whether the file is present on the file system somewhere.
     *
     * @param stored_file $file The file to ensure is available.
     * @return bool
     */
    public function is_readable($file) {
        $path = $this->get_fullpath_from_storedfile($file, true);
        if (!is_readable($path)) {
            if (!$this->fs->try_content_recovery($file) or !is_readable($path)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Ensure that the file really is available on the file system.
     * Often, we want to perform operations on the file which involve file
     * streams. In these instances the file may be elsewhere.
     *
     * Use this function only when you really do need the file to exist on
     * the local filesystem and cannot use a streamed copy instead.
     *
     * @param stored_file $file The file to ensure is available.
     * @return bool
     * @throws file_exception When the file could not be found at all.
     */
    public function ensure_readable(stored_file $file) {
        if (!$this->is_readable($file)) {
            throw new file_exception('storedfilecannotread', '', $this->get_fullpath_from_storedfile($file));
        }
        return true;
    }

    /**
     * Copy content of file to given pathname.
     *
     * @param string $pathname real path to the new file
     * @return bool success
     */
    public function copy_content_to(stored_file $file, $pathname) {
        $this->ensure_readable($file);
        $source = $this->get_fullpath_from_storedfile($file, true);
        return copy($source, $pathname);
    }

    public function get_content(stored_file $file) {
        $this->ensure_readable($file);
        $source = $this->get_fullpath_from_storedfile($file, true);
        return file_get_contents($source);

    }

    /**
     * List contents of archive.
     *
     * @param file_packer $packer file packer instance
     * @return array of file infos
     */
    public function list_files($file, file_packer $packer) {
        $archivefile = $this->get_fullpath_from_storedfile($file, true);
        return $packer->list_files($archivefile);
    }

    /**
     * Extract file to given file path (real OS filesystem), existing files are overwritten.
     *
     * @param file_packer $packer file packer instance
     * @param string $pathname target directory
     * @param file_progress $progress Progress indicator callback or null if not required
     * @return array|bool list of processed files; false if error
     */
    public function extract_to_pathname(stored_file $file, file_packer $packer, $pathname, file_progress $progress = null) {
        $archivefile = $this->get_fullpath_from_storedfile($file, true);
        return $packer->extract_to_pathname($archivefile, $pathname, null, $progress);
    }

    /**
     * Adds this file path to a curl request (POST only).
     *
     * @param curl $curlrequest the curl request object
     * @param string $key what key to use in the POST request
     * @return void
     */
    public function add_to_curl_request(&$curlrequest, $key) {
        $path = $this->get_fullpath_from_storedfile($file, true);
        if (function_exists('curl_file_create')) {
            // As of PHP 5.5, the usage of the @filename API for file uploading is deprecated.
            $value = curl_file_create($path);
        } else {
            $value = '@' . $path;
        }
        $curlrequest->_tmp_file_post_params[$key] = $value;
    }

    /**
     * Extract file to given file path (real OS filesystem), existing files are overwritten.
     *
     * @param file_packer $packer file packer instance
     * @param int $contextid context ID
     * @param string $component component
     * @param string $filearea file area
     * @param int $itemid item ID
     * @param string $pathbase path base
     * @param int $userid user ID
     * @param file_progress $progress Progress indicator callback or null if not required
     * @return array|bool list of processed files; false if error
     */
    public function extract_to_storage(stored_file $file, file_packer $packer, $contextid,
            $component, $filearea, $itemid, $pathbase, $userid = null, file_progress $progress = null) {
        $archivefile = $this->get_fullpath_from_storedfile($file, true);
        return $packer->extract_to_storage($archivefile, $contextid,
                $component, $filearea, $itemid, $pathbase, $userid, $progress);
    }

    /**
     * Add file/directory into archive.
     *
     * @param file_archive $filearch file archive instance
     * @param string $archivepath pathname in archive
     * @return bool success
     */
    public function archive_file(stored_file $file, file_archive $filearch, $archivepath) {
        if ($file->is_directory()) {
            return $filearch->add_directory($archivepath);
        } else {
            $this->ensure_readable($file);
            return $filearch->add_file_from_pathname($archivepath, $this->get_fullpath_from_storedfile($file, true));
        }
    }

    /**
     * Returns information about image,
     * information is determined from the file content
     *
     * @return mixed array with width, height and mimetype; false if not an image
     */
    public function get_imageinfo($file) {
        $this->ensure_readable($file);
        $mimetype = $file->get_mimetype();
        $path = $this->get_fullpath_from_storedfile($file, true);
        if (!preg_match('|^image/|', $mimetype) || !filesize($path) || !($imageinfo = getimagesize($path))) {
            return false;
        }
        $image = array('width'=>$imageinfo[0], 'height'=>$imageinfo[1], 'mimetype'=>image_type_to_mime_type($imageinfo[2]));
        if (empty($image['width']) or empty($image['height']) or empty($image['mimetype'])) {
            // gd can not parse it, sorry
            return false;
        }
        return $image;
    }

    /**
     * Returns file handle - read only mode, no writing allowed into pool files!
     *
     * When you want to modify a file, create a new file and delete the old one.
     *
     * @param int $type Type of file handle (FILE_HANDLE_xx constant)
     * @return resource file handle
     */
    public function get_content_file_handle($file, $type = stored_file::FILE_HANDLE_FOPEN) {
        $this->ensure_readable($file);
        $path = $this->get_fullpath_from_storedfile($file, true);
        return self::get_file_handle_for_path($path, $type);
    }

    protected static function get_file_handle_for_path($path, $type = stored_file::FILE_HANDLE_FOPEN) {
        switch ($type) {
            case stored_file::FILE_HANDLE_FOPEN:
                // Binary reading.
                return fopen($path, 'rb');
            case stored_file::FILE_HANDLE_GZOPEN:
                // Binary reading of file in gz format.
                return gzopen($path, 'rb');
            default:
                throw new coding_exception('Unexpected file handle type');
        }
    }

    ////////////////////////////////////////////////////////////////////////////
    // old file_storage stuff
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Return mimetype by given file pathname
     *
     * If file has a known extension, we return the mimetype based on extension.
     * Otherwise (when possible) we try to get the mimetype from file contents.
     *
     * @param string $pathname full path to the file
     * @param string $filename correct file name with extension, if omitted will be taken from $path
     * @return string
     */
    public static function mimetype($pathname, $filename = null) {
        if (empty($filename)) {
            $filename = $pathname;
        }
        $type = mimeinfo('type', $filename);
        if ($type === 'document/unknown' && class_exists('finfo') && file_exists($pathname)) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $type = mimeinfo_from_type('type', $finfo->file($pathname));
        }
        return $type;
    }

    /**
     * Add file content to sha1 pool.
     *
     * @param string $pathname path to file
     * @param string $contenthash sha1 hash of content if known (performance only)
     * @return array (contenthash, filesize, newfile)
     */
    public function add_file_to_pool($pathname, $contenthash = NULL) {
        global $CFG;

        if (!is_readable($pathname)) {
            throw new file_exception('storedfilecannotread', '', $pathname);
        }

        $filesize = filesize($pathname);
        if ($filesize === false) {
            throw new file_exception('storedfilecannotread', '', $pathname);
        }

        if (is_null($contenthash)) {
            $contenthash = sha1_file($pathname);
        } else if ($CFG->debugdeveloper) {
            $filehash = sha1_file($pathname);
            if ($filehash === false) {
                throw new file_exception('storedfilecannotread', '', $pathname);
            }
            if ($filehash !== $contenthash) {
                // Hopefully this never happens, if yes we need to fix calling code.
                debugging("Invalid contenthash submitted for file $pathname", DEBUG_DEVELOPER);
                $contenthash = $filehash;
            }
        }
        if ($contenthash === false) {
            throw new file_exception('storedfilecannotread', '', $pathname);
        }

        if ($filesize > 0 and $contenthash === sha1('')) {
            // Did the file change or is sha1_file() borked for this file?
            clearstatcache();
            $contenthash = sha1_file($pathname);
            $filesize = filesize($pathname);

            if ($contenthash === false or $filesize === false) {
                throw new file_exception('storedfilecannotread', '', $pathname);
            }
            if ($filesize > 0 and $contenthash === sha1('')) {
                // This is very weird...
                throw new file_exception('storedfilecannotread', '', $pathname);
            }
        }

        $hashpath = $this->get_fulldir_from_hash($contenthash);
        $hashfile = "$hashpath/$contenthash";

        $newfile = true;

        if (file_exists($hashfile)) {
            if (filesize($hashfile) === $filesize) {
                return array($contenthash, $filesize, false);
            }
            if (sha1_file($hashfile) === $contenthash) {
                // Jackpot! We have a sha1 collision.
                mkdir("$this->filedir/jackpot/", $this->dirpermissions, true);
                copy($pathname, "$this->filedir/jackpot/{$contenthash}_1");
                copy($hashfile, "$this->filedir/jackpot/{$contenthash}_2");
                throw new file_pool_content_exception($contenthash);
            }
            debugging("Replacing invalid content file $contenthash");
            unlink($hashfile);
            $newfile = false;
        }

        if (!is_dir($hashpath)) {
            if (!mkdir($hashpath, $this->dirpermissions, true)) {
                // Permission trouble.
                throw new file_exception('storedfilecannotcreatefiledirs');
            }
        }

        // Let's try to prevent some race conditions.

        $prev = ignore_user_abort(true);
        @unlink($hashfile.'.tmp');
        if (!copy($pathname, $hashfile.'.tmp')) {
            // Borked permissions or out of disk space.
            ignore_user_abort($prev);
            throw new file_exception('storedfilecannotcreatefile');
        }
        if (filesize($hashfile.'.tmp') !== $filesize) {
            // This should not happen.
            unlink($hashfile.'.tmp');
            ignore_user_abort($prev);
            throw new file_exception('storedfilecannotcreatefile');
        }
        rename($hashfile.'.tmp', $hashfile);
        chmod($hashfile, $this->filepermissions); // Fix permissions if needed.
        @unlink($hashfile.'.tmp'); // Just in case anything fails in a weird way.
        ignore_user_abort($prev);

        return array($contenthash, $filesize, $newfile);
    }

    /**
     * Add string content to sha1 pool.
     *
     * @param string $content file content - binary string
     * @return array (contenthash, filesize, newfile)
     */
    public function add_string_to_pool($content) {
        global $CFG;

        $contenthash = sha1($content);
        $filesize = strlen($content); // binary length

        $hashpath = $this->get_fulldir_from_hash($contenthash);
        $hashfile = "$hashpath/$contenthash";

        $newfile = true;

        if (file_exists($hashfile)) {
            if (filesize($hashfile) === $filesize) {
                return array($contenthash, $filesize, false);
            }
            if (sha1_file($hashfile) === $contenthash) {
                // Jackpot! We have a sha1 collision.
                mkdir("$this->filedir/jackpot/", $this->dirpermissions, true);
                copy($hashfile, "$this->filedir/jackpot/{$contenthash}_1");
                file_put_contents("$this->filedir/jackpot/{$contenthash}_2", $content);
                throw new file_pool_content_exception($contenthash);
            }
            debugging("Replacing invalid content file $contenthash");
            unlink($hashfile);
            $newfile = false;
        }

        if (!is_dir($hashpath)) {
            if (!mkdir($hashpath, $this->dirpermissions, true)) {
                // Permission trouble.
                throw new file_exception('storedfilecannotcreatefiledirs');
            }
        }

        // Hopefully this works around most potential race conditions.

        $prev = ignore_user_abort(true);

        if (!empty($CFG->preventfilelocking)) {
            $newsize = file_put_contents($hashfile.'.tmp', $content);
        } else {
            $newsize = file_put_contents($hashfile.'.tmp', $content, LOCK_EX);
        }

        if ($newsize === false) {
            // Borked permissions most likely.
            ignore_user_abort($prev);
            throw new file_exception('storedfilecannotcreatefile');
        }
        if (filesize($hashfile.'.tmp') !== $filesize) {
            // Out of disk space?
            unlink($hashfile.'.tmp');
            ignore_user_abort($prev);
            throw new file_exception('storedfilecannotcreatefile');
        }
        rename($hashfile.'.tmp', $hashfile);
        chmod($hashfile, $this->filepermissions); // Fix permissions if needed.
        @unlink($hashfile.'.tmp'); // Just in case anything fails in a weird way.
        ignore_user_abort($prev);

        return array($contenthash, $filesize, $newfile);
    }

    ////////////////////////////////////////////////////////////////////////////
    // New stuff
    ////////////////////////////////////////////////////////////////////////////
    public function stored_file_mimetype(stored_file $file) {
        // The mimetype functions do require that the file exists locally for some obscure reason.
        $this->ensure_readable($file);
        $pathname = $this->get_fullpath_from_storedfile($file);
        return self::mimetype($pathname, $file->get_filename());
    }
}

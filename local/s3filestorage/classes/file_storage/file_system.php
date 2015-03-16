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
 * A version of the file system for S3.
 *
 * @package    s3filestorage
 * @subpackage local
 * @copyright  2015 Andrew Nicols
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_s3filestorage\file_storage;

require_once(dirname(dirname(__DIR__)) . '/vendor/autoload.php');

use file_storage;
use stored_file;

use Aws\Common\Aws;
use moodle_exception;

use Aws\S3\S3Client;

defined('MOODLE_INTERNAL') || die();

class file_system extends \file_system {

    /**
     * @var $bucket The string name for the bucket.
     */
    protected static $bucket = null;

    /**
     * @var $client The S3Client instance.
     */
    protected static $client = null;

    public function __construct($filedir, $dirpermissions, $filepermissions, file_storage $fs = null) {
        global $CFG;

        // Set up the rest of the constructor.
        parent::__construct($filedir, $dirpermissions, $filepermissions, $fs);

        if (!isset($CFG->s3bucket)) {
            throw new moodle_exception('S3 not configured');
        }

        // Attempt to connect to S3.
        if (isset($CFG->awsconfig)) {
            $aws = Aws::factory($CFG->awsconfig);
        } else {
            $aws = Aws::factory();
        }
        self::$client = $aws->get('S3');
        self::$bucket = $CFG->s3bucket;
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
        $this->fetch_local_copy($file->get_contenthash());
        return parent::ensure_readable($file);
    }

    /**
     * Handle readfile for a stored_file.
     *
     * @param stored_file $file The stored file.
     */
    public function readfile(stored_file $file) {
        // TODO convert this to use fopen.
        return parent::readfile($file);
    }

    /**
     * Copy the content to the specified location.
     *
     * @param stored_file $file The file to copy.
     * @param string $fullpath The full path to the new file.
     * @return bool The result of the copy operation.
     */
    public function copy_content_to(stored_file $file, $fullpath) {
        if ($this->is_readable($file)) {
            return parent::copy_content_to($file, $fullpath);
        } else {
            // No point downloading, then copying. Just perform a straight download to the target.
            if ($this->fetch_local_copy($file->get_contenthash(), $fullpath)) {
                return true;
            } else {
                // Something went wrong and, for some reason, an exception was not thrown.
                return false;
            }
        }
    }

    /**
     * Get the content of the specified file.
     *
     * @param stored_file $file The file to retrieve content for.
     * @return string The file content.
     */
    public function get_content(stored_file $file) {
        // TODO - do we want to ensure that this is readable?
        // Need to audit where we call get_content and decide if it's
        // something we should always stream from, or always save for.
        $this->ensure_readable($file);
        if ($this->is_readable($file)) {
            // If a locally cached copy is available, return it rather than going to S3.
            return parent::get_content($file);
        } else {
            // Generate a pre-signed URL and retrieve it's content.
            return file_get_contents($this->get_presigned_url($file->get_contenthash()));
        }
    }

    /**
     * Fetch a local copy of the file described by contenthash.
     *
     * If newtarget is specified, and it matches the expected target for
     * the contenthash, check whether the file already exists on disk
     * first.
     *
     * If newtarget is specified but it does not match, then the file is
     * downloaded to the specified location.
     *
     * If no value for newtarget is specified, then the default location
     * for that contenthash is used.
     *
     * @param string $contenthash The hash for the content in the Moodle filedir.
     * @param string $newtarget The intended location for the file, if
     * different to the standard location.
     * @return string The path to the new file
     */
    protected function fetch_local_copy($contenthash, $newtarget = null) {
        $target = $this->get_fullpath_from_hash($contenthash);
        if ($newtarget === null || $newtarget === $target) {
            if (is_readable($target)) {
                // The file is still available locally. Touch it to update the timestamp.
                // This used for file cleanup when we remove all old files.
                touch($target);

                return $target;
            }
        } else if ($newtarget) {
            $target = $newtarget;
        }

        $temptarget = $target . '.tmp';
        // Attempt to fetch the file.
        $subpath = $this->get_contentpath_from_hash($contenthash);
        try {
            self::$client->getObject(array(
                    'Bucket'    => self::$bucket,
                    'Key'       => $subpath,
                    'SaveAs'    => $temptarget,
                ));
            // Atomicity is nice.
            rename($temptarget, $target);
            chmod($target, $this->filepermissions); // Fix permissions if needed.
            @unlink($temptarget); // Just in case anything fails in a weird way.
        } catch (\Aws\S3\Exception\NoSuchKeyException $e) {
            debugging('File not found');
        }

        // The file was successfully found - return it again now.
        return $target;
    }

    /**
     * Get an S3 pre-signed URL for the specified content hash.
     *
     * @param string $contenthash The contenthash to retrieve a URL for.
     * @return string The pre-signed URL.
     */
    protected function get_presigned_url($contenthash) {
        // Find the parth within the filedir to use.
        $subpath = $this->get_contentpath_from_hash($contenthash);

        // We generate a pre-signed URL for the file handle to use.
        $command = self::$client->getCommand('GetObject', array(
                'Bucket'    => self::$bucket,
                'Key'       => $subpath,
            ));

        // It doesn't matter how long this presigned URL lasts as it is disposed of almost immediately.
        // It must be a sufficient period of time to allow slow reads of large files.
        $url = $command->createPresignedUrl('+1 day');

        return $url;
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
        return self::get_file_handle_for_path($this->get_presigned_url($file->get_contenthash()), $type);
    }

    /**
     * Upload the entire moodle data directory to S3.
     */
    public function sync_filedir($debug = false) {
        // By default uploadDirectory only uploads missing and/or different files.
        $options = array(
            'debug' => $debug,
        );
        self::$client->uploadDirectory($this->filedir, self::$bucket, '', $options);
    }

    /**
     * Add file content to sha1 pool.
     *
     * @param string $pathname path to file
     * @param string $contenthash sha1 hash of content if known (performance only)
     * @return array (contenthash, filesize, newfile)
     */
    public function add_file_to_pool($pathname, $contenthash = NULL) {
        $result = parent::add_file_to_pool($pathname, $contenthash);
        return $this->push_to_s3($result);
    }

    /**
     * Add string content to sha1 pool.
     *
     * @param string $content file content - binary string
     * @return array (contenthash, filesize, newfile)
     */
    public function add_string_to_pool($content) {
        $result = parent::add_string_to_pool($content);
        return $this->push_to_s3($result);
    }

    /**
     * Push the newly added file to S3.
     *
     * @param array $result The result from add_file_to_pool or add_string_to_pool.
     * @return array $result The input is passed straight back out.
     *
     */
    private function push_to_s3($result) {
        list($contenthash, $filesize, $newfile) = $result;

        if ($newfile) {
            // TODO Schedule a file push.
            $source = $this->get_fullpath_from_hash($contenthash);
            $key = $this->get_contentpath_from_hash($contenthash);

            $fh = fopen($source, 'r');
            self::$client->upload(self::$bucket, $key, $fh);
        }

        return $result;
    }

}

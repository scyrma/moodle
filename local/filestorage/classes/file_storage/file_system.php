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
 * @package    filestorage
 * @subpackage local
 * @copyright  2015 Andrew Nicols
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_filestorage\file_storage;

require_once(dirname(dirname(__DIR__)) . '/vendor/autoload.php');

use file_storage;
use stored_file;

use Aws\Common\Aws;
use moodle_exception;
use file_pool_content_exception;
use Aws\S3\Exception\NoSuchKeyException;

use Aws\S3\S3Client;

use local_logging\logger;

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

        if (!isset($CFG->s3bucket)) {
            throw new moodle_exception('S3 not configured');
        }

        $s3filedir = make_localcache_directory(substr(md5(microtime()), 0, 8));
        if (!$s3filedir) {
            throw new moodle_exception('Unable to create a S3 cache directory');
        }

        if (strpos($s3filedir, $filedir)) {
            throw new moodle_exception('The S3 cache directory is within filedir. Aborting before something dangerous happens');
        }

        \core_shutdown_manager::register_function(array($this, 'cleanup'), array($s3filedir));

        // Set up the rest of the constructor.
        parent::__construct($s3filedir, $dirpermissions, $filepermissions, $fs);

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
     * The shutdown handler for the S3 file storage system.
     *
     * This will remove the temporary filedir.
     * This looks like a dangerous operation
     */
    public function cleanup($s3filedir) {
        // Unlink the entire filedir.
        remove_dir($s3filedir);
    }

    /**
     * Return the filedir. The filedir is specific to this request.
     *
     * @param string $contenthash
     * @return string
     */
    protected function get_fulldir_from_hash($contenthash) {
        return $this->filedir;
    }

    /**
     * Return the filedir/$contenthash. The filedir is specific to this request.
     *
     * @param string $contenthash
     * @return string
     */
    protected function get_fullpath_from_hash($contenthash) {
        return $this->filedir . DIRECTORY_SEPARATOR . $contenthash;
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
        if ($file->is_directory()) {
            // We cannot store directories in S3, so we cannot fetch them.
            return true;
        }

        $contenthash = $file->get_contenthash();
        if ($file->get_filesize() === 0) {
            // S3 Cannot deal with empty files - touch the target on the filesystem.
            $target = $this->get_fullpath_from_hash($contenthash);
            touch($target);
            chmod($target, $this->filepermissions); // Fix permissions if needed.
        }

        $this->fetch_local_copy($contenthash);
        return parent::ensure_readable($file);
    }

    /**
     * Handle readfile for a stored_file.
     *
     * @param stored_file $file The stored file.
     */
    public function readfile(stored_file $file) {
        return readfile_allow_large($this->get_presigned_url($file->get_contenthash()), $file->get_filesize());
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
        $key = $this->get_contentpath_from_hash($contenthash);
        try {
            $start = microtime();
            self::$client->getObject(array(
                    'Bucket'    => self::$bucket,
                    'Key'       => $key,
                    'SaveAs'    => $temptarget,
                ));

            self::log_statistic('fetched', array(
                    'logmessage'    => 'Fetched file from S3',
                    'contenthash'   => $contenthash,
                    'filesize'      => filesize($temptarget),
                    'time'          => microtime_diff($start, microtime()),
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

        self::log_statistic('generatedurl', array(
                'logmessage'    => 'Generated pre-signed URL',
                'contenthash'   => $contenthash,
            ));

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

        switch ($type) {
            case stored_file::FILE_HANDLE_FOPEN:
                self::$client->registerStreamWrapper();
                // Binary reading.
                $context = stream_context_create([
                    's3' => ['seekable' => true]
                ]);
                return fopen('s3://'.self::$bucket.'/'.$file->get_contenthash(), 'r', false, $context);
                break;
            default:
                return self::get_file_handle_for_path($this->get_presigned_url($file->get_contenthash()), $type);
        }
    }

    /**
     * Upload the entire moodle data directory to S3.
     */
    public function sync_filedir($debug = false) {
        throw new moodle_exception('Sorry, but no');
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
        self::check_file_within_quota($result);

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
        self::check_file_within_quota($result);

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

        // Note: We cannot rely on the result of $newfile as this only checks whether a file was present in filedir,
        // which may be empty.
        $sourcefile = $this->get_fullpath_from_hash($contenthash);
        $key = $this->get_contentpath_from_hash($contenthash);

        if ($filesize === 0 || is_dir($sourcefile)) {
            // We cannot push empty files or directories.
            return $result;
        }

        // Perform the headObject in a try/catch.
        // This saves an API call performing the doesObjectExist, which
        // performs the same operation as headObject anyway.
        try {
            // Fetch the head information.
            // If no file exists at the specified key, then a NoSuchKeyException is thrown.
            $start = microtime();
            $object = self::$client->headObject(array(
                    'Bucket'        => self::$bucket,
                    'Key'           => $key,
                ));

            // Compare the local file size against the remote ContentLength.
            $sizematch  = ($object->get('ContentLength') == $filesize);

            if ($sizematch) {
                // A copy of this file is already present, and it has a matching file size.
                // No point in uploading it again so return early.
                self::log_statistic('precheckmatch', array(
                        'logmessage'    => 'New file matched existing file in S3',
                        'contenthash'   => $contenthash,
                        'filesize'      => $filesize,
                        'time'          => microtime_diff($start, microtime()),
                    ));
                return $result;
            } else {
                // There's already a key present, but it has a different file size.
                // Better fail here for safety's sake.
                throw new file_pool_content_exception($contenthash);
            }
        } catch (NoSuchKeyException $e) {
            // Only catch the NoSuchKeyException exception.
            // There is no key here - upload the file.
            self::log_statistic('precheckfail', array(
                    'logmessage'    => 'Existing file not found when checking before upload',
                    'contenthash'   => $contenthash,
                    'filesize'      => $filesize,
                    'time'          => microtime_diff($start, microtime()),
                ));

            // We must use a file handle here. If we were to pass the path to the sourcefile to upload, the literal
            // string for the path would be saved as the file content.
            $fh = fopen($sourcefile, 'r');

            $start = microtime();
            self::$client->upload(self::$bucket, $key, $fh);
            self::log_statistic('uploaded', array(
                    'logmessage'    => 'New file uploaded to S3',
                    'contenthash'   => $contenthash,
                    'filesize'      => $filesize,
                    'time'          => microtime_diff($start, microtime()),
                ));

            // Note: No need to fclose here. The AWS API does it as part of the upload.
        }

        return $result;
    }

    /**
     * Returns information about image.
     * Information is determined from the file content
     *
     * @param stored_file $file The file to inspect
     * @return mixed array with width, height and mimetype; false if not an image
     */
    public function get_imageinfo($file) {
        if (!$this->is_image($file)) {
            return false;
        }

        return $this->get_imageinfo_from_path($this->get_presigned_url($file->get_contenthash()));
    }

    /**
     * Log some statistics to the logger.
     *
     * @param string $eventname
     * @param array $data
     */
    protected static function log_statistic($eventname, $data) {
        logger::log($eventname, $data, 'local_filestorage');
    }

    /**
     * Check whether a file is within the file system quota.
     *
     * @return void
     */
    protected function check_file_within_quota($result) {
        global $DB;
        if (defined('FILESTORAGE_QUOTA')) {
            list($contenthash, $filesize, $newfile) = $result;

            // Cannot rely upon $newfile as the file may not exist on the local file system.
            if (!$DB->record_exists('files', array('contenthash' => $contenthash))) {
                $current = self::unique_storage_size_used();
                if (($current + $filesize) > FILESTORAGE_QUOTA) {
                    throw new \local_filestorage\exception\quota_exception($current, $filesize);
                }
            }
        }
    }

    /**
     * Determine the current unique file storage size.
     *
     * @return int
     */
    public static function unique_storage_size_used() {
        global $DB;

        return $DB->get_field_sql("
            SELECT SUM(f.filesize)
              FROM (
                    SELECT DISTINCT
                                    filesize
                               FROM {files}
                              WHERE filearea <> 'draft' AND
                                     component <> 'tool_recyclebin' AND
                                     referencefileid IS NULL
                           GROUP BY filesize, contenthash
              ) AS f");
    }

}

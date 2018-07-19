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

require_once(dirname(dirname(__DIR__)) . '/sdk/aws-autoloader.php');

use stored_file;

use moodle_exception;
use file_pool_content_exception;
use local_filestorage\exception\quota_exception;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;

use local_logging\logger;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filestorage/file_system.php');

class file_system_s3 extends \file_system {

    /**
     * @var string $bucket The string name for the bucket.
     */
    protected static $bucket = null;

    /**
     * @var S3Client $client The S3Client instance.
     */
    protected static $client = null;

    /** @var string $filedir */
    protected static $filedir = null;

    public function __construct() {
        global $CFG;

        if (!isset($CFG->s3bucket)) {
            throw new moodle_exception('S3 not configured');
        }

        // Attempt to connect to S3.
        self::$client = new S3Client($CFG->awsconfig);
        self::$bucket = $CFG->s3bucket;
        self::$filedir = make_request_directory();
    }

    /**
     * Get the full path for the specified hash, including the path to the filedir.
     *
     * @param string $contenthash The content hash
     * @param bool $fetchifnotfound
     * @return string The full path to the content file
     */
    protected function get_local_path_from_hash($contenthash, $fetchifnotfound = false) {
        $path = self::$filedir . DIRECTORY_SEPARATOR . $contenthash;

        if ($fetchifnotfound && !is_readable($path)) {
            // The S3 API cannot fetch to an empty file.
            $this->fetch_local_copy($contenthash);
        }

        return $path;
    }

    /**
     * Get the full path for the specified hash, including the path to the filedir.
     *
     * @param string $contenthash The content hash
     * @return string The full path to the content file
     */
    public function get_remote_path_from_hash($contenthash) {
        return $this->get_presigned_url($contenthash);
    }

    /**
     * Get the content path for the specified content hash within filedir.
     *
     * This does not include the filedir, and is often used by file systems
     * as the object key for storage and retrieval.
     *
     * @param string $contenthash The content hash
     * @return string The filepath within filedir
     */
    protected function get_contentpath_from_hash($contenthash) {
        return $this->get_contentdir_from_hash($contenthash) . "/$contenthash";
    }

    /**
     * Get the content directory for the specified content hash.
     * This is the directory that the file will be in, but without the
     * fulldir.
     *
     * @param string $contenthash The content hash
     * @return string The directory within filedir
     */
    protected function get_contentdir_from_hash($contenthash) {
        $l1 = $contenthash[0] . $contenthash[1];
        $l2 = $contenthash[2] . $contenthash[3];
        return "$l1/$l2";
    }

    /**
     * Determine whether the file is present on the local file system somewhere.
     *
     * @param stored_file $file The file to ensure is available.
     * @return bool
     */
    public function is_file_readable_by_storedfile(stored_file $file) {
        if ($file->is_directory()) {
            // We cannot store directories in S3, so we cannot fetch them.
            return true;
        }

        return $this->is_file_readable_by_hash($file->get_contenthash());
    }

    /**
     * Determine whether the file is present on the local file system somewhere.
     *
     * @param stored_file $file The file to ensure is available.
     * @return bool
     */
    public function is_file_readable_remotely_by_storedfile(stored_file $file) {
        if (!$file->get_filesize()) {
            // Files with empty size are either directories or empty.
            // We handle these virtually.
            return true;
        }

        if ($this->get_remote_path_from_storedfile($file)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the file is available remotely.
     *
     * @param string $contenthash The contenthash of the file to check.
     * @return bool
     */
    public function is_file_readable_by_hash($contenthash) {
        if ($contenthash === sha1('')) {
            // We cannot store directories in S3, so we cannot fetch them.
            return true;
        }

        if ($this->is_file_readable_locally_by_hash($contenthash, false)) {
            return true;
        }

        return $this->is_file_readable_remotely_by_hash($contenthash);
    }

    public function is_file_readable_remotely_by_hash($contenthash) {
        try {
            self::$client->headObject([
                'Bucket' => self::$bucket,
                'Key' => $this->get_contentpath_from_hash($contenthash) . self::get_key_suffix_from_contenthash($contenthash),
            ]);

            return true;
        } catch (S3Exception $e) {
            if ($e->getAwsErrorCode() !== 'NotFound') {
                self::log_statistic(
                    'remotereadbyhashfail',
                    [
                        'logmessage' => 'Exception thrown while attempting to read file remotely by hash',
                        'contenthash' => $contenthash,
                        'AwsErrorCode' => $e->getAwsErrorCode(),
                        'errormessage' => $e->getMessage(),
                    ]
                );

                throw new moodle_exception('Unable to read file');
            }
        }

        return false;
    }

    /**
     * Handle readfile for a stored_file.
     *
     * @param stored_file $file The stored file.
     * @return int|bool
     */
    public function readfile(stored_file $file) {
        return readfile_allow_large($this->get_presigned_url($file->get_contenthash()), $file->get_filesize());
    }

    /**
     * Copy the content to the specified location.
     *
     * @param stored_file $file The file to copy.
     * @param string $target The full path to the new file.
     * @return bool The result of the copy operation.
     */
    public function copy_content_from_storedfile(stored_file $file, $target) {
        if ($this->is_file_readable_locally_by_storedfile($file)) {
            $source = $this->get_local_path_from_storedfile($file, true);
            return copy($source, $target);
        } else {
            // No point downloading, then copying. Just perform a straight download to the target.
            if ($this->fetch_local_copy($file->get_contenthash(), $target)) {
                return true;
            } else {
                // Something went wrong and, for some reason, an exception was not thrown.
                return false;
            }
        }
    }

    /**
     * Remove a file.
     *
     * @param string $contenthash
     */
    public function remove_file($contenthash) {
        global $CFG, $dynamicsite;
        require_once($CFG->moodlecloud_template_files_path);
        global $moodlecloud_template_files;

        // Never ever delete template files from a template site.
        if (isset($CFG->moodlecloud_is_template)) {
            return;
        }

        if (!self::is_file_removable($contenthash)) {
            // Don't remove the file - it's still in use.
            return;
        }

        if (in_array($contenthash, $moodlecloud_template_files)) {
            // Never remove template files.
            return;
        }

        $start = microtime();
        try {
            self::$client->deleteObject(
                [
                    'Bucket'        => self::$bucket,
                    'Key'           => $this->get_contentpath_from_hash($contenthash) . '_' . $dynamicsite,
                ]
            );
            self::log_statistic('deleted', [
                'logmessage'    => 'File deleted from S3',
                'contenthash'   => $contenthash,
                'time'          => microtime_diff($start, microtime()),
            ]);
        } catch (S3Exception $e) {
            self::log_statistic(
                'deletefail',
                [
                    'logmessage' => 'Exception thrown while attempting to delete file from S3',
                    'contenthash' => $contenthash,
                    'AwsErrorCode' => $e->getAwsErrorCode(),
                    'errormessage' => $e->getMessage(),
                    'time' => microtime_diff($start, microtime())
                ]
            );

            if ($e->getAwsErrorCode() !== 'NotFound') {
                throw new moodle_exception('Unable to remove file');
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
        if ($this->is_file_readable_locally_by_storedfile($file)) {
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
        $target = $this->get_local_path_from_hash($contenthash, false);
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

        // Attempt to fetch the file.
        $temptarget = $target . '.tmp';
        // The S3 API can only fetch to an existing file.
        touch($temptarget);

        try {
            $start = microtime();
            self::$client->getObject(array(
                'Bucket'    => self::$bucket,
                'Key'       => $this->get_contentpath_from_hash($contenthash) . self::get_key_suffix_from_contenthash($contenthash),
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
            @unlink($temptarget); // Just in case anything fails in a weird way.
        } catch (S3Exception $e) {
            self::log_statistic(
                'fetchlocalcopyfail',
                [
                    'logmessage' => 'Exception thrown while attempting to fetch local copy',
                    'contenthash' => $contenthash,
                    'AwsErrorCode' => $e->getAwsErrorCode(),
                    'errormessage' => $e->getMessage(),
                ]
            );

            return false;
        }

        return $target;
    }

    /**
     * Get an S3 pre-signed URL for the specified content hash.
     *
     * @param string $contenthash The contenthash to retrieve a URL for.
     * @return string The pre-signed URL.
     */
    protected function get_presigned_url($contenthash) {
        try {
            $key = $this->get_contentpath_from_hash($contenthash) . self::get_key_suffix_from_contenthash($contenthash);
            // This is still needed because getCommand doesn't throw an exception if the key doesn't exist.
            self::$client->headObject(array(
                'Bucket'        => self::$bucket,
                'Key'           => $key,
            ));

            // We generate a pre-signed URL for the file handle to use.
            $command = self::$client->getCommand('GetObject', [
                'Bucket'    => self::$bucket,
                'Key'       => $key
            ]);

            // It doesn't matter how long this presigned URL lasts as it is disposed of almost immediately.
            // It must be a sufficient period of time to allow slow reads of large files.
            $url = self::$client->createPresignedRequest($command, '+1 day');

            self::log_statistic('generatedurl', array(
                'logmessage'    => 'Generated pre-signed URL',
                'contenthash'   => $contenthash,
            ));

            return (string) $url->getUri();
        } catch (S3Exception $e) {
            self::log_statistic(
                'presignfaill',
                [
                    'logmessage' => 'Exception thrown while attempting to get presined URL',
                    'contenthash' => $contenthash,
                    'AwsErrorCode' => $e->getAwsErrorCode(),
                    'errormessage' => $e->getMessage()
                ]
            );

            return false;
        }
    }

    /**
     * Returns file handle - read only mode, no writing allowed into pool files!
     *
     * When you want to modify a file, create a new file and delete the old one.
     *
     * @param stored_file $file
     * @param int $type Type of file handle (FILE_HANDLE_xx constant)
     * @return resource file handle
     */
    public function get_content_file_handle(stored_file $file, $type = stored_file::FILE_HANDLE_FOPEN) {
        try {
            switch ($type) {
                case stored_file::FILE_HANDLE_FOPEN:
                    /**
                     * Open a seekable S3 stream using the AWS SDK.
                     * In order to allow previously read data to be recalled, data is buffered in a PHP
                     * temp stream using a stream decorator. When the amount of cached data exceeds 2MB,
                     * the data in the temp stream will transfer from memory to disk. Keep this in mind
                     * when downloading large files from Amazon S3 using the seekable stream context setting.
                     */

                    $key = $this->get_contentpath_from_hash($file->get_contenthash()) . self::get_key_suffix_from_contenthash($file->get_contenthash());

                    // Check the file exists.
                    self::$client->headObject(array(
                        'Bucket'        => self::$bucket,
                        'Key'           => $key,
                    ));

                    self::$client->registerStreamWrapper();
                    $context = stream_context_create([
                        's3' => ['seekable' => true]
                    ]);
                    $tmps3filepath = 's3://'. self::$bucket .'/'. $key;
                    $tmphandle = fopen($tmps3filepath, 'r', false, $context);
                    if ($tmphandle) {
                        // S3 seekable streams allow you to seek only to bytes that were previously read.
                        // Read the entirety of the file before returning the handle to Moodle for seeking.
                        while (!feof($tmphandle)) {
                            fread($tmphandle, 8192);
                        }
                        fseek($tmphandle, 0);
                    } else {
                        error_log('Failed to open the filehandle to S3: '. $tmps3filepath);
                    }
                    return $tmphandle;
                    break;
                default:
                    return self::get_file_handle_for_path(
                        $this->get_presigned_url($file->get_contenthash()),
                        $type
                    );
            }
        } catch (S3Exception $e) {
            self::log_statistic(
                'gethandlefail',
                [
                    'logmessage' => 'Exception thrown while attempting to get file handle',
                    'contenthash' => $file->get_contenthash(),
                    'AwsErrorCode' => $e->getAwsErrorCode(),
                    'errormessage' => $e->getMessage()
                ]
            );

            throw new moodle_exception('Cannot read file');
        }
    }

    /**
     * Add file content to sha1 pool.
     *
     * @param string $pathname path to file
     * @param string $contenthash sha1 hash of content if known (performance only)
     * @return array (contenthash, filesize, newfile)
     */
    public function add_file_from_path($pathname, $contenthash = NULL) {
        $contenthash = sha1_file($pathname);
        $filesize = filesize($pathname);
        if ($filesize === 0) {
            return [$contenthash, $filesize, true];
        }

        return $this->push_to_s3($pathname, $contenthash, $filesize);
    }

    /**
     * Add string content to sha1 pool.
     *
     * @param string $content file content - binary string
     * @return array (contenthash, filesize, newfile)
     */
    public function add_file_from_string($content) {
        $contenthash = sha1($content);
        $filesize = strlen($content);
        if ($filesize === 0) {
            return [$contenthash, 0, true];
        }

        if ($this->is_file_readable_remotely_by_hash($contenthash)) {
            return [$contenthash, $filesize, true];
        }

        $filepath = $this->get_local_path_from_hash($contenthash, false);
        file_put_contents($filepath, $content);

        return $this->push_to_s3($filepath, $contenthash, $filesize);
    }

    /**
     * Push the newly added file to S3.
     *
     * @param $sourcefile
     * @param null $contenthash
     * @param null $filesize
     * @return array $result The input is passed straight back out.
     * @throws file_pool_content_exception
     */
    private function push_to_s3($sourcefile, $contenthash = null, $filesize = null) {
        // Note: We cannot rely on the result of $newfile as this only checks whether a file was present in filedir,
        // which may be empty.
        $key = $this->get_contentpath_from_hash($contenthash) . self::get_key_suffix_from_contenthash($contenthash);

        $result = [$contenthash, $filesize, true];

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
                )
            );

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
        } catch (S3Exception $e) {
            if ($e->getAwsErrorCode() !== 'NotFound') {
                self::log_statistic(
                    'pushfail',
                    [
                        'logmessage' => 'Exception thrown while attempting to push file to S3',
                        'contenthash' => $contenthash,
                        'AwsErrorCode' => $e->getAwsErrorCode(),
                        'errormessage' => $e->getMessage(),
                        'time' => microtime_diff($start, microtime())
                    ]
                );

                throw new moodle_exception('Could not add file');
            } else {
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

                // New server side encryption option
                $options = ['params' => ['ServerSideEncryption' => 'AES256']];

                // ACL to apply to the object (default: private)
                $acl = 'private';

                $start = microtime();
                // Upload the file
                self::$client->upload(self::$bucket, $key, $fh, $acl, $options);
                // Log about it.
                self::log_statistic('uploaded', array(
                    'logmessage'    => 'New file uploaded to S3',
                    'contenthash'   => $contenthash,
                    'filesize'      => $filesize,
                    'time'          => microtime_diff($start, microtime()),
                ));

                // Note: No need to fclose here. The AWS API does it as part of the upload.
            }
        }

        return $result;
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

    protected static function get_key_suffix_from_contenthash(string $contenthash) : string {
        global $CFG, $dynamicsite;

        // Never add the suffix on template sites.
        if (isset($CFG->moodlecloud_is_template)) {
            return '';
        }

        require_once($CFG->moodlecloud_template_files_path);
        global $moodlecloud_template_files;

        return in_array($contenthash, $moodlecloud_template_files) ? '' : '_' . $dynamicsite;
    }

    /**
     * Determine the current unique file storage size.
     *
     * @return int
     */
    public static function unique_storage_size_used() {
        global $DB;

        /** @noinspection SqlDialectInspection */
        /** @noinspection SqlNoDataSourceInspection */
        return $DB->get_field_sql("
            SELECT SUM(f.filesize)
              FROM (
                    SELECT DISTINCT
                                    contenthash,
                                    filesize
                               FROM {files}
                              WHERE filearea <> 'draft' AND
                                     component <> 'tool_recyclebin' AND
                                     (component <> 'backup' OR mimetype <> 'application/vnd.moodle.backup') AND
                                     referencefileid IS NULL
                           GROUP BY filesize, contenthash
              ) AS f");
    }

}

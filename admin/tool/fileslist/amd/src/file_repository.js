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
 * A javascript module to retrieve a list of files from the server.
 *
 * @module     tool_fileslist/file_repository
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax'], function($, Ajax) {
    var cachedResponse;

    /**
     * Retrieve a list of files ordered by filesize.
     *
     * @method getFilesBySize
     * @return {promise} Resolved with an array of file objects.
     */
    var getFilesBySize = function() {
        return $.when(
            // If we already have the response cached, we can resolve with
            // that immediately, otherwise query the server.
            cachedResponse ||
                Ajax.call([
                    {
                        methodname: 'tool_fileslist_get_files_by_size',
                        args: []
                    }
                ])[0].then(function(response) {
                    cachedResponse = {
                        files: response.files
                            .filter(function(file) {
                                return file.filearea != 'draft';
                            })
                    };
                    return cachedResponse;
                })
        );
    };

    return {
        getFilesBySize: getFilesBySize
    };
});

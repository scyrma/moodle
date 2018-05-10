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
 * A javascript module to render a list of file objects.
 *
 * @module     tool_fileslist/fileslist
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['tool_fileslist/file_repository', 'core/templates'], function(FileRepository, Templates) {
    return {
        /**
         * Update the files list.
         *
         * @param {jQuery} root The root element of the files list.
         * @return {Promise} Resolved when the elements are attached to the DOM
         */
        updateList: function(root) {
            var start = +root.attr('data-start');
            var limit = +root.attr('data-limit');

            return FileRepository
                .getFilesBySize()
                .then(function(response) {
                    root.attr('data-num-items', response.files.length);
                    return Templates.render(
                        'tool_fileslist/fileslist-items',
                        {
                            files: response.files
                                .slice(start, start + limit)
                                .map(function(file) {
                                    var index = Math.floor(Math.log(file.size) / Math.log(1024));
                                    file.sizeHumanReadable = Math.round((file.size/Math.pow(1024, index))*100)/100
                                        + ' ' + ['B', 'KB', 'MB', 'GB'][index];

                                    return file;
                                })
                        }
                    );
                })
                .done(function(html, js) {
                    root.attr('data-start', start + limit);
                    Templates.replaceNodeContents(root.find('tbody'), html, js);
                });
        }
    };
});

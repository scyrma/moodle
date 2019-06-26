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

define(['jquery'], function($) {

    // Initialise permission header checkboxes actions on form.
    var initPositionPermHeaderChkbox = function() {
        $('input:checkbox.permission-header.global').on('click', function() {
            $('input:checkbox.permission.global').prop('checked', true);
        });

        $('input:checkbox.permission-header.department').on('click', function() {
            $('input:checkbox.permission.department').prop('checked', true);
        });
    };

    return {
        init: function() {
            initPositionPermHeaderChkbox();
        }
    };
});

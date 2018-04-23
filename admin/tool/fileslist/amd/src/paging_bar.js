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
 * A javascript module to manage pagination of the files list.
 *
 * @module     tool_fileslist/paging_bar
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'tool_fileslist/fileslist', 'core/custom_interaction_events', 'core/templates'], function ($, FilesList, CustomEvents, Templates) {
    var SELECTORS = {
        PAGE_ITEM: '[data-region="page-item"]',
        PAGER: ['data-region="pagination"']
    };

    var registerEventListeners = function(root, filesList) {
        CustomEvents.define(root, [
            CustomEvents.events.activate
        ]);

        root.on(CustomEvents.events.activate, SELECTORS.PAGE_ITEM, function(e, data) {
            var num = +$(e.currentTarget).attr('data-page-number');
            filesList.attr('data-start', num * filesList.attr('data-limit'));

            $(e.currentTarget).parent().children('.active').removeClass('active');
            $(e.currentTarget).parent().children('[data-page-number="' + num + '"]').addClass('active');
            $(e.currentTarget).parent().children(':first-child').attr('data-page-number', num-1).removeClass('active');
            $(e.currentTarget).parent().children(':last-child').attr('data-page-number', num+1).removeClass('active');

            if (num > 0) {
                $(e.currentTarget).parent().children(':first-child').removeClass("disabled");
            } else {
                $(e.currentTarget).parent().children(':first-child').addClass("disabled");
            }

            if (num+1 == Math.ceil(+filesList.attr('data-num-items')/+filesList.attr('data-limit'))) {
                $(e.currentTarget).parent().children(':last-child').addClass("disabled");
            } else {
                $(e.currentTarget).parent().children(':last-child').removeClass("disabled");
            }

            FilesList.updateList(filesList);
            data.originalEvent.preventDefault();
        });
    };

    return {
        init: function(root, filesList) {
            registerEventListeners(root, filesList);

            var numPages = Math.ceil(+filesList.attr('data-num-items')/+filesList.attr('data-limit'));

            if (numPages === 1) {
                return Templates.render('tool_fileslist/page-items');
            }

            var pages = [{
                active: false,
                disabled: true,
                number: 0,
                label: '«'
            }];
            for (var i = 0; i < numPages; i++) {
                pages.push({
                    active: i === 0,
                    disabled: false,
                    number: i,
                    label: i+1
                });
            }
            pages.push({
                active: false,
                disabled: numPages === 1 ? true : false,
                number: 1,
                label: '»'
            });

            return Templates.render('tool_fileslist/page-items', {pages: pages})
                .done(function(html, js) {
                    Templates.replaceNodeContents(root, html, js);
                });
        }
    };
});

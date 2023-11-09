<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

declare(strict_types=1);

namespace tool_catalogue\external;

use core\external\exporter;
use core_course\external\course_summary_exporter;
use core_course_category;
use core_course_list_element;
use html_writer;
use moodle_url;
use tool_catalogue\configuration;
use tool_catalogue\local\helpers\filters;
use tool_catalogue\local\helpers\search;

/**
 * Class catalogue_search_exporter
 *
 * @package    tool_catalogue
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 David Carrillo <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class catalogue_search_exporter extends exporter {

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'filters' => filters::class,
            'offset' => 'int?',
            'limit' => 'int',
            'isfrontpage' => 'bool?',
        ];
    }

    /**
     * Filters supplied to this exporter
     *
     * @return filters
     */
    protected function get_filters(): filters {
        return $this->related['filters'];
    }

    /**
     * Return the list of additional, generated dynamically from the given properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'islistview' => ['type' => PARAM_BOOL],
            'istilesview' => ['type' => PARAM_BOOL],
            'hasprices' => ['type' => PARAM_BOOL],
            'catalogueitems' => [
                'type' => [
                    'id' => ['type' => PARAM_INT],
                    'title' => ['type' => PARAM_TEXT],
                    'image' => ['type' => PARAM_RAW],
                    'url' => ['type' => PARAM_URL],
                    'fields' => [
                        'type' => [
                            'fieldname' => ['type' => PARAM_TEXT],
                            'content' => ['type' => PARAM_RAW],
                            'label' => ['type' => PARAM_RAW],
                            'isempty' => ['type' => PARAM_BOOL],
                            'showlabel' => ['type' => PARAM_BOOL],
                            'iscoursesummary' => ['type' => PARAM_BOOL],
                            'iscoursecontacts' => ['type' => PARAM_BOOL],
                            'istags' => ['type' => PARAM_BOOL],
                            'iscategory' => ['type' => PARAM_BOOL],
                            'iscustomfield' => ['type' => PARAM_BOOL],
                            'customfieldtype' => ['type' => PARAM_TEXT],
                        ],
                        'multiple' => true,
                    ],
                ],
                'multiple' => true,
            ],
        ];
    }

    /**
     * Other values
     *
     * @param \renderer_base $output
     * @return array
     */
    protected function get_other_values(\renderer_base $output): array {
        /** @var filters $filters */
        $filters = $this->get_filters();
        $offset = (int)$this->related['offset'] ?? 0;
        $limit = (int)$this->related['limit'] ?? configuration::get_courses_per_page_search();
        $courselist = [];

        // Get the list of fields to display for each course according to the view type.
        $fields = self::get_fields_for_view_type();

        $extracoursefields = [];
        if (in_array(configuration::FIELD_SUMMARY, $fields)) {
            $extracoursefields[] = 'summary';
            $extracoursefields[] = 'summaryformat';
        }

        $courses = search::get_courses($filters, $extracoursefields, $offset, $limit);

        // Preload course custom fields and contacts when needed.
        $displayedcustomfields = array_filter($fields, function ($field) {
            return strpos($field, configuration::FIELD_PREFIX_CUSTOM_FIELDS) === 0;
        });
        if (count($displayedcustomfields)) {
            core_course_category::preload_custom_fields($courses);
        }
        if (in_array(configuration::FIELD_CONTACTS, $fields)) {
            core_course_category::preload_course_contacts($courses);
        }

        foreach ($courses as $course) {
            $courseimage = course_summary_exporter::get_course_image($course);
            if (!$courseimage) {
                $courseimage = $output->get_generated_image_for_id($course->id);
            }

            $coursefields = [
                'id' => (int)$course->id,
                'title' => get_course_display_name_for_list($course),
                'image' => $courseimage,
                'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'fields' => [],
            ];

            // Add course custom fields.
            $coursefieldsvalues = $this->export_course_customfields($course->customfields ?? []);
            $customfields = configuration::get_all_course_custom_fields();

            // Export fields in the order they are configured.
            foreach ($fields as $field) {
                if (array_key_exists($field, $customfields)) {
                    if (array_key_exists($field, $coursefieldsvalues)) {
                        $coursefields['fields'][] = $coursefieldsvalues[$field];
                    }
                    continue;
                }
                switch ($field) {
                    case configuration::FIELD_CATEGORY:
                        $coursefields['fields'][] = $this->export_course_category($course);
                        break;
                    case configuration::FIELD_SUMMARY:
                        $coursefields['fields'][] = $this->export_course_summary($course);
                        break;
                    case configuration::FIELD_TAGS:
                        $coursefields['fields'][] = $this->export_course_tags((int)$course->id);
                        break;
                    case configuration::FIELD_CONTACTS:
                        $coursefields['fields'] = array_merge($coursefields['fields'],
                            $this->export_course_contacts($course));
                        break;
                    default:
                        throw new \coding_exception('Invalid field type value');
                }
            }

            $courselist[] = $coursefields;
        }

        return [
            'islistview' => $this->is_list_view(),
            'istilesview' => $this->is_tiles_view(),
            'catalogueitems' => $courselist,
            'hasprices' => in_array(configuration::FIELD_PREFIX_CUSTOM_FIELDS . 'price', $fields),
        ];
    }

    /**
     * Create a field from array values
     *
     * @param array $fieldvalues
     * @return array
     */
    private function create_field(array $fieldvalues): array {
        $result = $fieldvalues + [
            'fieldname' => '',
            'content' => '',
            'label' => '',
            'iscoursesummary' => 0,
            'iscoursecontacts' => 0,
            'istags' => 0,
            'iscategory' => 0,
            'iscustomfield' => 0,
        ];

        $result['isempty'] = strlen($result['content'] ?? '') === 0;
        $result['showlabel'] = self::get_show_label($result['fieldname']);

        return $result;
    }

    /**
     * Return the value for show label
     *
     * @param string $field
     * @return int
     */
    private function get_show_label(string $field): int {
        // TODO: WP-4377 take from settings.
        switch ($field) {
            case configuration::FIELD_SUMMARY:
            case configuration::FIELD_TAGS:
            case configuration::FIELD_PREFIX_CUSTOM_FIELDS . 'price':
                return 0;
            case configuration::FIELD_CONTACTS:
            case configuration::FIELD_CATEGORY:
            case configuration::FIELD_SUBCATEGORY:
            default:
                return 1;
        }
    }

    /**
     * Get fields for view type
     *
     * @return array
     */
    private function get_fields_for_view_type(): array {
        return $this->is_list_view() ?
            configuration::get_display_fields_list() :
            configuration::get_display_fields_tiles();
    }

    /**
     * Export course customfields
     *
     * @param \customfield_text\data_controller[] $customfields
     * @return array
     */
    private function export_course_customfields(array $customfields): array {
        global $PAGE;

        $exportedcustomfields = [];
        foreach ($customfields as $customfield) {
            $value = (string)$customfield->export_value();
            $fieldname = configuration::FIELD_PREFIX_CUSTOM_FIELDS . $customfield->get_field()->get('shortname');
            $fieldtype = $customfield->get_field()->get('type');
            $content = $this->strip_html_tags($fieldname, $value);
            $exportedcustomfields[$fieldname] = $this->create_field([
                'fieldname' => $fieldname,
                'content' => $content,
                'label' => $customfield->get_field()->get_formatted_name(),
                'iscustomfield' => 1,
                'customfieldtype' => $fieldtype,
            ]);
        }

        return $exportedcustomfields;
    }

    /**
     * Exports course tags
     *
     * @param int $courseid
     * @return array
     */
    private function export_course_tags(int $courseid): array {
        $tags = \core_tag_tag::get_item_tags_array('core', 'course', $courseid);
        $tags = array_map(function ($tag) {
            // Display just tags names, without the links for now (later we may combine it with tags filters).
            return '<li>' . html_writer::span($tag, 'badge badge-info') . "</li>\n";
        }, $tags);

        $content = '';
        if (count($tags)) {
            $content = html_writer::tag('ul', implode('', $tags), ['class' => 'inline-list']);
        }

        return $this->create_field([
            'fieldname' => configuration::FIELD_TAGS,
            'content' => $content,
            'label' => get_string('tags'),
            'istags' => 1,
        ]);
    }

    /**
     * Returns course contacts
     *
     * @param \stdClass $course
     * @return array
     */
    private function export_course_contacts(\stdClass $course): array {
        $result = [];
        if (count($course->managers)) {
            $coursecontacts = (new core_course_list_element($course))->get_course_contacts();
            foreach ($coursecontacts as $coursecontact) {
                $rolenames = array_map(function ($role) {
                    return $role->displayname;
                }, $coursecontact['roles']);
                $label = implode(", ", $rolenames);
                $name = html_writer::link(new moodle_url('/user/view.php',
                        ['id' => $coursecontact['user']->id, 'course' => SITEID]),
                        $coursecontact['username']);
                $result[] = $this->create_field([
                    'fieldname' => configuration::FIELD_CONTACTS,
                    'content' => $name,
                    'label' => $label,
                    'iscoursecontacts' => 1,
                ]);
            }
        } else {
            $result[] = $this->create_field([
                'fieldname' => configuration::FIELD_CONTACTS,
                'content' => '',
                'label' => get_string('coursecontact', 'admin'),
                'iscoursecontacts' => 1,
            ]);
        }
        return $result;
    }

    /**
     * Returns course summary
     *
     * @param \stdClass $course
     * @return array
     */
    private function export_course_summary(\stdClass $course): array {
        \context_helper::preload_from_record($course);
        $context = \context_course::instance($course->id);
        $summary = file_rewrite_pluginfile_urls($course->summary, 'pluginfile.php', $context->id,
            'course', 'summary', null);
        $summary = format_text($summary, $course->summaryformat, ['context' => $context]);
        $summary = $this->strip_html_tags(configuration::FIELD_SUMMARY, $summary);
        $truncate = configuration::get_truncate_summary();
        if ($truncate > 0) {
            // Purify truncation to make sure we do not have unclosed html tags after truncation.
            $summary = purify_html(shorten_text($summary, $truncate));
        }
        return $this->create_field([
            'fieldname' => configuration::FIELD_SUMMARY,
            'content' => $summary,
            'label' => get_string('coursesummary', 'moodle'),
            'iscoursesummary' => 1,
        ]);
    }

    /**
     * Returns course category
     *
     * @param \stdClass $course
     * @return array
     */
    private function export_course_category(\stdClass $course): array {
        $category = core_course_category::get((int)$course->category, IGNORE_MISSING);
        return $this->create_field([
            'fieldname' => configuration::FIELD_CATEGORY,
            'content' => $category ? $category->get_formatted_name() : '',
            'label' => get_string('coursecategory', 'moodle'),
            'iscategory' => 1,
        ]);
    }

    /**
     * Strip html tags from the field if needed
     *
     * @param string $field
     * @param string $html
     * @return string
     */
    private function strip_html_tags(string $field, string $html): string {
        $value = $this->is_list_view() ?
            configuration::get_allow_html_tags_list($field) :
            configuration::get_allow_html_tags_tiles($field);

        if ($value === configuration::OPTION_HTML_TAGS_NONE) {
            return strip_tags($html);
        } else if ($value === configuration::OPTION_HTML_TAGS_ALL) {
            return $html;
        } else {
            return strip_tags($html, configuration::get_safe_html_tags());
        }
    }

    /**
     * Is 'list' view used to display the items
     *
     * @return bool
     */
    private function is_list_view(): bool {
        $filters = $this->get_filters();
        $isfrontpage = $this->related['isfrontpage'] ?? false;

        return !$isfrontpage && ($filters->get_search_string() !== null || $filters->get_categoryid() > 0);
    }

    /**
     * Is 'tiles' view used to display the items
     *
     * @return bool
     */
    private function is_tiles_view(): bool {
        return !$this->is_list_view();
    }
}

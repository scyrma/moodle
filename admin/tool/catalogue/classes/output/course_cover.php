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
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

declare(strict_types=1);

namespace tool_catalogue\output;

use context_course;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use tool_catalogue\external\course_exporter;
use tool_catalogue\router;

/**
 * Class to prepare the course cover for display.
 *
 * @package     tool_catalogue
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Bas Brands <bas@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_cover implements templatable, renderable {

    /** @var stdClass The course instance */
    private $course;

    /** @var bool Whether to show sections collapsed */
    private $collapsesections;

    /**
     * Constructor.
     *
     * @param stdClass $course
     * @param bool $collapsesections
     */
    public function __construct(stdClass $course, bool $collapsesections = false) {
        $this->course = $course;
        $this->collapsesections = $collapsesections;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return object Export data as an object.
     */
    public function export_for_template(renderer_base $output) {
        global $USER;

        $data = (object)[];

        $context = context_course::instance($this->course->id);
        $related = [
            'context' => $context,
            'course' => $this->course,
        ];
        $exporter = new course_exporter(null, $related);
        $coursedata = $exporter->export($output);

        $data->courseid = (int) $this->course->id;
        $data->userid = $USER->id;

        // TODO probably request this data only for course cover to avoid queries not needed on my courses main page.
        $data->linkedprograms = $coursedata->linkedprograms;
        $data->trainers = $coursedata->trainers;

        $data->pagesectiondates = (object)[];
        $data->pagesectiondates->collapse = $this->collapsesections;
        $data->startdate = userdate($coursedata->course->startdate, get_string('strftimedatefullshort', 'langconfig'));
        $data->enddate = userdate($coursedata->duedate, get_string('strftimedatefullshort', 'langconfig'));

        if (!empty($coursedata->coursefiles)) {
            $data->coursefiles = $coursedata->coursefiles;
            $data->pagesectionfiles = (object)[];
            $data->pagesectionfiles->collapse = $this->collapsesections;
        }

        // Help section of the program info page.
        $data->hashelp = !empty($data->linkedprograms);
        if ($data->hashelp) {
            $data->helpimage = new moodle_url('/admin/tool/catalogue/pix/help_course.svg');
            $data->helptitle = get_string('coursecoverhelpmultiprogram', 'tool_catalogue');
            $data->helptext = get_string('coursecoverhelptext', 'tool_catalogue');

            // Set the title for the help box.
            $data->multiprogram = (count($data->linkedprograms) > 1);
            if (count($data->linkedprograms) == 1) {
                $data->helptitle = get_string('coursecoverhelp', 'tool_catalogue', $data->linkedprograms[0]['name']);
            }

            // Get the help box links.
            $linkstring = $data->multiprogram ? 'programlink' : 'programlinksingle';
            $data->programlinks = [];
            $maxlinks = 2;
            $linkcount = 0;
            $data->nummore = count($data->linkedprograms) - $maxlinks;
            foreach ($data->linkedprograms as $program) {
                $hidden = false;
                if ($linkcount >= $maxlinks) {

                    $hidden = true;
                    $data->showmorelink = true;
                }
                $data->programlinks[] = [
                    'url' => $program['url'],
                    'string' => get_string($linkstring, 'tool_catalogue', $program['name']),
                    'hidden' => $hidden,
                ];
                $linkcount++;
            }
        }

        // Course description.
        $description = file_rewrite_pluginfile_urls($this->course->summary, 'pluginfile.php', $context->id, 'course',
            'summary', null);
        $data->description = format_text($description, $this->course->summaryformat, ['context' => $context]);
        if (!empty($data->description)) {
            $data->pagesectiondescription = (object)[];
            $data->pagesectiondescription->collapse = false;
        }
        if (!empty($data->trainers)) {
            $data->pagesectiontrainers = (object)[];
            $data->pagesectiontrainers->collapse = $this->collapsesections;
        }
        $data->courseurl = router::build_course_url((int) $this->course->id);

        return $data;
    }
}

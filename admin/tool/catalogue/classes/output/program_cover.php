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

use moodle_url;
use renderable;
use renderer_base;
use templatable;
use tool_catalogue\external\program_exporter;
use tool_catalogue\manager;
use tool_catalogue\router;
use tool_program\persistent\program;

/**
 * Class to prepare the program cover for display.
 *
 * @package     tool_catalogue
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Bas Brands <bas@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_cover implements templatable, renderable {

    /** @var program The program instance */
    private $program;

    /**
     * Constructor.
     *
     * @param program $program
     */
    public function __construct(program $program) {
        $this->program = $program;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return object Export data as an object.
     */
    public function export_for_template(renderer_base $output) {
        global $CFG, $USER;
        $related = [
            'userid' => (int) $USER->id,
            'context' => $this->program->get_context(),
            'program' => $this->program,
            'allocations' => manager::get_user_allocations((int) $USER->id, $this->program->get('id')),
        ];
        $exporter = new program_exporter(null, $related);
        $data = $exporter->export($output);

        $data->certimage = file_get_contents($CFG->dirroot . '/' . $CFG->admin . '/tool/catalogue/pix/certificates.svg');

        // Do not show the program cover buttons when the program cover is in the information tab.
        $data->hasbuttons = !get_user_preferences('tool_catalogue_show_program_content_' . $this->program->get('id'), false);

        // Help section of the program info page.
        $data->hashelp = !get_user_preferences('tool_catalogue_hide_program_cover_help', false);
        if ($data->hashelp) {
            user_preference_allow_ajax_update('tool_catalogue_hide_program_cover_help', PARAM_BOOL);
            $data->helpid = 'tool_catalogue_hide_program_cover_help';
            $data->helpimage = new moodle_url('/admin/tool/catalogue/pix/help_program.svg');
            $data->helpurl = get_docs_url('Programs');
            $data->helptitle = get_string('programhelptitle', 'tool_catalogue');
            $data->helptext = get_string('programhelptext', 'tool_catalogue');
        }

        $data->returnurl = new moodle_url('/my/courses.php');
        $data->programurl = router::build_program_url($this->program->get('id'));
        $data->userid = $USER->id;

        $showcontent = get_user_preferences('tool_catalogue_show_program_content_' . $this->program->get('id'), false);

        // These objects are used in the program_cover template but the variables are
        // used in the enclosed pagesection template to control if the content is
        // collapsed or not.
        $data->pagesectionstructure = (object)[];
        $data->pagesectionstructure->collapse = !$showcontent;

        if (!empty($data->certifications)) {
            $data->pagesectioncertifications = (object) [];
            $data->pagesectioncertifications->collapse = !$showcontent;
        }

        $data->pagesectiondates = (object)[];
        $data->pagesectiondates->collapse = !$showcontent;

        $data->hastags = !empty($data->tags);

        if (!empty($data->description) || $data->hastags || count($data->customfields)) {
            $data->pagesectionabout = (object)[];
            $data->pagesectionabout->collapse = false;
        }

        return $data;
    }
}

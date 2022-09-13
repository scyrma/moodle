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
use paging_bar;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use tool_catalogue\constants;
use tool_catalogue\external\catalogue_exporter;

/**
 * My courses output class
 *
 * @package     tool_catalogue
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Bas Brands <bas@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class my_courses implements renderable, templatable {

    /** @var int $page */
    protected $page;

    /** @var string $sort */
    protected $sort;

    /** @var string $filter */
    protected $filter;

    /** @var string $search */
    protected $search;

    /**
     * Class constructor
     *
     * @param int $page
     * @param string $sort
     * @param string $filter
     * @param string $search
     */
    public function __construct(int $page, string $sort, string $filter, string $search) {
        $this->page = $page;
        $this->sort = $sort;
        $this->filter = $filter;
        $this->search = $search;
    }

    /**
     * Export report data suitable for a template
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER;

        $perpage = 10;
        $showpaging = false;

        $template = (object)[];

        // TODO: We need to diferenciate between catalogue/programcontent pages for course items. Better way to do it?
        $template->iscatalogue = true;

        $template->urlsortname = '';

        $params = [
            'page' => $this->page,
            'sort' => $this->sort,
            'filter' => $this->filter,
            'search' => $this->search
        ];
        $fullurl = new moodle_url('/my/courses.php', $params);

        $paramoptions = [
            'sort' => [
                constants::SORT_NAME,
                constants::SORT_DUEDATE,
                constants::SORT_LASTACCESS
            ],
            'filter' => [
                constants::FILTER_ALL,
                constants::FILTER_COURSES,
                constants::FILTER_PROGRAMS,
                constants::FILTER_COMPLETE,
                constants::FILTER_INCOMPLETE
            ],
        ];
        // Generate template parameters like 'urlfilterall'.
        foreach ($paramoptions as $param => $options) {
            foreach ($options as $option) {
                $varname = 'url' . $param . $option;
                $newurl = clone($fullurl);
                $newurl->param($param, $option);
                $template->$varname = $newurl;
            }
        }

        $template->baseurl = $fullurl;
        $searchurl = new moodle_url('/my/courses.php');
        $template->searchinput = [
            "action" => $searchurl->out(),
            "inputname" => "search",
            "searchstring" => get_string('searchplaceholder', 'tool_catalogue'),
            "query" => $this->search,
            "hiddenfields" => [
                ['name' => 'page', 'value' => $this->page],
                ['name' => 'sort', 'value' => $this->sort],
                ['name' => 'filter', 'value' => $this->filter],
            ],
        ];

        // Get required related data for the exporter.
        $related = [
            'userid' => (int) $USER->id,
            'filter' => $this->filter,
            'sort' => $this->sort,
            'search' => $this->search,
        ];

        // Call the 'my_courses' exporter with the related data.
        $exporter = new catalogue_exporter(null, $related);

        // Return exporters export.
        $export = $exporter->export($output);
        $exporteditems = $export->listitems;

        $template->statusfilterstr = get_string($this->filter, 'tool_catalogue');
        $template->sortingfilterstr = get_string($this->sort, 'tool_catalogue');

        // Pagination.
        if (count($exporteditems) > $perpage) {
            $showpaging = true;
            $template->listitems = array_slice($exporteditems, $this->page * $perpage, $perpage);
        } else {
            $template->listitems = array_values($exporteditems);
        }

        if (!empty($this->search)) {
            foreach ($template->listitems as &$item) {
                $item['fullname'] = highlightfast($this->search, $item['fullname']);
            }
        }

        // Add pagination.
        if ($showpaging) {
            $pagingbar = new paging_bar(count($exporteditems), $this->page, $perpage, $fullurl);
            $template->pagingbar = $output->render($pagingbar);
        }

        return $template;
    }
}

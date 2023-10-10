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

namespace tool_catalogue\local\helpers;

use context;
use moodle_url;

/**
 * Represents the course catalogue filters (categoryid, searchstring and any custom filters).
 *
 * @package     tool_catalogue
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class filters {
    /** @var int $categoryid */
    protected $categoryid = 0;
    /** @var string|null $searchstring */
    protected $searchstring = null;

    /**
     * Creates an instance from the $_REQUEST parameters on /course/search.php page
     *
     * @return filters
     */
    public static function create_from_page_course_search(): filters {
        $searchstring = trim(optional_param('q', '', PARAM_RAW));
        $filters = new self();
        if ($searchstring !== '') {
            $filters->searchstring = $searchstring;
        }
        return $filters;
    }

    /**
     * Creates an instance from the $_REQUEST parameters on /course/index.php page
     *
     * @return filters
     */
    public static function create_from_page_course_index(): filters {
        $categoryid = optional_param('categoryid', 0, PARAM_INT);
        $filters = new self();
        $filters->categoryid = $categoryid;
        return $filters;
    }

    /**
     * Returns the URL to the page with the filters applied.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        if ($this->searchstring !== null) {
            $url = new moodle_url('/course/search.php', ['q' => $this->searchstring]);
        } else {
            $url = new moodle_url('/course/index.php');
            if ($this->categoryid) {
                $url->param('categoryid', $this->categoryid);
            }
        }
        return $url;
    }

    /**
     * Returns the search string
     *
     * @return string|null
     */
    public function get_search_string(): ?string {
        return $this->searchstring;
    }

    /**
     * Returns the category id
     *
     * @return int
     */
    public function get_categoryid(): int {
        return $this->categoryid ?? 0;
    }

    /**
     * Converts the object to JSON to be passed as data- attribute in HTML
     *
     * @return string
     */
    public function export_as_json(): string {
        return json_encode([
            'categoryid' => $this->get_categoryid(),
            'searchstring' => $this->get_search_string(),
        ]);
    }

    /**
     * Restores the object from JSON
     *
     * @param string $json
     * @return filters
     */
    public static function create_from_json(string $json): filters {
        $params = @json_decode($json, true);
        if (!$params || !is_array($params)) {
            $params = [];
        }
        $filters = new self();
        if (isset($params['searchstring'])) {
            $filters->searchstring = trim((string)$params['searchstring']);
        }
        $filters->categoryid = clean_param($params['categoryid'] ?? 0, PARAM_INT);
        return $filters;
    }

    /**
     * Context of this page (for the fragment API)
     *
     * @return context
     */
    public function get_context(): context {
        if ($this->categoryid) {
            return \context_coursecat::instance($this->categoryid);
        } else {
            return \context_system::instance();
        }
    }
}

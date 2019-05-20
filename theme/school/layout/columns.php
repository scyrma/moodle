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
 * The columns layout for the classic theme.
 *
 * @package   theme_classic
 * @copyright 2018 Bas Brands
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$bodyattributes = $OUTPUT->body_attributes();
$blockspre = $OUTPUT->blocks('side-pre');
$blockspost = $OUTPUT->blocks('side-post');

$hassidepre = $PAGE->blocks->region_has_content('side-pre', $OUTPUT);
$hassidepost = $PAGE->blocks->region_has_content('side-post', $OUTPUT);

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockspre,
    'sidepostblocks' => $blockspost,
    'haspreblocks' => $hassidepre,
    'haspostblocks' => $hassidepost,
    'bodyattributes' => $bodyattributes,
    'coursefooter' => $this->course_footer(),
    'doclinks' => $this->page_doc_link(),
    'logininfo' => $this->login_info(),
    'standardfooterhtml' => $this->standard_footer_html(),
];

$leftfootnote = get_config('theme_school', 'leftfootnote');
if (!empty($leftfootnote)) {
    $templatecontext['leftfootnote'] = $leftfootnote;
}

$footnote = get_config('theme_school', 'footnote');
if (!empty($footnote)) {
    $templatecontext['footnote'] = format_text($footnote);
}

$footnotelinks = array();
for ($i = 1; $i <= 6; $i++) {
    $text = get_config('theme_school', sprintf('leftfootnotesection%d', $i));
    $url = get_config('theme_school', sprintf('leftfootnotesectionlink%d', $i));

    if (!empty($text) && !empty($url)) {
        $footnotelinks[] = array('text' => $text, 'url' => $url);
    }
}

$templatecontext['footnotelinks'] = $footnotelinks;

// Determine whether the 2nd column has content or not.
$templatecontext['hasleftfootnotes'] = !empty($footnotelinks) || !empty(strip_tags($leftfootnote));

$templatecontext['facebookurl'] = get_config('theme_school', 'facebook');
$templatecontext['twitterurl'] = get_config('theme_school', 'twitter');
$templatecontext['googleplusurl'] = get_config('theme_school', 'googleplus');
$templatecontext['youtubeurl'] = get_config('theme_school', 'youtube');
$templatecontext['address'] = get_config('theme_school', 'contactaddress');
$templatecontext['phone'] = get_config('theme_school', 'contactphone');
$templatecontext['email'] = get_config('theme_school', 'contactemail');
$templatecontext['hascontacts'] = !empty($templatecontext['facebookurl']) || !empty($templatecontext['twitterurl']) || !empty($templatecontext['googleplusurl'])
        || !empty($templatecontext['youtubeurl']) || !empty($templatecontext['address']) || !empty($templatecontext['phone']) || !empty($templatecontext['email']);

echo $OUTPUT->render_from_template('theme_school/columns', $templatecontext);


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

$renderer = $PAGE->get_renderer('theme_school');

$widgettorender = get_config('theme_school', 'frontpageimagecontent');

if ($widgettorender == true) {
    $heroslider = new \theme_school\output\heroslider();
    $widgets = (object) [
            'object' => $renderer->render($heroslider)];
} else {
    $herostatic = new \theme_school\output\herostatic();
    $widgets = (object) [
            'object' => $renderer->render($herostatic)];
}

$frontpagesettingsurl = new \moodle_url('/admin/settings.php', array('section' => 'themesettingschool', 'activetab' => 'theme_school_frontpage'));
$frontpagesiteadminurl = new \moodle_url('/admin/');

$context = array(
        'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
        'output' => $OUTPUT,
        'bodyattributes' => $bodyattributes,
        'widgets' => $widgets,
        'coursefooter' => $this->course_footer(),
        'doclinks' => $this->page_doc_link(),
        'logininfo' => $this->login_info(),
        'standardfooterhtml' => $this->standard_footer_html(),
        'isadmin' => is_siteadmin(),
        'frontpagesettingsurl' => $frontpagesettingsurl->out(),
        'frontpagesiteadminurl' => $frontpagesiteadminurl->out(),
);

$leftfootnote = get_config('theme_school', 'leftfootnote');
if (!empty($leftfootnote)) {
    $context['leftfootnote'] = $leftfootnote;
}

$footnote = get_config('theme_school', 'footnote');
if (!empty($footnote)) {
    $context['footnote'] = format_text($footnote);
}

$footnotelinks = array();
for ($i = 1; $i <= 6; $i++) {
    $text = get_config('theme_school', sprintf('leftfootnotesection%d', $i));
    $url = get_config('theme_school', sprintf('leftfootnotesectionlink%d', $i));

    if (!empty($text) && !empty($url)) {
        $footnotelinks[] = array('text' => $text, 'url' => $url);
    }
}

$context['footnotelinks'] = $footnotelinks;

// Determine whether the 2nd column has content or not.
$context['hasleftfootnotes'] = !empty($footnotelinks) || !empty(strip_tags($leftfootnote));

$context['facebookurl'] = get_config('theme_school', 'facebook');
$context['twitterurl'] = get_config('theme_school', 'twitter');
$context['googleplusurl'] = get_config('theme_school', 'googleplus');
$context['youtubeurl'] = get_config('theme_school', 'youtube');
$context['address'] = get_config('theme_school', 'contactaddress');
$context['phone'] = get_config('theme_school', 'contactphone');
$context['email'] = get_config('theme_school', 'contactemail');
$context['hascontacts'] = !empty($context['facebookurl']) || !empty($context['twitterurl']) || !empty($context['googleplusurl'])
        || !empty($context['youtubeurl']) || !empty($context['address']) || !empty($context['phone']) || !empty($context['email']);

echo $OUTPUT->render_from_template('theme_school/frontpage', $context);

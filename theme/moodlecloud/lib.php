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
 * Theme moodlecloud lib.
 *
 * @package    theme_moodlecloud
 * @copyright  2014 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extra LESS code to inject.
 *
 * This will generate some LESS code from the settings used by the user. We cannot use
 * the {@link theme_moodlecloud_less_variables()} here because we need to create selectors or
 * alter existing ones.
 *
 * @param theme_config $theme The theme config object.
 * @return string Raw LESS code.
 */
function theme_moodlecloud_extra_less($theme) {
    $content = '';
    $imageurl = $theme->setting_file_url('backgroundimage', 'backgroundimage');
    // Sets the background image, and its settings.
    if (!empty($imageurl)) {
        $content .= 'body { ';
        $content .= "background-image: url('$imageurl');";
        if (!empty($theme->settings->backgroundfixed)) {
            $content .= 'background-attachment: fixed;';
        }
        if (!empty($theme->settings->backgroundposition)) {
            $content .= 'background-position: ' . str_replace('_', ' ', $theme->settings->backgroundposition) . ';';
        }
        if (!empty($theme->settings->backgroundrepeat)) {
            $content .= 'background-repeat: ' . $theme->settings->backgroundrepeat . ';';
        }
        $content .= ' }';
    }
    // If there the user wants a background for the content, we need to make it look consistent,
    // therefore we need to round its borders, and adapt the border colour.
    if (!empty($theme->settings->contentbackground)) {
        $content .= '
            #region-main {
                .well;
                background-color: ' . $theme->settings->contentbackground . ';
                border-color: darken(' . $theme->settings->contentbackground . ', 7%);
            }';
    }
    return $content;
}

/**
 * Returns variables for LESS.
 *
 * We will inject some LESS variables from the settings that the user has defined
 * for the theme. No need to write some custom LESS for this.
 *
 * @param theme_config $theme The theme config object.
 * @return array of LESS variables without the @.
 */
function theme_moodlecloud_less_variables($theme) {
    $variables = array();
    if (!empty($theme->settings->bodybackground)) {
        $variables['bodyBackground'] = $theme->settings->bodybackground;
    }
    if (!empty($theme->settings->textcolor)) {
        $variables['textColor'] = $theme->settings->textcolor;
    }
    if (!empty($theme->settings->linkcolor)) {
        $variables['linkColor'] = $theme->settings->linkcolor;
    }
    if (!empty($theme->settings->secondarybackground)) {
        $variables['wellBackground'] = $theme->settings->secondarybackground;
    }
    return $variables;
}

/**
 * Parses CSS before it is cached.
 *
 * This function can make alterations and replace patterns within the CSS.
 *
 * @param string $css The CSS
 * @param theme_config $theme The theme config object.
 * @return string The parsed CSS The parsed CSS.
 */
function theme_moodlecloud_process_css($css, $theme) {

    // Set the background image for the logo.
    $logo = $theme->setting_file_url('logo', 'logo');
    $css = theme_moodlecloud_set_logo($css, $logo);

    // Set custom CSS.
    if (!empty($theme->settings->customcss)) {
        $customcss = $theme->settings->customcss;
    } else {
        $customcss = null;
    }
    $css = theme_moodlecloud_set_customcss($css, $customcss);

    return $css;
}

/**
 * Adds the logo to CSS.
 *
 * @param string $css The CSS.
 * @param string $logo The URL of the logo.
 * @return string The parsed CSS
 */
function theme_moodlecloud_set_logo($css, $logo) {
    $tag = '[[setting:logo]]';
    $replacement = $logo;
    if (is_null($replacement)) {
        $replacement = '';
    }

    $css = str_replace($tag, $replacement, $css);

    return $css;
}

/**
 * Serves any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_moodlecloud_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel == CONTEXT_SYSTEM && ($filearea === 'logo' || $filearea === 'backgroundimage')) {
        $theme = theme_config::load('moodlecloud');
        // By default, theme files must be cache-able by both browsers and proxies.
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    } else {
        send_file_not_found();
    }
}

/**
 * Adds any custom CSS to the CSS before it is cached.
 *
 * @param string $css The original CSS.
 * @param string $customcss The custom CSS to add.
 * @return string The CSS which now contains our custom CSS.
 */
function theme_moodlecloud_set_customcss($css, $customcss) {
    $tag = '[[setting:customcss]]';
    $replacement = $customcss;
    if (is_null($replacement)) {
        $replacement = '';
    }

    $css = str_replace($tag, $replacement, $css);

    return $css;
}

function theme_moodlecloud_is_teacher($context) {
    // The same capability as is used with page_doc_link().
    return has_capability('moodle/site:doclinks', $context);
}

function theme_moodlecloud_get_footerlinks($context) {
    global $OUTPUT;

    $s = $OUTPUT->page_doc_link();

    if (theme_moodlecloud_is_teacher($context)) {
        $title = get_string('supportforums', 'theme_moodlecloud');
        $s .= " | <a href='https://moodle.org/community' target='_blank'>$title</a>";
    }
    if (is_siteadmin()) {
        $title = get_string('reportproblem', 'theme_moodlecloud');
        //$s .= " | <a href='#' target='_blank'>$title</a>";
        $s .= " | $title";

        $title = get_string('faq', 'theme_moodlecloud');
        //$s .= " | <a href='#' target='_blank'>$title</a>";
        $s .= " | $title";
    }
    return $s;
}

function theme_moodlecloud_get_readspeaker() {
    global $OUTPUT;

    $protocol = (!empty($_SERVER['HTTPS']) || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
	$slink = (!empty($_SERVER['HTTPS']) || $_SERVER['SERVER_PORT'] == 443) ? "sf1-" : "f1.";
	$region = (!empty($_SERVER['HTTPS']) || $_SERVER['SERVER_PORT'] == 443) ? "" : ".eu";

	$pageURL = $protocol.$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	$encodedURL=urlencode($pageURL);

	$dr_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', dirname(__FILE__))).'/docreader/proxy.php';

    $cid = 8018;
    $lang = 'en_au';
    $readid = 'region-main';

	$s = '<div style="text-align: center;width:100%;">
	<div style="display: inline-block;">

	<script type="text/javascript">window.rsConf = {general: {usePost: true}}; window.rsDocReaderConf = {proxypath: "'.$dr_path.'"}</script><script src="'.$protocol.$slink.'eu.readspeaker.com/script/'.$cid.'/ReadSpeaker.js?pids=embhl,dr&amp;skin=ReadSpeakerCompactSkin" type="text/javascript"></script>
	<div id="readspeaker_button1" class="rs_skip rsbtn rs_preserve">
	<a accesskey="L" class="rsbtn_play" title="Listen to this page using ReadSpeaker" href="'.$protocol.'app'.$region.'.readspeaker.com/cgi-bin/rsent?customerid='.$cid.'&amp;lang='.$lang.'&amp;readid='.$readid.'&amp;url='.$encodedURL.'">
	<span class="rsbtn_left rspart"><span class="rsbtn_text"><span>Listen</span></span></span>
    <span class="rsbtn_right rsplay rspart"></span>
	</a>
	</div>

	</div></div>';
    return $s;
}

/**
 * The ads for teachers and admins require some JS to be added to the page header.
 */
function theme_moodlecloud_get_ad_header($context) {
    $s = null;
    if (theme_moodlecloud_is_teacher($context)) {
        $s = "<script type='text/javascript'>
  var googletag = googletag || {};
  googletag.cmd = googletag.cmd || [];
  (function() {
    var gads = document.createElement('script');
    gads.async = true;
    gads.type = 'text/javascript';
    var useSSL = 'https:' == document.location.protocol;
    gads.src = (useSSL ? 'https:' : 'http:') +
      '//www.googletagservices.com/tag/js/gpt.js';
    var node = document.getElementsByTagName('script')[0];
    node.parentNode.insertBefore(gads, node);
  })();
</script>

<script type='text/javascript'>
  googletag.cmd.push(function() {
    googletag.defineSlot('/23455367/free_moodle_teacher_site', [728, 90], 'div-gpt-ad-1432631430132-0').addService(googletag.pubads());
    googletag.pubads().enableSingleRequest();
    googletag.enableServices();
  });
</script>";
    }
    return $s;
}

/**
 * Partners ads for teachers and admins, adsense for students.
 */
function theme_moodlecloud_get_ad($context) {
    $s = null;

    if (theme_moodlecloud_is_teacher($context)) {
        $s = "<div id='moodlecloud_ad' style='width:728px;margin-left:auto;margin-right:auto;display:block !important;'>
<!-- /23455367/free_moodle_teacher_site -->
<div id='div-gpt-ad-1432631430132-0' style='height:90px; width:728px;'>
<script type='text/javascript'>
googletag.cmd.push(function() { googletag.display('div-gpt-ad-1432631430132-0'); });
</script>
</div>
</div>";

    } else {
        //width:728px;height:90px;background-color:green;
        $s = '<div id="moodlecloud_ad" style="margin-left:auto;margin-right:auto;display:block !important;">
         <script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
<!-- Moodle Free Student Footer Block -->
<ins class="adsbygoogle"
     style="display:inline-block;width:728px;height:90px"
     data-ad-client="ca-pub-3092401428789996"
     data-ad-slot="1236105465"></ins>
<script>
(adsbygoogle = window.adsbygoogle || []).push({});
</script>
         </div>';
    }

    return $s;
}


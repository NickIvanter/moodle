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
 *  Cloudfront URL signing filter
 *
 *  This filter will replace defined cloudfront URLs with signed
 *  URLs as described at http://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/private-content-signed-urls.html
 *
 * @package    filter
 * @subpackage cloudfront_signurl
 * @copyright  2014 Owen Barritt, Wine & Spirit Education Trust
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/filter/cloudfront_signurl/defaults.php');
require_once($CFG->dirroot.'/filter/cloudfront_signurl/lib.php');

class filter_cloudfront_signurl extends moodle_text_filter {

	private $id = 0;

	/**
     * Implement the filtering.
     *
     * @param $text some HTML content.
     * @param array $options options passed to the filters
     * @return the HTML content after the filtering has been applied.
     */
    public function filter($text, array $options = array()) {
        if (!is_string($text) or empty($text)) {
            // Non string data can not be filtered anyway.
            return $text;
        }
        
        if (stripos($text, '[cloudfront') === false) {
            // Performance shortcut - all regexes below contain http/https protocol,
            // if not present nothing can match.
            return $text;
        }
        
        $urls = preg_split("~\s+~", $disturls);
        $regexurls = array();

        // Strip protocol and trailing / from disturl if present
        foreach ($urls as $disturl) {
            $disturl = preg_replace('~^https?://|/$~','',$disturl);
            if($disturl !== ''){
                $regexurls[] = $disturl;
            }
        }
        $urlregex = implode("|",$regexurls);

		$newtext = preg_replace_callback($re = '~\[cloudfront.*\]~is', array($this, 'callback'), $text);
        
        if (empty($newtext) or $newtext === $text) {
            // Error or not filtered.
            return $text;
        }

        return $this->embedPlayer() . $newtext;
    }

	private function embedPlayer() {
		$scriptDir = $CFG->dirroot.'/filter/cloudfront_signurl/scripts';

		return "<script type='text/javascript' src='{$scriptDir}/jwplayer/jwplayer.js'></script>
<script>jwplayer.key='MskDf3mwEySn8344579IXFmJZOU97Sntf6bMIw==';</script>";
	}

	private static function parseParam($text, $tag, $paramName)
	{
		global $filter_cloudfront_signurl_defaults;
		if ( preg_match("/$tag=([^]\s]+)/i", $text, $matches) ) {
			return $matches[1];
		} else {
			$conf = get_config(
				'filter_cloudfront_signurl',
				$paramName,
				$filter_cloudfront_signurl_defaults[$paramName]
			);
			if ( $conf ) {
				return $conf;
			} else {
				return $filter_cloudfront_signurl_defaults[$paramName];
			}
		}
	}

    private function callback(array $matches) {
		$scriptDir = $CFG->dirroot.'/filter/cloudfront_signurl/scripts';
		$text =  $matches[0];
		$this->id++;

		// Parse parameters
		if ( preg_match('/url=([^]\s]+)/i', $text, $url) ) {
			$url = $url[1];
		} else {
			return $text; // No url given
		}

		$width = self::parseParam($text, 'width', 'videowidth');
		$height = self::parseParam($text, 'height', 'videoheight');
		$autostart = self::parseParam($text, 'autostart', 'videoautostart');
		$thumb = self::parseParam($text, 'thumb', 'videothumb');
		if ( $autostart == 1 ) {
			$onReady = 'this.play();';
		} elseif ( $thumb == 1 ) {
			$onReady = 'this.play(); this.pause();';
		} else {
			$onReady = '';
		}


		if ( preg_match('~.*(rtmp://.*/cfx/st/(_definst_/|mp4:|flv:|mp3:|webm:)?)(\S*)~is', $url, $url_parts) ) {
			if (count($url_parts) < 3)
				return $text;

			$signUrl = filter_cloudfront_signurl_urlsigner::get_canned_policy_stream_name_rtmp($url_parts[1], $url_parts[3]);
		} else {
			$signUrl = filter_cloudfront_signurl_urlsigner::get_canned_policy_stream_name($url);
		}


        return "<div id=\"cloudfront-video-{$this->id}\"></div>
<script type=\"text/javascript\" id=\"cloudfront-video-setup-{$this->id}\">
jwplayer(\"cloudfront-video-{$this->id}\").setup({
flashplayer: \"{$scriptDir}/jwplayer/jwplayer.flash.swf\",
file: \"{$signUrl}\",
width: \"{$width}\",
height: \"{$height}\",
primary: \"flash\",
events: {
onReady: function () { {$onReady} }
},
rtmp: { subscribe: true }
});
//Y.one(\"#cloudfront-video-setup-{$this->id}\").remove();
</script>";
	}
}
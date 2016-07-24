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
	private $scriptDir;
	private $imageDir;

	public function __construct()
	{
		global $CFG;
		$this->scriptDir = $CFG->wwwroot.'/filter/cloudfront_signurl/scripts';
		$this->imageDir = '';
	}

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
        
        if (stripos($text, '[cloudfront') === false && stripos($text, '[s3mediastream]') === false) {
            // Performance shortcut - all regexes below contain http/https protocol,
            // if not present nothing can match.
            return $text;
        }

		// Native tag video
		$newtext1 = preg_replace_callback('~\[cloudfront video[^]]*\]~i', array($this, 'native_video_callback'), $text);

		// Native tag link
		$newtext2 = preg_replace_callback('~\[cloudfront link[^]]*\]~i', array($this, 'native_link_callback'), $newtext1);

		// s3mediastream compat tag
		$newtext3 = preg_replace_callback('~\[s3mediastream\]([^[]*)\[/s3\]~i', array($this, 's3ms_compat_callback'), $newtext2);

        if (empty($newtext3) or $newtext3 === $text) {
            // Error or not filtered.
            return $text;
        }
        return $this->embed_player() . $newtext3;
    }

	private function is_param_set($key, $params)
	{
		return array_key_exists($key, $params) && $params[$key];
	}

	private function embed_player() {
		$playerkey = self::default_param('playerkey');

		return "<script type='text/javascript' src='{$this->scriptDir}/jwplayer/jwplayer.js'></script>
<script>jwplayer.key='$playerkey';</script>";
	}

	private static function is_rtmp_distribution($dist)
	{
		return $dist[0] == 's';
	}

	private static function compose_distribution_url($dist, $file)
	{
		// Rip off protocol and domain if any
		$dist = trim($dist);
		$file = trim($file);
		$dist = str_ireplace(['rtmp:', 'https:', '/', 'cloudfront.net', '.'], '', $dist);

		if ( self::is_rtmp_distribution($dist) ) {
			$protocol = 'rtmp://';
			$suffix = 'cfx/st/';
			if ( preg_match('/\.(.+)$/', $file, $parts) ) {
				$ext = "{$parts[1]}:";
			} else {
				$ext = '';
			}
		} else {
			$protocol = 'https://';
			$suffix = '';
			$ext = '';
		}

		return $protocol . $dist . '.cloudfront.net/' . $suffix . $ext . self::encode_url($file);
	}


	private static function default_param($paramName)
	{
		global $filter_cloudfront_signurl_defaults;
		if ($paramName) {
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
		} else {
			return 0;
		}
	}

	private static function parse_native_param($text, $tag, $paramName)
	{
		if ( preg_match("/$tag='([^']+)'/i", $text, $matches) ) {
			return $matches[1];
		} elseif ( preg_match("/$tag=([^]\s]+)/i", $text, $matches) ) {
			return $matches[1];
		} else {
			return self::default_param($paramName);
		}
	}

    private function native_video_callback($matches) {
		$text =  $matches[0];

		// Parse parameters
		$params = [
			'width' => self::parse_native_param($text, 'width', 'videowidth'),
			'height' => self::parse_native_param($text, 'height', 'videoheight'),
			'autostart' => self::parse_native_param($text, 'autostart', 'videoautostart'),
			'thumb' => self::parse_native_param($text, 'thumb', 'videothumb'),
			'image' => self::parse_native_param($text, 'image', ''),
		];

		// Dist and file
		$dist = null;
		if ( preg_match('/dist=([^]\s]+)/i', $text, $dist_part) ) {
			$dist = $dist_part[1];
		} elseif ( self::default_param('maindistr') ) {
			$dist = self::default_param('maindistr');
		}
		$file = null;
		if ( preg_match('/file=([^]\s]+)/i', $text, $file_part) ) {
			$file = $file_part[1];
		}
		if ( $dist && $file ) {
			$params['url'] = self::compose_distribution_url($dist, $file);
		}

		// Direct full url
		if ( preg_match('/url=([^]\s]+)/i', $text, $url) ) {
			$params['url'] = $url[1];
			$params['url'] = self::encode_url($url[1]);
	}

		// Fallback Dist and file
		$fallback_dist = null;
		if ( preg_match('/fallback=([^]\s]+)/i', $text, $fallback_dist_part) ) {
			$fallback_dist = $fallback_dist_part[1];
		} elseif ( self::default_param('fallbackdistr') ) {
			$fallback_dist = self::default_param('fallbackdistr');
		}

		if ( $fallback_dist && $file ) {
			$params['fallbackUrl'] = self::compose_distribution_url($fallback_dist, $file);
		}
		// Direct full url
		if ( preg_match('/fallback_url=([^]\s]+)/i', $text, $fallback) ) {
			$params['fallbackUrl'] = $fallback[1];
			$params['fallbackUrl'] = self::encode_url($fallback[1]);
		}

		return $this->embed_video($params, $text);

	}

    private function native_link_callback($matches) {
		$text =  $matches[0];

		// Parse parameters
		$params = [
			'name' => self::parse_native_param($text, 'name', 'linkname'),
		];

		// Dist and file
		$dist = null;
		if ( preg_match('/dist=([^]\s]+)/i', $text, $dist_part) ) {
			$dist = $dist_part[1];
		} elseif ( self::default_param('fallbackdistr') ) {
			$dist = self::default_param('fallbackdistr');
		}
		$file = null;
		if ( preg_match('/file=([^]\s]+)/i', $text, $file_part) ) {
			$file = $file_part[1];
		}

		if ( $dist && $file ) {
			$params['url'] = self::compose_distribution_url($dist, $file);
		}

		// Direct full url
		if ( preg_match('/url=([^]\s]+)/i', $text, $url) ) {
			$params['url'] = self::encode_url($url[1]);
		}

		return $this->embed_link($params, $text);
	}

	private static function parse_s3ms_param($val, $paramName)
	{
		global $filter_cloudfront_signurl_defaults;
		if ( $val ) {
			if ( $val === 'no') $val = 0;
			if ( $val === 'yes') $val = 1;
			return $val;
		} else {
			return self::default_param($paramName);
		}
	}

	private function s3ms_compat_callback($matches)
	{
		$text = $matches[0];
		$s3text = trim(str_ireplace( ['&nbsp;'], ' ', $matches[1]) );

		if ( !$s3text ) return ''; // Wipe out empty tag
		$s3values = preg_split('/\s*,\s*/', $s3text);

		switch ($s3values[0]) {
		case 's3streamingvideo':
			return self::s3ms_compat_videostream($s3values, $text);
			break;
		case 's3link':
		case 's3link_s':
			return self::s3ms_compat_link($s3values, $text);
			break;
		default:
			return "<b>*** S3MEDIASTREAM TYPE {$s3values[0]} DOES NOT SUPPORTED ***</b>";
		}
	}

	private function s3ms_compat_replace_plus($val)
	{
		return str_replace('+', '/', $val);
	}

	private function s3ms_compat_videostream($values, $text)
	{
		// var_dump($values);
		list(
			$mediaType,
			$linkTitle,
			$mediaID,
			$startTimeline,
			$duration,
			$mediaFile,
			$bucket,
			$expireSeconds,
			$encodeUrl,
			$posterImage,
			$logo,
			$logoPosition,
			$logoLink,
			$autoStart,
			$playerWidth,
			$playerHeight,
			$fullScreen,
			$controlBar,
			$selectSkin,
			$drelatedLink,
			$borderColor,
			$captionsLink,
			$captionsBack,
			$captionsState,
			$dockButtons,
			$html5Fallback,
			$repeat
		) = $values;

		$params = [
			'width' => self::parse_s3ms_param($playerWidth, 'videowidth'),
			'height' => self::parse_s3ms_param($playerHeight, 'videoheight'),
			'autostart' => self::parse_s3ms_param($autoStart, 'videoautostart'),
			'thumb' => 0,
			'image' => self::parse_s3ms_param(self::s3ms_compat_replace_plus($posterImage), ''),
			'url' => self::compose_distribution_url(
				self::s3ms_compat_replace_plus($bucket),
				self::s3ms_compat_replace_plus($mediaFile)
			),
		];
		// if ( $html5Fallback ) {
		// 	$params['fallbackUrl'] = self::compose_distribution_url(
		// 		$html5Fallback,
		// 		self::s3ms_compat_replace_plus($mediaFile)
		// 	);
		// } else
		if ( self::default_param('fallbackdistr') ) {
			$params['fallbackUrl'] = self::compose_distribution_url(
				self::default_param('fallbackdistr'),
				self::s3ms_compat_replace_plus($mediaFile)
			);
		}

		return $this->embed_video( $params, $text );
	}

	private function s3ms_compat_link($values, $text)
	{
		global $USER, $filter_cloudfront_signurl_defaults;
		list(
			$mediaType,
			$linkTitle,
			$mediaFile,
			$bucket,
			$expireSeconds
		) = $values;

		$mediaFile = self::s3ms_compat_replace_plus($mediaFile);

		if ( $mediaType == 's3link_s' ) {
			if ( $USER && $USER->idnumber ) {
				$mediaFile = 'user/' . $USER->idnumber . '/' . $mediaFile;
			} else {
				return $filter_cloudfront_signurl_defaults['nouserstub'];
			}
		}

		$params = [
			'name' => $linkTitle,
			'url' => self::compose_distribution_url(self::default_param('fallbackdistr'), $mediaFile),
		];

		return self::embed_link($params, $text);
	}

	private static function encode_url($url)
	{
		return str_replace('%2F', '/', rawurlencode($url));
	}

	private function embed_link($params, $text)
	{
		$params['signUrl'] = filter_cloudfront_signurl_urlsigner::get_canned_policy_stream_name($params['url']);
		return "<a href='{$params['signUrl']}'>{$params['name']}</a>";
	}

	private function embed_video($params, $text) {
		// var_dump($params);
		// No url given or composed
		if ( !array_key_exists( 'url', $params ) ) return $text;

		if ( preg_match('~.*(rtmp://.*/cfx/st/(_definst_/|mp4:|flv:|mp3:|webm:)?)(\S*)~is', $params['url'], $url_parts) ) {
			if (count($url_parts) < 3)
				return $text;

			$params['signUrl'] = filter_cloudfront_signurl_urlsigner::get_canned_policy_stream_name_rtmp($url_parts[1], $url_parts[3]);
			$params['player'] = 'flash';
		} else {
			$params['signUrl'] = filter_cloudfront_signurl_urlsigner::get_canned_policy_stream_name($params['url']);
			$params['player'] = 'flash';
		}

		// don't check for rtmp cause fallback must be web distribution
		if ( self::is_param_set( 'fallbackUrl', $params ) ) {
			$params['signFallbackUrl'] = filter_cloudfront_signurl_urlsigner::get_canned_policy_stream_name($params['fallbackUrl']);
			$fallbackUrl = ",{file: '{$params['signFallbackUrl']}'}";
		} else {
			$fallbackUrl = '';
		}

		if ( self::is_param_set( 'autostart', $params ) ) {
			$onReady = 'this.play();';
		} elseif ( self::is_param_set( 'thumb', $params ) ) {
			$onReady = 'this.play(); this.pause();';
		} else {
			$onReady = '';
		}

		$custom = '';
		if ( self::is_param_set( 'image', $params ) ) {
			$custom .= "image: '{$this->imageDir}/{$params['image']}',";
		}
		if ( array_key_exists('title', $params) && $params['title']  ) {
			$custom .= "title: '{$params['image']}',";
		}

		$this->id++;
		$embed = "<div id='cloudfront-video-{$this->id}'></div>
<script type='text/javascript' id='cloudfront-video-setup-{$this->id}'>
jwplayer('cloudfront-video-{$this->id}').setup({
flashplayer: '{$this->scriptDir}/jwplayer/jwplayer.flash.swf',
sources: [{file: '{$params['signUrl']}'}{$fallbackUrl}],
width: '{$params['width']}',
height: '{$params['height']}',
primary: '{$params['player']}',
{$custom}
events: { onReady: function () { {$onReady} } } });
</script>";
		return $embed;
	}

}

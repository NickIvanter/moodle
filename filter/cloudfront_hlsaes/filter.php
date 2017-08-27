<?php
/**
 *  Cloudfront URL signing filter
 *
 *  This filter will replace defined cloudfront HLS AES streams
 *
 * @package    filter
 * @subpackage cloudfront_hlsaes
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/filter/cloudfront_hlsaes/defaults.php');

class filter_cloudfront_hlsaes extends moodle_text_filter {

	private $id = 0;
	private $scriptDir;
	private $imageDir;

	public function __construct()
	{
		global $CFG;
		$this->scriptDir = $CFG->wwwroot.'/filter/cloudfront_hlsaes/scripts';
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

        if (stripos($text, '[cloudfront_hlsaes') === false && stripos($text, '[coundfront_hlsaes]') === false) {
            // Performance shortcut - all regexes below contain http/https protocol,
            // if not present nothing can match.
            return $text;
        }

		// Native tag video
		$newtext1 = preg_replace_callback('~\[cloudfront_hlsaes video[^]]*\]~i', array($this, 'native_video_callback'), $text);

		// s3mediastream compat tag
		$newtext2 = preg_replace_callback('~\[cloudfront_hlsaes\]([^[]*)\[/cloudfront_hlsaes\]~i', array($this, 's3ms_compat_callback'), $newtext1);

        if (empty($newtext2) or $newtext2 === $text) {
            // Error or not filtered.
            return $text;
        }
        return $this->embed_player() . $newtext2;
    }

	private function is_param_set($key, $params)
	{
		return array_key_exists($key, $params) && $params[$key];
	}

	private function embed_player() {
		$playerkey = self::default_param('playerkey');

		return "<link rel='stylesheet' href='{$this->scriptDir}/flowplayer/skin/skin.css'>\n
<script type='text/javascript' src='{$this->scriptDir}/flowplayer/flowplayer.min.js'></script>
<script type='text/javascript' src='{$this->scriptDir}/flowplayer/flowplayer.hlsjs.light.min.js'></script>
<style>.fluid-width-video-wrapper { position: initial; }</style>";
	}

	private static function compose_distribution_url($dist, $file)
	{
		$file = trim($file);
		return 'https://' . $dist . '.cloudfront.net/' . self::encode_url($file);
	}


	private static function default_param($paramName)
	{
		global $filter_cloudfront_hlsaes_defaults;
		if ($paramName) {
			$conf = get_config(
				'filter_cloudfront_hlsaes',
				$paramName,
				$filter_cloudfront_hlsaes_defaults[$paramName]
			);
			if ( $conf ) {
				return $conf;
			} else {
				return $filter_cloudfront_hlsaes_defaults[$paramName];
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

		return $this->embed_video($params, $text);
	}

	private static function parse_s3ms_param($val, $paramName)
	{
		global $filter_cloudfront_hlsaes_defaults;
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

		if ( !$bucket ) {
			$bucket = self::default_param('maindistr');
		}

		$params['url'] = self::compose_distribution_url(
			self::s3ms_compat_replace_plus($bucket),
			self::s3ms_compat_replace_plus($mediaFile)
		);

		return $this->embed_video( $params, $text );
	}

	private static function encode_url($url)
	{
		return str_replace('%2F', '/', rawurlencode($url));
	}

	private function embed_video($params, $text) {
		// var_dump($params);
		// No url given or composed
		if ( !array_key_exists( 'url', $params ) ) return $text;

		$custom = '';
		if ( self::is_param_set( 'image', $params ) ) {
			$custom .= ", poster: '{$this->imageDir}/{$params['image']}'";
		}
		if ( array_key_exists('title', $params) && $params['title']  ) {
			$custom .= ", title: '{$params['image']}'";
		}

		if ( self::is_param_set( 'autostart', $params ) ) {
			$custom .= ', autoplay: true';
		}

		$style = '';
		if ( self::is_param_set( 'width', $params ) ) {
			$style .= "width: {$params['width']}px;";
		}
		if ( self::is_param_set( 'height', $params ) ) {
			$style .= "height: {$params['height']}px;";
		}

		$this->id = self::getRandomID();
		$embed = "<style>#cloudfront-video-{$this->id} { $style }</style><div id='cloudfront-video-{$this->id}' style='{$style}'></div>
<script type='text/javascript' id='cloudfront-video-setup-{$this->id}'>
(function() {
var player_element = document.getElementById('cloudfront-video-{$this->id}');
flowplayer( player_element, {
share: false,
logo: '',
width: '{$params['width']}',
height: '{$params['height']}',
clip: { sources: [{ type: \"application/x-mpegurl\", src: \"{$params['url']}\" }] }{$custom}
});
document.querySelector('#cloudfront-video-{$this->id} > a').remove();
})()
</script>";
		return $embed;
	}

	private static function getRandomID($length = 10)
	{
		$characters = 'abcdefghijklmnopqrstuvwxyz';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}
}

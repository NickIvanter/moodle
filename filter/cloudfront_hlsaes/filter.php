<?php
/**
 *  Cloudfront HLSAES filter
 *
 * @package    filter
 * @subpackage cloudfront_hlsaes
 */

defined('MOODLE_INTERNAL') || die();

class filter_cloudfront_hlsaes extends moodle_text_filter {

	protected $scriptDir;
	protected $imageUrl = '';

	// Random Id charset
	protected $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	protected $charsLen;
	protected $length;

	// Auth headers
	protected $secretHeader = 'X-HLSAES-Secret';
	protected $tokenHeader = 'X-HLSAES-Token';
	protected $tokenIdHeader = 'X-HLSAES-Token-id';

	public function __construct()
	{
		global $CFG;
		$this->scriptDir = $CFG->wwwroot.'/filter/cloudfront_hlsaes/scripts';

		$this->charsLen = strlen( $this->chars );
	}

	/**
     * Implement the filtering.
     *
     * @param $text some HTML content.
     * @param array $options options passed to the filters
     * @return the HTML content after the filtering has been applied.
     */
    public function filter($text, array $options = array())
	{
        if (!is_string($text) or empty($text)) {
            // Non string data can not be filtered anyway.
            return $text;
        }

        if (stripos($text, '[cloudfront_hlsaes') === false) {
            // Performance shortcut - all regexes below contain http/https protocol,
            // if not present nothing can match.
            return $text;
        }

		if ( !get_config( 'filter_cloudfront_hlsaes', 'tokenurl', false ) ) {
			return $text;
		}

		// Native tag video
		$newtext = preg_replace_callback('~\[cloudfront_hlsaes[^]]*\]~i', array($this, 'native_video_callback'), $text);

        if (empty($newtext) or $newtext === $text) {
            // Error or not filtered.
            return $text;
        }
        return $this->embed_player() . $newtext;
    }

	protected static function is_param_set($key, $params)
	{
		return array_key_exists($key, $params) && $params[$key];
	}

	protected function embed_player() {
		return "<link rel='stylesheet' href='{$this->scriptDir}/flowplayer/skin/skin.css'>\n
<script type='text/javascript' src='{$this->scriptDir}/flowplayer/flowplayer.min.js'></script>
<script type='text/javascript' src='{$this->scriptDir}/flowplayer/flowplayer.hlsjs.light.min.js'></script>
<style>.fluid-width-video-wrapper { position: initial; }</style>";
	}

	protected static function parse_native_param($text, $tag, $paramName = '')
	{
		if ( preg_match("/$tag='([^']+)'/i", $text, $matches) ) {
			return $matches[1];
		} elseif ( preg_match("/$tag=([^]\s]+)/i", $text, $matches) ) {
			return $matches[1];
		} else {
			return '';
		}
	}

    protected function native_video_callback($matches) {
		$text =  $matches[0];

		// Parse parameters
		$params = [
			'url' => self::parse_native_param($text, 'url'),
			'poster' => self::parse_native_param($text, 'poster'),
			'autostart' => self::parse_native_param($text, 'autoplay'),
			'width' => self::parse_native_param($text, 'width'),
			'height' => self::parse_native_param($text, 'height'),
		];


		// Setup video access token

		$token_id = $this->random_id();
		$token = $this->setup_token( $token_id );
		$params['token_id'] = $token_id;
		$params['token'] = $token;
		$params['key_url'] = get_config( 'filter_cloudfront_hlsaes', 'keyurl' );;

		return $this->embed_video($params, $text);
	}

	protected function setup_token( $token_id )
	{
		$secret = get_config( 'filter_cloudfront_hlsaes', 'tokensecret', false );
		$options = [
			'http'		  => [
				'method'  => 'POST',
				'header'  => "Content-type: application/x-www-form-urlencoded\r\n{$this->secretHeader}: {$secret}\r\n",
				'content' => http_build_query(['token_id' => $token_id]),
				'timeout' => 10,
			],
		];

		$context = stream_context_create( $options );
		$url = get_config( 'filter_cloudfront_hlsaes', 'tokenurl' );
		$token = file_get_contents($url, false, $context);
		return $token;
	}

	protected function embed_video($params, $text) {
		// var_dump($params);
		// No url given or composed
		if ( !array_key_exists( 'url', $params ) ) return $text;

		$custom = '';
		if ( self::is_param_set( 'poster', $params ) ) {
			$custom .= ", poster: '{$this->imageUrl}/{$params['poster']}'";
		}

		if ( self::is_param_set( 'autoplay', $params ) ) {
			$custom .= ', autoplay: true';
		}

		$style = '';
		if ( self::is_param_set( 'width', $params ) ) {
			$style .= "width: {$params['width']}px;";
		}
		if ( self::is_param_set( 'height', $params ) ) {
			$style .= "height: {$params['height']}px;";
		}

		$this->id = $this->random_id();
		$embed = "<style>#cloudfront-video-{$this->id} { $style }</style><div id='cloudfront-video-{$this->id}' style='{$style}'></div>
<script type='text/javascript' id='cloudfront-video-setup-{$this->id}'>
(function() {
var player_element = document.getElementById('cloudfront-video-{$this->id}');
flowplayer( player_element, {
share: false,
logo: '',
width: '{$params['width']}',
height: '{$params['height']}',
hlsjs: {
xhrSetup: function( xhr, url ) {
  if ( url.indexOf('{$params['key_url']}') > -1 ) {
    xhr.setRequestHeader( '{$this->tokenIdHeader}', '{$params['token_id']}' );
    xhr.setRequestHeader( '{$this->tokenHeader}', '{$params['token']}' );
  }
}
},
clip: { sources: [{ type: \"application/x-mpegurl\", src: \"{$params['url']}\" }] }{$custom}
});
document.querySelector('#cloudfront-video-{$this->id} > a').remove();
})()
</script>";
		return $embed;
	}

	protected function random_id( $len = 16 )
	{
		$randString = '';
		for ($i = 0; $i < $len; $i++) {
			$randString .= $this->chars[mt_rand(0, $this->charsLen)];
		}
		return $randString;
	}

}

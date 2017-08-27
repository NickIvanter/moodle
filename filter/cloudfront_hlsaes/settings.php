<?php
/**
 *  Cloudfront HLSAES settings
 *
 * @package    filter
 * @subpackage cloudfront_hlsaes
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
	require_once(__DIR__ . '/defaults.php');
	global $filter_cloudfront_hlsaes_defaults;

    $settings->add(new admin_setting_configtext('filter_cloudfront_hlsaes/aeskeys',
        get_string('aeskeys', 'filter_cloudfront_hlsaes'),
        get_string('aeskeysdesc', 'filter_cloudfront_hlsaes'),
        ''));

    $settings->add(new admin_setting_configtext('filter_cloudfront_hlsaes/maindistr',
        get_string('maindistr', 'filter_cloudfront_hlsaes'),
        get_string('maindistrdesc', 'filter_cloudfront_hlsaes'),
        ''));

	$settings->add(new admin_setting_configtext(
		'filter_cloudfront_hlsaes/videowidth',
        get_string('videowidth', 'filter_cloudfront_hlsaes'),
        get_string('videowidthdesc', 'filter_cloudfront_hlsaes'),
		$filter_cloudfront_hlsaes_defaults['videowidth'],
		PARAM_INT
	));

	$settings->add(new admin_setting_configtext(
		'filter_cloudfront_hlsaes/videoheight',
        get_string('videoheight', 'filter_cloudfront_hlsaes'),
        get_string('videoheightdesc', 'filter_cloudfront_hlsaes'),
		$filter_cloudfront_hlsaes_defaults['videoheight'],
		PARAM_INT
	));

	$settings->add(new admin_setting_configcheckbox(
		'filter_cloudfront_hlsaes/videoautostart',
        get_string('videoautostart', 'filter_cloudfront_hlsaes'),
        get_string('videoautostartdesc', 'filter_cloudfront_hlsaes'),
		$filter_cloudfront_hlsaes_defaults['videoautostart']
	));

	$settings->add(new admin_setting_configcheckbox(
		'filter_cloudfront_hlsaes/videothumb',
        get_string('videothumb', 'filter_cloudfront_hlsaes'),
        get_string('videothumbdesc', 'filter_cloudfront_hlsaes'),
		$filter_cloudfront_hlsaes_defaults['videothumb']
	));

	$settings->add(new admin_setting_configtext('filter_cloudfront_hlsaes/playerkey',
        get_string('playerkey', 'filter_cloudfront_hlsaes'),
        get_string('playerkeydesc', 'filter_cloudfront_hlsaes'),
        ''));


}

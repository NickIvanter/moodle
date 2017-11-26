<?php
/**
 *  Cloudfront HLSAES settings
 *
 * @package    filter
 * @subpackage cloudfront_hlsaes
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
		'filter_cloudfront_hlsaes/tokenurl',
		get_string('tokenurl', 'filter_cloudfront_hlsaes'),
		get_string('tokenurldesc', 'filter_cloudfront_hlsaes'),
		''));
    $settings->add(new admin_setting_configtext(
		'filter_cloudfront_hlsaes/tokensecret',
		get_string('tokensecret', 'filter_cloudfront_hlsaes'),
		get_string('tokensecretdesc', 'filter_cloudfront_hlsaes'),
		''));
    $settings->add(new admin_setting_configtext(
		'filter_cloudfront_hlsaes/keyurl',
		get_string('keyurl', 'filter_cloudfront_hlsaes'),
		get_string('keyurldesc', 'filter_cloudfront_hlsaes'),
		''));

}

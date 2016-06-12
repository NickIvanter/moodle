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
 *  Cloudfront URL signing settings.
 *
 * @package    filter
 * @subpackage cloudfront_signurl
 * @copyright  2014 Owen Barritt, Wine & Spirit Education Trust
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    require_once(__DIR__ . '/lib.php');
    require_once(__DIR__ . '/adminlib.php');
	require_once(__DIR__ . '/defaults.php');
	global $filter_cloudfront_signurl_defaults;

    $settings->add(new filter_cloudfront_signurl_keyid('filter_cloudfront_signurl/keypairid',
        get_string('keyid', 'filter_cloudfront_signurl'),
        get_string('keyiddesc', 'filter_cloudfront_signurl'),
        ''));
        
    $settings->add(new filter_cloudfront_signurl_privatekey('filter_cloudfront_signurl/privatekey',
        get_string('privatekey', 'filter_cloudfront_signurl'),
        get_string('privatekeydesc', 'filter_cloudfront_signurl'),
        'privatekey',
        0,
        array(
            'accepted_types' => array('.pem')
        )));
            
    $settings->add(new admin_setting_configtext('filter_cloudfront_signurl/maindistr',
        get_string('maindistr', 'filter_cloudfront_signurl'),
        get_string('maindistrdesc', 'filter_cloudfront_signurl'),
        ''));

    $settings->add(new admin_setting_configtext('filter_cloudfront_signurl/fallbackdistr',
        get_string('fallbackdistr', 'filter_cloudfront_signurl'),
        get_string('fallbackdistrdesc', 'filter_cloudfront_signurl'),
        ''));

    $settings->add(new admin_setting_configduration('filter_cloudfront_signurl/validduration',
        get_string('validduration', 'filter_cloudfront_signurl'),
        get_string('validdurationdesc', 'filter_cloudfront_signurl'),
        86400,
        1));

	$settings->add(new admin_setting_configtext(
		'filter_cloudfront_signurl/videowidth',
        get_string('videowidth', 'filter_cloudfront_signurl'),
        get_string('videowidthdesc', 'filter_cloudfront_signurl'),
		$filter_cloudfront_signurl_defaults['videowidth'],
		PARAM_INT
	));

	$settings->add(new admin_setting_configtext(
		'filter_cloudfront_signurl/videoheight',
        get_string('videoheight', 'filter_cloudfront_signurl'),
        get_string('videoheightdesc', 'filter_cloudfront_signurl'),
		$filter_cloudfront_signurl_defaults['videoheight'],
		PARAM_INT
	));

	$settings->add(new admin_setting_configcheckbox(
		'filter_cloudfront_signurl/videoautostart',
        get_string('videoautostart', 'filter_cloudfront_signurl'),
        get_string('videoautostartdesc', 'filter_cloudfront_signurl'),
		$filter_cloudfront_signurl_defaults['videoautostart']
	));

	$settings->add(new admin_setting_configcheckbox(
		'filter_cloudfront_signurl/videothumb',
        get_string('videothumb', 'filter_cloudfront_signurl'),
        get_string('videothumbdesc', 'filter_cloudfront_signurl'),
		$filter_cloudfront_signurl_defaults['videothumb']
	));

	$settings->add(new admin_setting_configtext('filter_cloudfront_signurl/playerkey',
        get_string('playerkey', 'filter_cloudfront_signurl'),
        get_string('playerkeydesc', 'filter_cloudfront_signurl'),
        ''));


}
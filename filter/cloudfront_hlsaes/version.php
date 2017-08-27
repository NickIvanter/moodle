<?php
/**
 *  Cloudfront HLS AES filter
 *
 *  This filter will replace defined cloudfront URLs with signed
 *  URLs as described at http://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/private-content-signed-urls.html
 *
 * @package    filter
 * @subpackage cloudfront_hlsaes
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2017082700;        // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2014051200;        // Requires Moodle 2.7
$plugin->component = 'filter_cloudfront_hlsaes'; // Full name of the plugin (used for diagnostics).
$plugin->maturity = MATURITY_STABLE;
$plugin->release   = '0.1 for Moodle 2.7+';

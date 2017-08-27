<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/vendor/autoload.php';

if ( !isloggedin() ) {
    http_response_code( 400 ); die;
}

$awsApiConfig = [
	'region'  => 'eu-west-1',
	'version' => 'latest',
	// 'debug'	  => true,
];


$conf = get_config( 'filter_cloudfront_hlsaes', 'aeskeys', '' );

$keys = json_decode( $conf, true );

if ( isset( $_REQUEST['id'] ) && isset( $keys[$_REQUEST['id']] ) ) {

	$keymap = $keys[$_REQUEST['id']];

	$sdk = new Aws\Sdk( $awsApiConfig );
	$ETclient = $sdk->createElasticTranscoder();

    $job = $ETclient->readJob( [
		'Id' => $keymap['jobId'],
	]);

	$jobKey = $job['Job']['Playlists'][0]['HlsContentProtection']['Key'];

	$KMSclient = $sdk->createKms();

	$decryptionKey = $KMSclient->decrypt([
		'CiphertextBlob' => base64_decode( $jobKey ),
		'EncryptionContext' => [
			"service" => "elastictranscoder.amazonaws.com",
		],
		// 'GrantTokens' => array('string', ... ),
	]);

    header('Content-Type: binary/octet-stream');
    header('Cache-Control: no-store');
    echo $decryptionKey['Plaintext'];

} else {
    http_response_code( 400 ); die;
}

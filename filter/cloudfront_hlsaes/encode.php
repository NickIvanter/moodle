<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/vendor/autoload.php';

if ( !is_siteadmin() ) {
    http_response_code( 404 ); die;
}

global $CFG;

try {

if ( isset( $_REQUEST['input'] ) && isset( $_REQUEST['output'] ) && isset( $_REQUEST['id'] ) ) {

	$conf = json_decode( get_config( 'filter_cloudfront_hlsaes', 'aeskeys', '' ), true );

	$awsApiConfig = [
		'region'  => 'eu-west-1',
		'version' => 'latest',
		// 'debug'	  => true,
	];

	echo '<h1>Create ElasticTranscoder Job</h1>';

	$outputName = str_replace( '.', '_', basename( $_REQUEST['input'] ) ); // @todo More intelligent?

	// @todo Get from AWS KMS by ARN/generateNew
    $aesKey = '';
    $aesIV = '';
    $aesMD5 = '';

    $keyUrl = "{$CFG->wwwroot}/filter/cloudfront_hlsaes/key.php?id={$_REQUEST[ 'id']}"; // @todo Config or autodetect?

	$hlsPresets = [ '1351620000001-200010','1351620000001-200020', '1351620000001-200030', '1351620000001-200040' ]; // @todo Config?

	$outputList = [];
    foreach( $hlsPresets as $preset ) {
		$outputDescription = aws_outputConfig( $preset, $outputName );
		$outputList[ $outputDescription['Key'] ] = $outputDescription;
	}

	$pipelineId = '1503226191334-fl93fp'; // @todo Config

	$jobDescription = [
        // PipelineId is required
        'PipelineId' => $pipelineId,
        // Input is required
        'Input' => [
            'Key' => $_REQUEST[ 'input' ],
            'FrameRate' => 'auto',
            'Resolution' => 'auto',
            'AspectRatio' => 'auto',
            'Interlaced' => 'auto',
            'Container' => 'auto',
        ],
        'Outputs' => array_values( $outputList ),
        'OutputKeyPrefix' => $_REQUEST[ 'output' ],
        'Playlists' => [
            [
                'Name' => $outputName,
                'Format' => 'HLSv3', // @todo Config? may be HLSv4?
                'OutputKeys' => array_keys( $outputList ),
                'HlsContentProtection' => [
                    'Method' => 'aes-128',
                    'Key' => $aesKey,
                    'KeyMd5' => $aesMD5,
                    'InitializationVector' => $aesIV,
                    'LicenseAcquisitionUrl' => $keyUrl,
                    'KeyStoragePolicy' => 'NoStore',
                ],
            ],
        ],
        /* 'UserMetadata' => '', // @todo Could use some how */
    ];

	echo '<h3>Job description</h3>';
	printf( '<pre>%s</pre>', var_export( $jobDescription, true ) );

	// Create an SDK class used to share configuration across clients.
	$sdk = new Aws\Sdk( $awsApiConfig );

	echo '<h3>Got ElasticTranscoder client</h3><pre>';
	$client = $sdk->createElasticTranscoder();

    $result = $client->createJob( $jobDescription );

	echo '</pre><h3>Result</h3><pre>';
	var_dump( $result );

	// Remember key mapping
	$conf[ $_REQUEST['id'] ] = [ 'jobId' => $result['Job']['Id'] ];

	set_config( 'aeskeys', json_encode($conf), 'filter_cloudfront_hlsaes' );


} else {
    http_response_code( 400 ); die;
}

} catch ( \Exception $e ) {
	printf( '</pre><p><strong>%s</strong></p>', $e->getMessage() );
}


function aws_outputConfig( $preset, $outputName )
{
    return [
        'Key' => aws_outputNameForPreset( $preset, $outputName ),
        /*
		 * 'ThumbnailPattern' => '',
         * 'ThumbnailEncryption' => '',
		 */
        'Rotate' => 'auto',
        'PresetId' => $preset,
        'SegmentDuration' => '30', // @todo Config? Param?
		/*
		 * 'Watermarks' => '',
		 * 'AlbumArt' => '',
         * 'Composition' => '',
         * 'Captions' => '',
         * 'Encryption' => '',
		 */
    ];
}

function aws_outputNameForPreset( $preset, $outputName )
{
	return "{$outputName}-{$preset}";
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

class PlaySMS_SenderFields {
    public $apiKey;
    public $apiPassword;

    public function __construct( $apiKey, $apiPassword ) {
        $this->setApiKey( $apiKey );
        $this->setApiPassword( $apiPassword );
    }

    public function setApiKey( $apiKey ) {
        $this->apiKey = $apiKey;
    }

    public function setApiPassword( $apiPassword ) {
        $this->apiPassword = $apiPassword;
    }

    public function getSenderFields() {

        $url = 'https://api.playsms.pl/senderFields';

        $responseHTTP = wp_remote_post( $url, array(
                'method'      => 'POST',
                'timeout'     => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => array(),
                'body'        => array(
                        'key'      => $this->apiKey,
                        'password' => $this->apiPassword
                ),
                'cookies'     => array()
            )
        );


        if ( is_wp_error( $responseHTTP ) ) {
            $response = 0;
        } else {
            $response = $responseHTTP['body'];
        }

        return $response;
    }

}


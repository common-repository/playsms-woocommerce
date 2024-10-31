<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

class PlaySMS_SendSms {

    private $apiUserName;
    private $apiPassword;
    private $to = null;
    private $message = null;
    private $format = 'json';
    private $header = null;
    private $clearPolish;
    private $test = 0;

    public function __construct( $apiUserName, $apiPassword, $to, $message, $test, $header, $clearPolish, $format ) {
        $this->setTo( $to );
        $this->setMessage( $message );
        $this->setApiUserName( $apiUserName );
        $this->setApiPassword( $apiPassword );
        $this->setTest( $test );
        $this->setHeader( $header );
        $this->setClearPolish( $clearPolish );
    }

    public function setApiUserName( $apiUserName ) {
        $this->apiUserName = $apiUserName;
    }

    public function setHeader( $header ) {
        if ( $header !== null ) {
            $this->header = $header;
        }
    }

    public function setApiPassword( $apiPassword ) {
        $this->apiPassword = $apiPassword;
    }

    public function setResponseFormat( $format ) {
        $this->format = $format;
    }

    public function setTo( $to ) {
        $this->to = $to;
    }

    public function setMessage( $message ) {
        $this->message = $message;
    }

    public function setTest( $test ) {
        $this->test = $test;
    }

    public function setClearPolish( $clearPolish ) {
        $this->clearPolish = $clearPolish;
    }

    public function send() {
        $newsXML        = new SimpleXMLElement( "<?xml version=\"1.0\" encoding=\"UTF-8\"?><data/>" );
        $key            = $newsXML->addChild( 'key', $this->apiUserName );
        $password       = $newsXML->addChild( 'password', $this->apiPassword );
        $sms            = $newsXML->addChild( 'sms' );
        $responseFormat = $sms->addChild( 'format', $this->format );
        $msg            = $sms->addChild( 'msg', $this->message );
        $header         = $sms->addChild( 'from', $this->header );
        $to             = $sms->addChild( 'to', $this->to );
        $clear_polish   = $sms->addChild( 'clear_polish', $this->clearPolish );
        $test           = $sms->addChild( 'test', $this->test );

        $xmlObjectToString = (string) $newsXML->asXML();

        $url = 'https://api.playsms.pl';

        $responseHTTP = wp_remote_post( $url, array(
                'method'      => 'POST',
                'timeout'     => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'headers'     => array(),
                'body'        => array(
                        'xmldata' => $xmlObjectToString
                ),
                'sslverify'   => 'false'
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
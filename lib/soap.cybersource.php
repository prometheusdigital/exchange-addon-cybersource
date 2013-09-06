<?php
/**
 * Class CyberSourceSoap
 *
 * @see SoapClient
 */
class CyberSource_SoapClient extends SoapClient {

	/**
	 * @var string CyberSource Username
	 */
	private $username;

	/**
	 * @var string CyberSource Password
	 */
	private $password;

	/**
	 * Init SoapClient for CyberSourceSoap
	 *
	 * @param mixed $wsdl
	 * @param array $options [optional]
	 */
	public function __construct( $wsdl, array $options = null ) {

		parent::__construct( $wsdl, $options );

	}

	/**
	 * Set username and password for CyberSource
	 *
	 * @param string $username
	 * @param string $password
	 */
	public function set_credentials( $username, $password ) {

		$this->username = $username;
		$this->password = $password;

	}

	/**
	 * Add CyberSource SOAP auth header for SoapClient requests
	 *
	 * @param string $request
	 * @param string $location
	 * @param string $action
	 * @param int $version
	 *
	 * @return string
	 */
	public function __doRequest( $request, $location, $action, $version ) {

		// Build SOAP header
		$soap_header = '<SOAP-ENV:Header xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">'
					   . '<wsse:Security SOAP-ENV:mustUnderstand="1">'
						   . '<wsse:UsernameToken>'
							   . '<wsse:Username>%s</wsse:Username>'
							   . '<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">%s</wsse:Password>'
						   . '</wsse:UsernameToken>'
					   . '</wsse:Security>'
				   . '</SOAP-ENV:Header>';

		// Send SOAP header with Username / Password
		$soap_header = sprintf( $soap_header, $this->username, $this->password );

		// Add to top of request
		$request = $soap_header . $request;

		// Proceed as planned
		return parent::__doRequest( $request, $location, $action, $version );

	}

}

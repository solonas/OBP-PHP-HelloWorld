<?php
/**
 * documentation:  https://github.com/OpenBankProject/OBP-API/wiki/OAuth-1.0-Server
 *
 * register sandbox api : https://apisandbox.openbankproject.com/consumer-registration
 *
 * tested with php version 5.6.1
 *
 *
 */

$baseUrlRealData= 'https://api.openbankproject.com';
$baseUrlSandBox = 'https://apisandbox.openbankproject.com';
$baseUrl        = $baseUrlSandBox;
$apiVersion     = 'v1.2';

session_save_path('/tmp/');
session_start();

$obpApiSettings = array(
	'consumer'  => array(
		'key'   => '',
		'secret'=> '',
	),
	'url' => array(
		'token' => array(
			'request'   => "$baseUrl/oauth/initiate",
			'access'    => "$baseUrl/oauth/token",
		),
		'auth'      => "$baseUrl/oauth/authorize",
		'api'       => "$baseUrl/obp/$apiVersion",
	)
);

$oAuth = new \OAuth( $obpApiSettings['consumer']['key'], $obpApiSettings['consumer']['secret'] );
$oAuth->enableDebug();  // in case of exception debug $oAuth->debugInfo

if ( !isset($_GET['oauthcallback']) || $_GET['oauthcallback'] != 1 || !isset($_GET['oauth_verifier']) ) {
	try {
		oAuthAction($oAuth, $obpApiSettings['url']['token']['request'], $obpApiSettings['url']['auth']);
	} catch ( \OAuthException $ex ) {
		$message = $ex->lastResponse.'_ : _'.$ex->getMessage();
		die($message);
	}
} else {
	if ( !isset($_SESSION['oauth_token_access']) || !isset($_SESSION['oauth_token_secret_access']) ) {
		if ( !isset($_SESSION['oauth_token'])
		     || !isset($_SESSION['oauth_token_secret'])
		     || $_SESSION['oauth_token'] != $_GET['oauth_token'])
		{
			$message = 'Expecting oauth_token and oauth_token_secret, restart oAuth process';
			die($message);
		}

		try {
			$oAuth->setToken($_SESSION['oauth_token'],$_SESSION['oauth_token_secret']);

			oAuthCallBackAction($oAuth, $obpApiSettings['url']['token']['access'], $_GET['oauth_verifier']);
		} catch ( \OAuthException $ex ) {
			$message = $ex->lastResponse.'_ : _'.$ex->getMessage();
			die($message);
		}
	}

	$oAuth->setToken( $_SESSION['oauth_token_access'], $_SESSION['oauth_token_secret_access'] );

	try {
		loadAndPrintoutBanks($oAuth, $obpApiSettings['url']['api']);
	} catch (\Exception $exception) {
		$message = 'Internal Error: '.$exception->getMessage();
		die($message);
	}
}

/**
 * Step 1 and Step 2
 *
 * @param \OAuth $oAuth
 * @param string $requestTokenUrl
 * @param string $authUrl
 *
 * @throws Exception
 */
function oAuthAction($oAuth, $requestTokenUrl, $authUrl) {
	$callBackUri = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on')) ? 'https://' : 'http://';
	$callBackUri .= $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?oauthcallback=1';

	// Step 1 : Obtaining a request token :
	$requestTokenInfo = $oAuth->getRequestToken( $requestTokenUrl, $callBackUri, OAUTH_HTTP_METHOD_POST );

	if ( $requestTokenInfo['oauth_callback_confirmed'] == 'true' ) {
		$_SESSION['oauth_token']        = $requestTokenInfo['oauth_token'];
		$_SESSION['oauth_token_secret'] = $requestTokenInfo['oauth_token_secret'];
		session_write_close();
	} else {
		$message = "oauth_callback_confirmed returned from server expected to be 'true', got: ";
		$message .= "'{$requestTokenInfo['oauth_callback_confirmed']}'";
		throw new \Exception($message);
	}

	// Step 2 : Redirecting the user:
	$redirectUri = $authUrl.'?oauth_token='.$requestTokenInfo['oauth_token'];
	header('Location: '.$redirectUri);
}

/**
 * Step 3
 *
 * @param \OAuth $oAuth
 * @param string $accessTokenUrl
 * @param string $oAuthVerifier
 */
function oAuthCallBackAction($oAuth, $accessTokenUrl, $oAuthVerifier) {
	// Step 3 : Converting the request token to an access token
	$accessTokenInfo = $oAuth->getAccessToken( $accessTokenUrl, null, $oAuthVerifier, OAUTH_HTTP_METHOD_POST );

	$_SESSION['oauth_token_access'] = $accessTokenInfo['oauth_token'];
	$_SESSION['oauth_token_secret_access'] = $accessTokenInfo['oauth_token_secret'];
	session_write_close();
}

/**
 * Step 4
 *
 * @param \OAuth $oAuth
 * @param string $apiUrl
 */
function loadAndPrintoutBanks($oAuth, $apiUrl) {
	// Step 4 : Accessing protected resources :
	$oAuth->fetch( $apiUrl . '/banks' );

	$response = json_decode($oAuth->getLastResponse());

	echo "<table>";
	echo "<thead><tr><th>ID</th><th>Short Name</th><th>Full Name</th><th>LOGO</th><th>Website</th></tr></thead>";
	foreach($response->banks as $bank) {
		echo "<tr>";
		echo "<td>$bank->id</td>";
		echo "<td>$bank->short_name</td>";
		echo "<td>$bank->full_name</td>";
		if ( $bank->logo ) {
			echo "<td><img height='25px' src='$bank->logo'></td>";
		} else {
			echo "<td></td>";
		}
		echo "<td>$bank->website</td>";
		echo "</tr>";
	}
	echo "</table>";
}
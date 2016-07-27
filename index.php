<?php
/**
 * Xero invoice creation demo
 * 
 * Written to help provide an answer to this Stack Overflow question: http://stackoverflow.com/q/26250530/628267
 */
 
// Includes and defines
include 'lib/XeroOAuth.php';

define ( "XRO_APP_TYPE", "Public" );
define ( "OAUTH_CALLBACK", XeroOAuth::php_self () );
$useragent = "Xero-OAuth-PHP Public";

$signatures = array (
        'consumer_key' => 'VSXN6BIM5PBTIPLVEIVSSUBFQTXUAF',
        'shared_secret' => 'ETEHTOAH24XAXHHNYNOBNWLPUXUOJU',
		// API versions
		'core_version' => '2.0',
		'payroll_version' => '1.0',
		'file_version' => '1.0' 
);

$XeroOAuth = new XeroOAuth ( array_merge ( array (
		'application_type' => XRO_APP_TYPE,
		'oauth_callback' => OAUTH_CALLBACK,
		'user_agent' => $useragent 
), $signatures ) );

// Make sure the config is all ok
$initialCheck = $XeroOAuth->diagnostics ();
$checkErrors = count ( $initialCheck );
if ($checkErrors > 0) {
	foreach ( $initialCheck as $check ) {
		echo 'Error: ' . $check . '<br>';
        die();
	}
} 
	
$here = XeroOAuth::php_self ();
session_start ();
$oauthSession = retrieveSession ();

// No oauth details, start the OAuth process
if ($oauthSession == "" &&  !isset ( $_REQUEST ['oauth_verifier'])) {
    
    // Fetch an OAuth request token (step 1)
    $params = array (
        'oauth_callback' => OAUTH_CALLBACK 
    );    
    $response = $XeroOAuth->request ( 'GET', $XeroOAuth->url ( 'RequestToken', '' ), $params );
    
    if ($XeroOAuth->response ['code'] == 200) {		
        
        // Redirect to Xero so the user can authorise the app (step 2)	
        $scope = "";
        $_SESSION ['oauth'] = $XeroOAuth->extract_params ( $XeroOAuth->response ['response'] );		
        
        $authurl = $XeroOAuth->url ( "Authorize", '' ) . "?oauth_token={$_SESSION['oauth']['oauth_token']}&scope=" . $scope;        
        echo '<p>To authorise this application, go to this URL: <a href="' . $authurl . '">' . $authurl . '</a></p>';
        
    } else {
        echo 'Failed to get request token:<br>';
        outputError ( $XeroOAuth );
    }
    
// Swap the request token for an access token (step 3)
} elseif (isset ( $_REQUEST ['oauth_verifier'] )) {
    $XeroOAuth->config['access_token'] = $_SESSION ['oauth'] ['oauth_token'];
    $XeroOAuth->config['access_token_secret'] = $_SESSION ['oauth'] ['oauth_token_secret'];

    $code = $XeroOAuth->request ( 'GET', $XeroOAuth->url ( 'AccessToken', '' ), array (
        'oauth_verifier' => $_REQUEST ['oauth_verifier'],
        'oauth_token' => $_REQUEST ['oauth_token'] 
    ) );
    
    if ($XeroOAuth->response ['code'] == 200) {
        // Store the access token in the session and refresh the page.
        $response = $XeroOAuth->extract_params ( $XeroOAuth->response ['response'] );
        $session = persistSession ( $response );			
        unset ( $_SESSION ['oauth'] );
        header ( "Location: {$here}" );
    } else {
        echo 'Failed to get access token:<br>';
        outputError ( $XeroOAuth );
    }

// OAuth token found, lets use it!
} 
else {

    $XeroOAuth->config ['access_token'] = $oauthSession ['oauth_token'];
    $XeroOAuth->config ['access_token_secret'] = $oauthSession ['oauth_token_secret']; 
    
    $xml = "<Invoices>    
            <Invoice>    
            <Type>ACCREC</Type>    
            <Contact>        
            <Name>Ima Test</Name>        
            </Contact>        
            <Date>2014-05-13T00:00:00</Date>        
            <DueDate>".date('c')."</DueDate>    
            <LineAmountTypes>Exclusive</LineAmountTypes>    
            <LineItems>    
            <LineItem>    
            <Description>Demo invoice</Description>    
            <Quantity>4.3400</Quantity>    
            <UnitAmount>395.00</UnitAmount>    
            <AccountCode>200</AccountCode>    
            </LineItem>    
            </LineItems>    
            </Invoice>    
            </Invoices>";
    
    $XeroOAuth->request('POST', $XeroOAuth->url('Invoices', 'core'),  array(), $xml);
    
    if ($XeroOAuth->response ['code'] == 200) {
        // Invoice created
        $xmlResponse = new SimpleXMLElement($XeroOAuth->response ['response']);
        echo "Invoice created<br><br> Result: {$xmlResponse->Status}<br>";
        echo "Invoice ID: {$xmlResponse->Id}<br>";
        echo "Invoice Number: {$xmlResponse->Invoices->Invoice->InvoiceNumber}<br>";
		
    } else {            
        $response = $XeroOAuth->extract_params ( $XeroOAuth->response ['response']);            
        // Public tokens only last 30 mins. 
        if ($response['oauth_problem'] == 'token_expired') {
            unset($_SESSION['access_token']);
            echo 'Expired session - <a href="?">restart the oauth process</a>';
        } else {        
            echo 'Failed to create invoice:<br>';
            outputError ( $XeroOAuth );
        }
    }
	session_destroy();
	
}

// Handy functions from the XeroAPI test class

function retrieveSession()
{
    if (isset($_SESSION['access_token'])) {
        $response['oauth_token'] = $_SESSION['access_token'];
        $response['oauth_token_secret'] = $_SESSION['oauth_token_secret'];
        if(isset($_SESSION['session_handle'])) 
            $response['oauth_session_handle'] = $_SESSION['session_handle'];
        return $response;
    } else {
        return false;
    }
}

function persistSession($response)
{
    if (isset($response)) {
        $_SESSION['access_token'] = $response['oauth_token'];
        $_SESSION['oauth_token_secret'] = $response['oauth_token_secret'];
        if(isset($response['oauth_session_handle'])) $_SESSION['session_handle'] = $response['oauth_session_handle'];
        var_dump($response);
    } else {
        return false;
    }
}

function outputError($XeroOAuth)
{
    echo 'Error: ' . $XeroOAuth->response['response'] . PHP_EOL;
    pr($XeroOAuth);
}

function pr($obj)
{
    if (!is_cli())
        echo '<pre style="word-wrap: break-word">';
    if (is_object($obj))
        print_r($obj);
    elseif (is_array($obj))
        print_r($obj);
    else
        echo $obj;
    if (!is_cli())
        echo '</pre>';
}

?>
<a href="logout.php">Send Invoice</a>

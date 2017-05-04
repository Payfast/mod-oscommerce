<?php
/**
 * payfast.php
 *
 * Copyright (c) 2008 PayFast (Pty) Ltd
 * You (being anyone who is not PayFast (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active PayFast account. If your PayFast account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.
 * 
 * @author     Jonathan Smit
 * @link       http://www.payfast.co.za/help/oscommerce
 */
chdir( '../../../../' );
require( 'includes/application_top.php' );

//// Check if module is enabled before processing
if( !defined( 'MODULE_PAYMENT_PAYFAST_STATUS' ) || ( MODULE_PAYMENT_PAYFAST_STATUS  != 'True' ) )
    exit;

// Include PayFast common file
define( 'PF_DEBUG', ( strcasecmp( MODULE_PAYMENT_PAYFAST_DEBUG, 'True' ) == 0 ? true : false ) );
include_once( 'includes/modules/payment/payfast_common.inc' );

// Variable Initialization
$pfError = false;
$pfNotes = array();
$pfData = array();
$pfHost = ( ( strcasecmp( MODULE_PAYMENT_PAYFAST_SERVER, 'Live' ) != 0 ) ? 'sandbox' : 'www' ) .'.payfast.co.za';
$orderId = '';
$pfParamString = '';

$pfErrors = array();

pflog( 'PayFast ITN call received' );

//// Set debug email address
$pfDebugEmail = ( strlen( MODULE_PAYMENT_PAYFAST_DEBUG_EMAIL ) > 0 ) ?
    MODULE_PAYMENT_PAYFAST_DEBUG_EMAIL : STORE_OWNER_EMAIL_ADDRESS;

pflog( 'Debug email address = '. $pfDebugEmail );

//// Notify PayFast that information has been received
if( !$pfError )
{
    header( 'HTTP/1.0 200 OK' );
    flush();
}

//// Get data sent by PayFast
if( !$pfError )
{
    pflog( 'Get posted data' );

    // Posted variables from ITN
    $pfData = pfGetData();

    pflog( 'PayFast Data: '. print_r( $pfData, true ) );

    if( $pfData === false )
    {
        $pfError = true;
        $pfNotes[] = PF_ERR_BAD_ACCESS;
    }
}

//// Verify security signature
if( !$pfError )
{
    pflog( 'Verify security signature' );

    // If signature different, log for debugging
    if( !pfValidSignature( $pfData, $pfParamString ) )
    {
        $pfError = true;
        $pfNotes[] = PF_ERR_INVALID_SIGNATURE;
    }
}

//// Verify source IP (If not in debug mode)
if( !$pfError && !PF_DEBUG )
{
    pflog( 'Verify source IP' );
    
    if( !pfValidIP( $_SERVER['REMOTE_ADDR'] ) )
    {
        $pfError = true;
        $pfNotes[] = PF_ERR_BAD_SOURCE_IP;
    }
}

//// Retrieve order from eCommerce System
if( !$pfError )
{
    pflog( 'Get order' );
}

//// Verify data
if( !$pfError )
{
    pflog( 'Verify data received' );

    if( $config['proxy'] == 1 )
        $pfValid = pfValidData( $pfHost, $pfParamString, $config['proxyHost'] .":". $config['proxyPort'] );
    else
        $pfValid = pfValidData( $pfHost, $pfParamString );

    if( !$pfValid )
    {
        $pfError = true;
        $pfNotes[] = PF_ERR_BAD_ACCESS;
    }
}

//// Check status and update order & transaction table
if( !$pfError )
{
    pflog( 'Check status and update order' );
    
    // Get order
    $orderId = (int)$pfData['m_payment_id'];
    $order_query = tep_db_query(
        "SELECT `orders_status`, `currency`, `currency_value`
        FROM `". TABLE_ORDERS ."`
        WHERE `orders_id` = '" . $orderId . "'
          AND `customers_id` = '" . $pfData['custom_int1'] . "'" );
    
    // If order found
    if( tep_db_num_rows( $order_query ) > 0 )
    {
        // Get order details
        $order = tep_db_fetch_array( $order_query );

        // If order in "Preparing" state, update to "Pending"
        if( $order['orders_status'] == MODULE_PAYMENT_PAYFAST_PREPARE_ORDER_STATUS_ID )
        {
            $sql_data_array = array(
                'orders_id' => $orderId,
                'orders_status_id' => MODULE_PAYMENT_PAYFAST_PREPARE_ORDER_STATUS_ID,
                'date_added' => 'now()',
                'customer_notified' => '0',
                'comments' => '' );

            tep_db_perform( TABLE_ORDERS_STATUS_HISTORY, $sql_data_array );

            // Update order status
            tep_db_query(
                "UPDATE ". TABLE_ORDERS ."
                SET `orders_status` = '". ( MODULE_PAYMENT_PAYFAST_ORDER_STATUS_ID > 0 ?
                  (int)MODULE_PAYMENT_PAYFAST_ORDER_STATUS_ID : (int)DEFAULT_ORDERS_STATUS_ID) . "',
                  `last_modified` = NOW()
                WHERE `orders_id` = '". $orderId ."'" );
        }

        // Get order total
        $total_query = tep_db_query(
            "SELECT `value`
            FROM `". TABLE_ORDERS_TOTAL ."`
            WHERE `orders_id` = '" . $orderId . "'
              AND `class` = 'ot_total'
            LIMIT 1" );
        $total = tep_db_fetch_array( $total_query );

        // Add comment to order history
        $comment_status = "Trn ID ". $pfData['pf_payment_id'];
        $comment_status = $pfData['payment_status'] .' ('. $currencies->format( $pfData['amount_gross'], false, 'ZAR' ) .')';

        $orderValue = number_format( $total['value'] * $order['currency_value'], $currencies->get_decimal_places( $order['currency'] ), '.', '' );
        if( $pfData['amount_gross'] != $orderValue )
        {
            $comment_status .= '; PayFast transaction value ('. tep_output_string_protected( $pfData['amount_gross'] ) .') does not match order value ('. $orderValue .')';
            $pfError = true;
            $pfErrMsg = PF_ERR_AMOUNT_MISMATCH;
        }

        $sql_data_array = array(
            'orders_id' => $orderId,
            'orders_status_id' => ( MODULE_PAYMENT_PAYFAST_ORDER_STATUS_ID > 0 ?
                (int)MODULE_PAYMENT_PAYFAST_ORDER_STATUS_ID : (int)DEFAULT_ORDERS_STATUS_ID ),
            'date_added' => 'now()',
            'customer_notified' => '0',
            'comments' => 'PayFast ITN received ['. $comment_status .']'
            );

        tep_db_perform( TABLE_ORDERS_STATUS_HISTORY, $sql_data_array );
    }
}

// If an error occurred
if( $pfError )
{
    pflog( 'Error occurred: '. $pfErrMsg );
    pflog( 'Sending email notification' );

    // Compose email to send
    $subject = "PayFast ITN error: ". $pfErrMsg;
    $body =
        "Hi,\n\n".
        "An invalid PayFast transaction on your website requires attention\n".
        "------------------------------------------------------------\n".
        "Site: ". $vendor_name ." (". $vendor_url .")\n".
        "Remote IP Address: ".$_SERVER['REMOTE_ADDR']."\n".
        "Remote host name: ". gethostbyaddr( $_SERVER['REMOTE_ADDR'] ) ."\n".
        "Order ID: ". $pfData['m_payment_id'] ."\n";
    if( isset( $pfData['pf_payment_id'] ) )
        $body .= "PayFast Transaction ID: ". $pfData['pf_payment_id'] ."\n";
    if( isset( $pfData['payment_status'] ) )
        $body .= "PayFast Payment Status: ". $pfData['payment_status'] ."\n";
    $body .=
        "\nError: ". $pfErrMsg ."\n";

    switch( $pfErrMsg )
    {
        case PF_ERR_AMOUNT_MISMATCH:
            $body .=
                "Value received : ". $pfData['amount_gross'] ."\n".
                "Value should be: ". $orderValue;
            break;

        // For all other errors there is no need to add additional information
        default:
            break;
    }

    // Send email
    tep_mail( '', $pfDebugEmail, $subject, $body, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS );
}

// Close log
pflog( '', true );

require('includes/application_bottom.php');
?>
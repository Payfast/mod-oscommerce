<?php
/**
 * payfast.php
 *
 * Stores definable information for PayFast payment module
 *
 * Copyright (c) 2008 PayFast (Pty) Ltd
 * You (being anyone who is not PayFast (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active PayFast account. If your PayFast account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.
 * 
 * @author     Jonathan Smit
 * @link       http://www.payfast.co.za/help/oscommerce
 */
// General
define( 'MODULE_PAYMENT_PAYFAST_TEXT_TITLE', 'PayFast');
define( 'MODULE_PAYMENT_PAYFAST_TEXT_PUBLIC_TITLE', 'PayFast');
define( 'MODULE_PAYMENT_PAYFAST_TEXT_DESCRIPTION', '<img src="images/icon_popup.gif" border="0">&nbsp;<a href="https://www.payfast.co.za" target="_blank" style="text-decoration: underline; font-weight: bold;">Visit Payfast Website</a>');

// Errors
define( 'MODULE_PAYMENT_PAYFAST_ERR_PDT_TOKEN_MISSING', 'PDT token not present in URL' );
define( 'MODULE_PAYMENT_PAYFAST_ERR_ORDER_ID_MISSING_URL', 'Order ID not present in URL' );
define( 'MODULE_PAYMENT_PAYFAST_ERR_ORDER_PROCESSED', 'This order has already been processed' );
define( 'MODULE_PAYMENT_PAYFAST_ERR_ORDER_INVALID', 'This order ID is invalid' );
define( 'MODULE_PAYMENT_PAYFAST_ERR_CONNECT_FAILED', 'Failed to connect to PayFast' );
define( 'MODULE_PAYMENT_PAYFAST_ERR_ID_MISMATCH', 'Order ID mismatch' );
define( 'MODULE_PAYMENT_PAYFAST_ERR_PAYMENT_NOT_COMPLETE', 'Payment is not yet COMPLETE' );
?>
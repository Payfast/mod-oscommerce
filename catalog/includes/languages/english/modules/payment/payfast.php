<?php
/**
 * payfast.php
 *
 * Stores definable information for PayFast payment module
 *
 * Copyright (c) 2008-2011 PayFast (Pty) Ltd
 * 
 * LICENSE:
 * 
 * This payment module is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation; either version 3 of the License, or (at
 * your option) any later version.
 * 
 * This payment module is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public
 * License for more details.
 * 
 * @author     Jonathan Smit
 * @copyright  2008-2011 PayFast (Pty) Ltd
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
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
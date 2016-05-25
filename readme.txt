PayFast osCommerce Module v1.22 for osCommerce 2.3.1
----------------------------------------------------
Copyright � 2008-2016 PayFast (Pty) Ltd

LICENSE:
 
This payment module is free software; you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published
by the Free Software Foundation; either version 3 of the License, or (at
your option) any later version.

This payment module is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public
License for more details.

Please see http://www.opensource.org/licenses/ for a copy of the GNU Lesser
General Public License.

INTEGRATION:
1. Unzip the module to a temporary location on your computer
2. Copy and paste the files into your osCommerce installation as they were extracted
- This should NOT overwrite any existing files or folders and merely supplement them with the PayFast files
- This is however, dependent on the FTPprogram you use
3. Login to the osCommerce admin console
4. Navigate to Modules ? Payment
5. Click the “Install Module” button on the right hand side
6. Select “PayFast” from the list
7. Click the “Install Module” button
8. Click the “Edit” button on the right hand pane
9. Change the value for “Enable PayFast” to “True”
10. Scroll down to the bottom of the right hand pane and press the “Save” button
11. The module is now operating in “test mode” and is ready to be tested with the Sandbox. To test with the sandbox, use the following login credentials when redirected to the PayFast site:
- Username: sbtu01@payfast.co.za
- Password: clientpass

I”m ready to go live! What do I do?
In order to make the module “Live”, follow the instructions below:

1. Login to the osCommerce admin console
2. Using the main menu, navigate to Modules ? Payment
3. Select the “PayFast” payment method by clicking on it
4. Click the “Edit” button on the right hand pane
5. Update the configuration values as detailed below:
6. Enable PayFast? = True
7. Merchant ID = https://www.payfast.co.za/acc/integration>
8. Merchant Key = https://www.payfast.co.za/acc/integration>
9. Transaction Server = “Live”
(Change the other fields as per your preferences)
10. Scroll down to the bottom of the right hand pane and press the “Save” button
The module is now ready to receive live payments.


******************************************************************************
*                                                                            *
*    Please see the URL below for all information concerning this module:    *
*                                                                            *
*                  https://www.payfast.co.za/shopping-carts/oscommerce/      *
*                                                                            *
******************************************************************************
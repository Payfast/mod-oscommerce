<?php

/**
 * This file is part of Payfast Pay.
 *
 * @link http://www.payfast.io
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace common\modules\orderPayment;

require_once __DIR__ . '/lib/payfast/vendor/autoload.php';

use common\helpers\OrderPayment as OrderPaymentHelper;
use mysql_xdevapi\Exception;
use Payfast\PayfastCommon\PayfastCommon;
use Yii;
use common\classes\modules\ModulePayment;
use common\classes\modules\ModuleStatus;
use common\classes\modules\ModuleSortOrder;
use backend\services\OrdersService;

use function PHPUnit\Framework\isEmpty;

/**
 * Class payfast
 */
class payfast extends ModulePayment
{

    const TRANSACT_MODE_URL = "www.payfast.co.za";
    const TEST_MODE_URL     = "sandbox.payfast.co.za";
    const AS_TEMP_ORDER     = false; //2do transform to real order on callback

    private $_payfastUrl = self::TRANSACT_MODE_URL;
    private $_payfastMechentId;
    private $_payfastMechentKey;

    public $countries = [];
    public $paid_status;
    public $processing_status;
    public $fail_paid_status;
    public $public_title;

    protected $defaultTranslationArray = [
        'MODULE_PAYMENT_PAYFAST_TEXT_TITLE'       => 'Payfast',
        'MODULE_PAYMENT_PAYFAST_TEXT_DESCRIPTION' => 'Payfast',
        'MODULE_PAYMENT_PAYFAST_TEXT_NOTES'       => ''
    ];

    public function __construct()
    {
        parent::__construct();

        $this->countries   = [];
        $this->code        = 'payfast';
        $this->title       = defined(
            'MODULE_PAYMENT_PAYFAST_TEXT_TITLE'
        ) ? MODULE_PAYMENT_PAYFAST_TEXT_TITLE : 'Payfast';
        $this->description = defined(
            'MODULE_PAYMENT_PAYFAST_TEXT_DESCRIPTION'
        ) ? MODULE_PAYMENT_PAYFAST_TEXT_DESCRIPTION : 'Payfast';
        $this->enabled     = true;

        $this->setUrl();

        if (!defined('MODULE_PAYMENT_PAYFAST_STATUS')) {
            $this->enabled = false;

            return;
        }
        $this->_payfastMechentKey = defined('MODULE_PAYMENT_PAYFAST_KEY') ? MODULE_PAYMENT_PAYFAST_KEY : '';
        $this->_payfastMechentId  = defined('MODULE_PAYMENT_PAYFAST_ID') ? MODULE_PAYMENT_PAYFAST_ID : '';
        $this->paid_status        = MODULE_PAYMENT_PAYFAST_ORDER_PAID_STATUS_ID;
        $this->processing_status  = MODULE_PAYMENT_PAYFAST_ORDER_PROCESS_STATUS_ID;
        $this->fail_paid_status   = MODULE_PAYMENT_PAYFAST_FAIL_PAID_STATUS_ID;

        $this->ordersService = \Yii::createObject(OrdersService::class);
        $this->update();
    }

    public function updateTitle($platformId = 0)
    {
        $mode = $this->get_config_key((int)$platformId, 'MODULE_PAYMENT_PAYFAST_TEST_MODE');
        if ($mode !== false) {
            $mode  = strtolower($mode);
            $title = (defined('MODULE_PAYMENT_PAYFAST_TEXT_TITLE') ? constant(
                'MODULE_PAYMENT_PAYFAST_TEXT_TITLE'
            ) : '');
            if ($title != '') {
                $this->title = $title;
                if ($mode == 'true') {
                    $this->title .= ' [Test]';
                }
            }
            $titlePublic = (defined('MODULE_PAYMENT_PAYFAST_TEXT_TITLE') ? constant(
                'MODULE_PAYMENT_PAYFAST_TEXT_TITLE'
            ) : '');
            if ($titlePublic != '') {
                $this->public_title = $titlePublic;
                if ($mode == 'true') {
                    $this->public_title .= " [{$this->code}; Test]";
                }
            }

            return true;
        }

        return false;
    }


    public function getTitle($method = '')
    {
        return $this->public_title;
    }

    /**
     * @param bool $pfError
     * @param bool $pfDone
     *
     * @return void
     */
    public function notifyPF(bool $pfError, bool $pfDone): void
    {
        if (!$pfError && !$pfDone) {
            header('HTTP/1.0 200 OK');
            flush();
        }
    }

    /**
     * @param $order
     * @param string $comment_status
     * @param int $orderID
     *
     * @return void
     */
    public function ifOrder($order, string $comment_status, int $orderID): void
    {
        if ($order) {
            $order->info['order_status'] = 3;

            $order->info['comments'] = $comment_status;

            $order->update_piad_information(true);

            $order->save_order($orderID);
        }
    }

    /**
     * @param $orders_status
     * @param int $orderID
     *
     * @return array
     */
    public function updateOrderState($orders_status, int $orderID): array
    {
        if ($orders_status == MODULE_PAYMENT_PAYFAST_ORDER_PROCESS_STATUS_ID) {
            $sql_data_array = array(
                'orders_id'         => $orderID,
                'orders_status_id'  => MODULE_PAYMENT_PAYFAST_ORDER_PROCESS_STATUS_ID,
                'date_added'        => 'now()',
                'customer_notified' => '0',
                'comments'          => ''
            );

            tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

            // Update order status
            tep_db_query(
                "UPDATE " . TABLE_ORDERS . "
                SET `orders_status` = '" . (MODULE_PAYMENT_PAYFAST_ORDER_PROCESS_STATUS_ID > 0 ?
                    (int)MODULE_PAYMENT_PAYFAST_ORDER_PROCESS_STATUS_ID : (int)DEFAULT_ORDERS_STATUS_ID) . "',
                  `last_modified` = NOW()
                WHERE `orders_id` = '" . $orderID . "'"
            );
        }

        return $sql_data_array;
    }

    /**
     * @param bool $pfError
     * @param PayfastCommon $payfastCommon
     * @param string $pfErrMsg
     * @param mixed $pfData
     * @param string $orderValue
     *
     * @return void
     */
    public function ifAnErrorOccurred(
        bool $pfError,
        PayfastCommon $payfastCommon,
        string $pfErrMsg,
        mixed $pfData,
        string $orderValue
    ): void {
        if ($pfError) {
            $payfastCommon->pflog('Error occurred: ' . $pfErrMsg);
            $payfastCommon->pflog('Sending email notification');

            // Compose email to send
            $subject = "Payfast ITN error: " . $pfErrMsg;
            $body    =
                "Hi,\n\n" .
                "An invalid Payfast transaction on your website requires attention\n" .
                "------------------------------------------------------------\n" .
                "Site: " . STORE_NAME . ")\n" .
                "Remote IP Address: " . $_SERVER['REMOTE_ADDR'] . "\n" .
                "Remote host name: " . gethostbyaddr($_SERVER['REMOTE_ADDR']) . "\n" .
                "Order ID: " . $pfData['m_payment_id'] . "\n";
            if (isset($pfData['pf_payment_id'])) {
                $body .= "Payfast Transaction ID: " . $pfData['pf_payment_id'] . "\n";
            }
            if (isset($pfData['payment_status'])) {
                $body .= "Payfast Payment Status: " . $pfData['payment_status'] . "\n";
            }
            if ($pfErrMsg === $payfastCommon::PF_ERR_AMOUNT_MISMATCH) {
                $body .=
                    "Value received : " . $pfData['amount_gross'] . "\n" .
                    "Value should be: " . $orderValue;
            }
            $body .=
                "\nError: " . $pfErrMsg . "\n";

            if ($pfErrMsg === $payfastCommon::PF_ERR_AMOUNT_MISMATCH) {
                $body .=
                    "Value received : " . $pfData['amount_gross'] . "\n" .
                    "Value should be: " . $orderValue;
            }
            // Send email
        }
    }

    /**
     * @param bool $mode
     *
     * @return string
     */
    public function getHost(bool $mode): string
    {
        return $mode ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';
    }

    private function update()
    {
        if (!$this->_payfastMechentId || !$this->_payfastMechentKey) {
            $this->enabled = false;
        }
    }

    private function setUrl()
    {
        if (defined('MODULE_PAYMENT_PAYFAST_TEST_MODE') && MODULE_PAYMENT_PAYFAST_TEST_MODE == 'True') {
            $this->_payfastUrl = self::TEST_MODE_URL;
        } else {
            $this->_payfastUrl = self::TRANSACT_MODE_URL;
        }
    }

    function before_process()
    {
        // Proccess data before checkout
    }

    function javascript_validation()
    {
        return false;
    }

    public function process_button()
    {
        $this->redirectForm();
        die();

        return false;
    }

    function after_process()
    {
        $this->manager->clearAfterProcess();
    }


    private function redirectForm()
    {
        $debugMode     = $this->isDebugMode();
        $payfastCommon = new PayfastCommon($debugMode);

        $payfastCommon->pflog(__METHOD__ . ': bof');

        global $languages_id;

        $order = $this->manager->getOrderInstance();

        $order->info['order_status'] = $this->processing_status;

        $order->save_order();

        $order->save_totals();

        $order->save_products(false);

        $orderId = $order->order_id;

        // Create Links
        $cancel_url = tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL');
        $notify_url = Yii::$app->urlManager->createAbsoluteUrl(
            ['callback/webhooks', 'set' => 'payment', 'module' => $this->code]
        );

        // Clean amount
        $amount = str_replace(',', '', $order->info['total']);
        $amount = number_format($amount, 2, '.', '');


        $data = array(
            'merchant_id'      => $this->_payfastMechentId,
            'merchant_key'     => $this->_payfastMechentKey,
            'return_url'       => tep_href_link(
                'callback/webhooks.payment.' . $this->code,
                'action=success&orders_id=' . $orderId,
                'SSL'
            ),
            'cancel_url'       => $cancel_url,
            'notify_url'       => $notify_url,
            'name_first'       => substr(trim($order->billing['firstname']), 0, 100),
            'name_last'        => substr(trim($order->billing['lastname']), 0, 100),
            'email_address'    => substr(trim($order->customer['email_address']), 0, 100),
            'm_payment_id'     => $orderId,
            'amount'           => $amount,
            'item_name'        => STORE_NAME . ' Purchase, Order #' . $orderId,
            'item_description' => substr(trim('$item_description'), 0, 255),
            'custom_int1'      => $order->customer['id']
        );

        $pfOutput = '';

        // Create output string
        foreach ($data as $key => $value) {
            $pfOutput .= $key . '=' . urlencode(trim($value)) . '&';
        }

        $passPhrase = trim(MODULE_PAYMENT_PAYFAST_PASSPHRASE);

        if (empty($passPhrase) || $this->isTestServer() != 0) {
            $pfOutput = substr($pfOutput, 0, -1);
        } else {
            $pfOutput = $pfOutput . "passphrase=" . urlencode($passPhrase);
        }

        $mode = $this->isTestServer();

        $this->manager->clearAfterProcess();
        $payfastCommon->createTransaction($data, $passPhrase, $mode);
    }

    function get_error()
    {
        return defined(
            'TEXT_GENERAL_PAYMENT_ERROR'
        ) ? TEXT_GENERAL_PAYMENT_ERROR : 'Please select different payment method';
    }

    public function describe_status_key()
    {
        return new ModuleStatus('MODULE_PAYMENT_PAYFAST_STATUS', 'True', 'False');
    }


    public function describe_sort_key()
    {
        return new ModuleSortOrder('MODULE_PAYMENT_PAYFAST_SORT_ORDER');
    }

    public function configure_keys()
    {
        $status_id      = defined(
            'MODULE_PAYMENT_PAYFAST_ORDER_PROCESS_STATUS_ID'
        ) ? MODULE_PAYMENT_PAYFAST_ORDER_PROCESS_STATUS_ID : $this->getDefaultOrderStatusId();
        $status_id_paid = defined(
            'MODULE_PAYMENT_PAYFAST_ORDER_PAID_STATUS_ID'
        ) ? MODULE_PAYMENT_PAYFAST_ORDER_PAID_STATUS_ID : $this->getDefaultOrderStatusId();
        $status_id_fail = defined(
            'MODULE_PAYMENT_PAYFAST_FAIL_PAID_STATUS_ID'
        ) ? MODULE_PAYMENT_PAYFAST_FAIL_PAID_STATUS_ID : $this->getDefaultOrderStatusId();

        return array(
            'MODULE_PAYMENT_PAYFAST_STATUS' => array(
                'title'        => 'Payfast Enable Module',
                'value'        => 'True',
                'description'  => 'Do you want to accept Payfast payments?',
                'sort_order'   => '1',
                'set_function' => 'tep_cfg_select_option(array(\'True\', \'False\'), ',
            ),

            'MODULE_PAYMENT_PAYFAST_ID'         => array(
                'title'       => 'Merchant ID',
                'value'       => '',
                'description' => '',
                'sort_order'  => '2',
                //'use_function' => '\\common\\helpers\\Zones::get_zone_class_title',
                //'set_function' => 'tep_cfg_pull_down_zone_classes(',
            ),
            'MODULE_PAYMENT_PAYFAST_KEY'        => array(
                'title'       => 'Merchant Key',
                'value'       => '',
                'description' => '',
                'sort_order'  => '3',
                //'use_function' => '\\common\\helpers\\Zones::get_zone_class_title',
                //'set_function' => 'tep_cfg_pull_down_zone_classes(',
            ),
            'MODULE_PAYMENT_PAYFAST_PASSPHRASE' => array(
                'title'       => 'Salt Passphrase',
                'value'       => '',
                'description' => '',
                'sort_order'  => '4',
                //'use_function' => '\\common\\helpers\\Zones::get_zone_class_title',
                //'set_function' => 'tep_cfg_pull_down_zone_classes(',
            ),

            'MODULE_PAYMENT_PAYFAST_SORT_ORDER'              => array(
                'title'       => 'Sort order of display.',
                'value'       => '0',
                'description' => 'Sort order of display. Lowest is displayed first.',
                'sort_order'  => '5',
            ),
            'MODULE_PAYMENT_PAYFAST_TEST_MODE'               => array(
                'title'        => 'Payfast Test mode',
                'value'        => 'True',
                'description'  => 'Sandbox mode',
                'sort_order'   => '6',
                'set_function' => 'tep_cfg_select_option(array(\'True\', \'False\'), ',
            ),
            'MODULE_PAYMENT_PAYFAST_ORDER_PROCESS_STATUS_ID' => array(
                'title'        => 'Payfast Set Order Processing Status',
                'value'        => $status_id,
                'description'  => 'Set the process status of orders made with this payment module to this value',
                'sort_order'   => '14',
                'set_function' => 'tep_cfg_pull_down_order_statuses(',
                'use_function' => '\\common\\helpers\\Order::get_order_status_name',
            ),
            'MODULE_PAYMENT_PAYFAST_ORDER_PAID_STATUS_ID'    => array(
                'title'        => 'Payfast Set Order Paid Status',
                'value'        => $status_id_paid,
                'description'  => 'Set the paid status of orders made with this payment module to this value',
                'sort_order'   => '15',
                'set_function' => 'tep_cfg_pull_down_order_statuses(',
                'use_function' => '\\common\\helpers\\Order::get_order_status_name',
            ),
            'MODULE_PAYMENT_PAYFAST_FAIL_PAID_STATUS_ID'     => array(
                'title'        => 'Payfast Set Order Fail Paid Status',
                'value'        => $status_id_fail,
                'description'  => 'Set the fail paid status of orders made with this payment module to this value',
                'sort_order'   => '15',
                'set_function' => 'tep_cfg_pull_down_order_statuses(',
                'use_function' => '\\common\\helpers\\Order::get_order_status_name',
            ),
            'MODULE_PAYMENT_PAYFAST_DEBUG_MODE'              => array(
                'title'        => 'Payfast Debug mode',
                'value'        => 'False',
                'description'  => 'Sandbox debug mode',
                'sort_order'   => '16',
                'set_function' => 'tep_cfg_select_option(array(\'True\', \'False\'), ',
            ),
        );
    }


    public function install($platform_id)
    {
        return parent::install($platform_id);
    }

    function isOnline(): bool
    {
        return true;
    }

    function selection(): array
    {
        $selection = array(
            'id'     => $this->code,
            'module' => $this->title
        );
        if (defined('MODULE_PAYMENT_PAYFAST_TEXT_NOTES') && !empty(MODULE_PAYMENT_PAYFAST_TEXT_NOTES)) {
            $selection['notes'][] = MODULE_PAYMENT_PAYFAST_TEXT_NOTES;
        }

        return $selection;
    }

    public function call_webhooks(): void
    {
        $orders_id = \Yii::$app->request->get('orders_id', 0);
        if (empty($orders_id)) {
            $this->verifyITN();
            exit();
        }
        tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, 'orders_id=' . $orders_id, 'SSL'));
    }

    public function verifyITN()
    {
        $debugMode     = $this->isDebugMode();
        $payfastCommon = new PayfastCommon($debugMode);

        $currencies = \Yii::$container->get('currencies');

        $pfError       = false;
        $pfErrMsg      = '';
        $pfDone        = false;
        $pfData        = array();
        $pfParamString = '';

        //// Notify Payfast that information has been received
        $this->notifyPF($pfError, $pfDone);

        $mode = $this->isTestServer();
        $payfastCommon->pflog("MODE: $mode");
        $pfHost = $this->getHost($mode);

        $payfastCommon->pflog('Payfast ITN call received');
        //// Get data sent by Payfast

        $payfastCommon->pflog('Get posted data');

        // Posted variables from ITN
        $pfData = $payfastCommon->pfGetData();

        $payfastCommon->pflog('Payfast Data: ' . print_r($pfData, true));

        if ($pfData === false) {
            $pfError  = true;
            $pfErrMsg = $payfastCommon::PF_ERR_BAD_ACCESS;
            $payfastCommon->pflog($pfErrMsg);
        }

        //// Verify security signature
        if (!$pfError && !$pfDone) {
            $payfastCommon->pflog('Verify security signature');

            $passPhrase   = trim(MODULE_PAYMENT_PAYFAST_PASSPHRASE);
            $pfPassPhrase = empty($passPhrase) ? null : $passPhrase;
            // If signature different, log for debugging
            if (!$payfastCommon->pfValidSignature($pfData, $pfParamString, $pfPassPhrase)) {
                $pfError  = true;
                $pfErrMsg = $payfastCommon::PF_ERR_INVALID_SIGNATURE;
                $payfastCommon->pflog($pfErrMsg);
            }
        }

        //// Verify data received
        if (!$pfError) {
            $moduleInfo = [
                "pfSoftwareName"       => 'osCommerce',
                "pfSoftwareVer"        => '4',
                "pfSoftwareModuleName" => 'Payfast-osCommerce',
                "pfModuleVer"          => '1.26.0',
            ];

            $payfastCommon->pflog('Verify data received');

            $pfValid = $payfastCommon->pfValidData($moduleInfo, $pfHost, $pfParamString);

            if (!$pfValid) {
                $pfError  = true;
                $pfErrMsg = $payfastCommon::PF_ERR_BAD_ACCESS;
                $payfastCommon->pflog($pfErrMsg);
            }
        }

        //// Check status and update order & transaction table
        if (!$pfError) {
            // Get order
            $orderID = (int)$pfData['m_payment_id'];
            $payfastCommon->pflog('order id' . $orderID);

            $order_query = tep_db_query(
                "SELECT `orders_status`, `currency`, `currency_value`
        FROM `" . TABLE_ORDERS . "`
        WHERE `orders_id` = '" . $orderID . "'
          AND `customers_id` = '" . $pfData['custom_int1'] . "'"
            );

            // If order found
            if (tep_db_num_rows($order_query) > 0) {
                // Get order details
                $order = tep_db_fetch_array($order_query);

                // If order in "Preparing" state, update to "Pending"
                $this->updateOrderState($order['orders_status'], $orderID);

                // Get order total
                $total_query = tep_db_query(
                    "SELECT `value`
            FROM `" . TABLE_ORDERS_TOTAL . "`
            WHERE `orders_id` = '" . $orderID . "'
              AND `class` = 'ot_total'
            LIMIT 1"
                );

                $total = tep_db_fetch_array($total_query);

                // Add comment to order history
                $comment_status = $pfData['payment_status'] . ' (' . $currencies->format(
                        $pfData['amount_gross'],
                        false,
                        'ZAR'
                    ) . ')';

                $orderValue = number_format(
                    $total['value'] * $order['currency_value'],
                    $currencies->get_decimal_places($order['currency']),
                    '.',
                    ''
                );
                if ($pfData['amount_gross'] != $orderValue) {
                    $comment_status .= '; Payfast transaction value (' . $pfData['amount_gross'] . ') does not match order value (' . $orderValue . ')';
                    $pfError        = true;
                    $pfErrMsg       = $payfastCommon::PF_ERR_AMOUNT_MISMATCH;
                }

                $sql_data_array = array(
                    'orders_id'         => $orderID,
                    'orders_status_id'  => (MODULE_PAYMENT_PAYFAST_ORDER_PROCESS_STATUS_ID > 0 ?
                        (int)MODULE_PAYMENT_PAYFAST_ORDER_PROCESS_STATUS_ID : (int)DEFAULT_ORDERS_STATUS_ID),
                    'date_added'        => 'now()',
                    'customer_notified' => '0',
                    'comments'          => 'Payfast ITN received [' . $comment_status . ']'
                );

                tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
            }
        }

        $order = $this->manager->getOrderInstanceWithId('\common\classes\Order', $pfData['m_payment_id']);
        $this->ifOrder($order, $comment_status, $orderID);

        // If an error occurred
        $this->ifAnErrorOccurred($pfError, $payfastCommon, $pfErrMsg, $pfData, $orderValue);

        // Close log
        $payfastCommon->pflog('true');

        return true;
    }

    public function isTestServer(): bool
    {
        return defined('MODULE_PAYMENT_PAYFAST_TEST_MODE') && MODULE_PAYMENT_PAYFAST_TEST_MODE == 'True';
    }

    public function isDebugMode(): bool
    {
        return defined('MODULE_PAYMENT_PAYFAST_DEBUG_MODE') && MODULE_PAYMENT_PAYFAST_DEBUG_MODE == 'True';
    }

}

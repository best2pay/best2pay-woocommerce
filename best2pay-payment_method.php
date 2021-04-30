<?php
/*  
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
/**
 * Plugin Name: Best2Pay payment method (Visa/MasterCard)
 * Plugin URI: http://best2pay.net/
 * Description: Receive payments via Visa/Mastercard easily with Best2Pay bank cards processing
 * Version: 1.0
 * Author: Best2Pay
 * Tested up to: 5.7.1
 * License: GPL3
 *
 * Text Domain: best2pay-payment_method
 * Domain Path: /languages
 *
 */

defined('ABSPATH') or die("No script kiddies please!");

if (false) {
    __('Best2Pay payment method (Visa/MasterCard)');
    __('Receive payments via Visa/Mastercard easily with Best2Pay bank cards processing');
}

add_action('plugins_loaded', 'init_woocommerce_best2pay', 0);

function init_woocommerce_best2pay()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    load_plugin_textdomain('best2pay-payment_method', false, dirname(plugin_basename(__FILE__)) . '/languages');

    class woocommerce_best2pay extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'best2pay';
            $this->method_title = __('Best2Pay', 'best2pay-payment_method');
            $this->title = __('Best2Pay', 'best2pay-payment_method');
            $this->description = __("Payments with bank cards via the <a href=\"http://www.best2pay.net\" target=\"_blank\">Best2Pay</a> payment system.", 'best2pay-payment_method');
            $this->icon = plugins_url('best2pay.png', __FILE__);
            $this->has_fields = true;
            $this->notify_url = add_query_arg('wc-api', 'best2pay_notify', home_url('/'));
            $this->callback_url = add_query_arg('wc-api', 'best2pay', home_url('/'));

            $this->init_form_fields();
            $this->init_settings();

            // variables
            $this->sector = $this->settings['sector'];
            $this->password = $this->settings['password'];
            $this->testmode = $this->settings['testmode'];
            $this->twostepsmode = $this->settings['twostepsmode'];

            // actions
            add_action('init', array($this, 'successful_request'));
            add_action('woocommerce_api_best2pay', array($this, 'callback_from_gateway'));
            add_action('woocommerce_api_best2pay_notify', array($this, 'notify_from_gateway'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        /**
         * Admin Panel Options
         **/
        public function admin_options()
        {
            ?>
            <h3><?php _e('Best2Pay', 'best2pay-payment_method'); ?></h3>
            <p><?php _e("Payments with bank cards via the <a href=\"http://www.best2pay.net\" target=\"_blank\">Best2Pay</a> payment system.", 'best2pay-payment_method'); ?></p>
            <table class="form-table">
                <?php
                // Generate the HTML For the settings form.
                $this->generate_settings_html();
                ?>
            </table><!--/.form-table-->
            <?php
        }

        /**
         * Initialise Gateway Settings Form Fields
         */
        public function init_form_fields()
        {

            //  array to generate admin form
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'best2pay-payment_method'),
                    'type' => 'checkbox',
                    'label' => __('Enable Best2Pay checkout method', 'best2pay-payment_method'),
                    'default' => 'yes'
                ),

                'sector' => array(
                    'title' => __('Sector ID', 'best2pay-payment_method'),
                    'type' => 'text',
                    'description' => __('Your shop identifier at Best2Pay', 'best2pay-payment_method'),
                    'default' => 'test'
                ),

                'password' => array(
                    'title' => __('Password', 'best2pay-payment_method'),
                    'type' => 'text',
                    'description' => __('Password to use for digital signature', 'best2pay-payment_method'),
                    'default' => 'test'
                ),

                'testmode' => array(
                    'title' => __('Test Mode', 'best2pay-payment_method'),
                    'type' => 'select',
                    'options' => array(
                        '1' => 'Test mode - real payments will not be processed',
                        '0' => 'Production mode - payments will be processed'
                    ),
                    'description' => __('Select test or live mode', 'best2pay-payment_method')
                ),
                'twostepsmode' => array(
                    'title' => __('2 steps payment mode', 'best2pay-payment_method'),
                    'type' => 'select',
                    'options' => array(
                        '1' => 'On',
                        '0' => 'Off'
                    ),
                    'description' => __('Turn on 2 steps mode', 'best2pay-payment_method')
                )
            );

        }

        /**
         * Register order @ Best2Pay and redirect user to payment form
         **/
        public function process_payment($order_id)
        {
            $order = new WC_Order($order_id);
            switch ($order->get_currency()) {
                case 'EUR':
                    $currency = '978';
                    break;
                case 'USD':
                    $currency = '840';
                    break;
                default:
                    $currency = '643';
                    break;
            }

            $best2pay_url = "https://test.best2pay.net";
            if ($this->testmode == "0")
                $best2pay_url = "https://pay.best2pay.net";
            $best2pay_operation = "Purchase";
            if ($this->twostepsmode == "1")
                $best2pay_operation = "Authorize";

            $signature = base64_encode(md5($this->sector . intval($order->get_total() * 100) . $currency . $this->password));

            $wc_order = wc_get_order($order_id);
            $items = $wc_order->get_items();
            $fiscalPositions = '';
            $fiscalAmount = 0;
            $KKT = true;

            if ($KKT) {
                foreach ($items as $item_id => $item) {
                    $item_data = $item->get_data();
                    $fiscalPositions .= $item_data['quantity'] . ';';
                    $elementPrice = $item_data['total'] / $item_data['quantity'];
                    $elementPrice = $elementPrice * 100;
                    $fiscalPositions .= $elementPrice . ';';
                    $fiscalPositions .= ($item_data['total_tax']) ?: 6 . ';';   // tax
                    $fiscalPositions .= str_ireplace([';', '|'], '', $item_data['name']) . '|';

                    $fiscalAmount += $item_data['quantity'] * $elementPrice;
                }
                if ($wc_order->shipping_total) {
                    $fiscalPositions .= '1;' . $wc_order->shipping_total * 100 . ';6;Доставка|';
                    $fiscalAmount += $wc_order->shipping_total * 100;
                }
                $fiscalDiff = abs($fiscalAmount - intval($order->get_total() * 100));
                if ($fiscalDiff) {
                    $fiscalPositions .= '1;' . $fiscalDiff . ';6;Скидка;14|';
                }
                $fiscalPositions = substr($fiscalPositions, 0, -1);
            }

            $args = array(
                'body' => array(
                    'sector' => $this->sector,
                    'reference' => $order->get_id(),
                    'amount' => intval($order->get_total() * 100),
                    'fiscal_positions' => $fiscalPositions,
                    'description' => sprintf(__('Order #%s', 'best2pay-payment_method'), ltrim($order->get_order_number(), '#')),
                    'email' => $order->get_billing_email(),
                    'currency' => $currency,
                    'mode' => 1,
                    'url' => $this->callback_url,
                    'signature' => $signature
                )
            );
            $remote_post = wp_remote_post($best2pay_url . '/webapi/Register', $args);
            $remote_post = (isset($remote_post['body'])) ? $remote_post['body'] : $remote_post;
            $b2p_order_id = ($remote_post) ? $remote_post : null;

            if (intval($b2p_order_id) == 0) {
                return false;
            }

            $signature = base64_encode(md5($this->sector . $b2p_order_id . $this->password));

            $order->update_status('on-hold');

            return array(
                'result' => 'success',
                'redirect' => "{$best2pay_url}/webapi/{$best2pay_operation}?sector={$this->sector}&id={$b2p_order_id}&signature={$signature}"
            );
        }

        /**
         * Callback from payment gateway was received
         **/
        public function callback_from_gateway()
        {
            // check payment status
            $b2p_order_id = intval($_REQUEST["id"]);
            if (!$b2p_order_id)
                return false;

            $b2p_operation_id = intval($_REQUEST["operation"]);
            if (!$b2p_operation_id) {
                $order_id = intval($_REQUEST["reference"]);
                $order = wc_get_order($order_id);
                if ($order)
                    $order->cancel_order(__("The order wasn't paid.", 'best2pay-payment_method'));

                wc_add_notice(__("The order wasn't paid.", 'best2pay-payment_method'), 'error');
                $get_checkout_url = apply_filters('woocommerce_get_checkout_url', WC()->cart->get_checkout_url());
                wp_redirect($get_checkout_url);
                exit();
            }

            // check payment operation state
            $signature = base64_encode(md5($this->sector . $b2p_order_id . $b2p_operation_id . $this->password));

            $best2pay_url = "https://test.best2pay.net";
            if ($this->testmode == "0")
                $best2pay_url = "https://pay.best2pay.net";

            $context = stream_context_create(array(
                'http' => array(
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query(array(
                        'sector' => $this->sector,
                        'id' => $b2p_order_id,
                        'operation' => $b2p_operation_id,
                        'signature' => $signature
                    )),
                )
            ));

            $repeat = 3;

            while ($repeat) {

                $repeat--;

                // pause because of possible background processing in the Best2Pay
                sleep(2);
                $args = array(
                    'body' => array(
                        'sector' => $this->sector,
                        'id' => $b2p_order_id,
                        'operation' => $b2p_operation_id,
                        'signature' => $signature
                    )
                );
                $xml = wp_remote_post($best2pay_url . '/webapi/Operation', $args)['body'];

                if (!$xml)
                    break;
                $xml = simplexml_load_string($xml);
                if (!$xml)
                    break;
                $response = json_decode(json_encode($xml));
                if (!$response)
                    break;
                if (!$this->orderAsPayed($response))
                    continue;

                wp_redirect($this->get_return_url(wc_get_order($response->reference)));
                exit();

            }

            $order_id = intval($response->reference);
            $order = wc_get_order($order_id);
            if ($order)
                $order->cancel_order(__("The order wasn't paid [1]: " . $response->message . '.', 'best2pay-payment_method'));

            wc_add_notice(__("The order wasn't paid [1]: ", 'best2pay-payment_method') . $response->message . '.', 'error');
            $get_checkout_url = apply_filters('woocommerce_get_checkout_url', WC()->cart->get_checkout_url());
            wp_redirect($get_checkout_url);
            exit();

        }

        /**
         * Payment notify from gateway was received
         **/
        public function notify_from_gateway()
        {
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once(ABSPATH . '/wp-admin/includes/file.php');
                WP_Filesystem();
            }

            // $xml = file_get_contents("php://input");
            $xml = $wp_filesystem->get_contents('php://input');
            if (!$xml)
                return false;
            $xml = simplexml_load_string($xml);
            if (!$xml)
                return false;
            $response = json_decode(json_encode($xml));
            if (!$response)
                return false;

            if (!$this->orderAsPayed($response)) {
                $order_id = intval($response->reference);
                $order = wc_get_order($order_id);
                if ($order)
                    $order->cancel_order(__("The order wasn't paid [2]: ", 'best2pay-payment_method') . $response->message . '.');
                exit();
            }

            die("ok");

        }

        private function orderAsPayed($response)
        {
            // looking for an order
            $order_id = intval($response->reference);
            if ($order_id == 0)
                return false;

            $order = wc_get_order($order_id);
            if (!$order)
                return false;

            // check payment state
            if (($response->type != 'PURCHASE' && $response->type != 'EPAYMENT') || $response->state != 'APPROVED')
                return false;

            // check server signature
            $tmp_response = json_decode(json_encode($response), true);
            unset($tmp_response["signature"]);
            unset($tmp_response["ofd_state"]);

            $signature = base64_encode(md5(implode('', $tmp_response) . $this->password));

            if ($signature !== $response->signature) {
                $order->update_status('fail', $response->message);
                return false;
            }

            $order->add_order_note(__('Payment completed.', 'best2pay-payment_method'));
            $order->payment_complete();

            // echo '<pre>' . print_r($tmp_response, true) . '<br>' . $signature . '<br>' . print_r($response, true); die();

            /*
             * сохраним в мета заказа выбранный на момент оплаты режим (1 или 2 стадии)
             */
            $b2p_mode = ($this->settings['twostepsmode']) ? 2 : 1;
            update_post_meta($order_id, 'b2p_payment_mode', $b2p_mode);

            return true;

        }

    } // class

    function add_best2pay_gateway($methods)
    {
        $methods[] = 'woocommerce_best2pay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_best2pay_gateway');
}
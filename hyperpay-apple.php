<?php
/*
  Plugin Name: ApplePay for Hyperpay Payment Gateway for WooCommerce
  Plugin URI:
  Description: Adds ApplePay payment option into WooCommerce. Hyperpay is the first one stop-shop service company for online merchants in MENA Region.<strong>If you have any question, please <a href="http://www.hyperpay.com/" target="_new">contact Hyperpay</a>.</strong>
  Version: 1.5.9
  Author: Hyperpay Team
  Ported to Oppwa By : Hyperpay Team

 */

add_filter('woocommerce_payment_gateways', 'hyperpayapplepay_add_gateway_class');
function hyperpayapplepay_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Hyperpay_ApplePay_Gateway'; // your class name is here
    return $gateways;
}

add_action('plugins_loaded', 'hyperpayapplepay_init_gateway_class');

function hyperpayapplepay_init_gateway_class()
{
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    class WC_Hyperpay_ApplePay_Gateway extends WC_Payment_Gateway
    {
        protected $msg = array();
        public function __construct()
        {
            $this->id = 'hyperpay_applepay';
            $this->has_fields = false;
            $this->method_title = 'Hyperpay ApplePay Gateway';
            $this->method_description = 'Hyperpay Woocommerce plugin for ApplePay';

            $this->init_form_fields();
            $this->init_settings();

            $this->script_url = "https://oppwa.com/v1/paymentWidgets.js?checkoutId=";
            $this->token_url = "https://oppwa.com/v1/checkouts";
            $this->transaction_status_url = "https://oppwa.com/v1/checkouts/##TOKEN##/payment";
            $this->script_url_test = "https://test.oppwa.com/v1/paymentWidgets.js?checkoutId=";
            $this->token_url_test = "https://test.oppwa.com/v1/checkouts";
            $this->transaction_status_url_test = "https://test.oppwa.com/v1/checkouts/##TOKEN##/payment";

            $this->testmode = $this->settings['testmode'];
            $this->title = $this->settings['title'];
            $this->trans_type = $this->settings['trans_type'];
            $this->trans_mode = $this->settings['trans_mode'];
            $this->accesstoken = $this->settings['accesstoken'];
            $this->entityid = $this->settings['entityId'];
            $this->brands = $this->settings['brands'];
            $this->connector_type = $this->settings['connector_type'];
            $this->payment_style = $this->settings['payment_style'];
            $this->country_code = $this->settings['country_code'];
            $this->mailerrors = $this->settings['mailerrors'];
            $this->lang = $this->settings['lang'];
            $this->supportedNetworks = $this->settings['supportedNetworks'];

            $lang = explode('-', get_bloginfo('language'));
            $lang = $lang[0];
            $this->lang  = $lang;

            $this->redirect_page_id = $this->settings['redirect_page_id'];
            //$this->description = ' ';

            if ($lang == 'ar') {
                $this->failed_message = 'تم رفض العملية ';

                $this->success_message = 'تم إجراء عملية الدفع بنجاح.';
            } else {
                $this->failed_message = 'Your transaction has been declined.';

                $this->success_message = 'Your payment has been processed successfully.';
            }

            $this->msg['message'] = "";
            $this->msg['class'] = "";

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_hyperpay_applepay', array(&$this, 'receipt_page'));

            // adding icon next gateway name in the checkout and change name based on lang using jQuery
            add_filter( 'woocommerce_gateway_icon',  function ( $icon, $id ){
                $icon_path = plugins_url('images/applepay-logo.png', __FILE__);

                $margin_style = ''; // css style

                if($id === 'hyperpay_applepay') { // applepay etc ....
                    $icon = '<img id="woocommerce_gateway_icon_applepay_hp" src="' . $icon_path . '" alt="applepay" style="'. $margin_style .'width: 100px;height: 30px" width="100" height="30">';
                }
            
                $icon .= '<script> 
                    if(jQuery("#woocommerce_gateway_icon_applepay_hp").length == 0) {
                        var $img = jQuery("#woocommerce_gateway_icon_applepay_hp").clone();
                        jQuery(".payment_method_hyperpay").find("label").append($img);
                    }
            
                </script>';
                return $icon;
            }, 10, 2 );
        }

        public function init_form_fields()
        {

            $postbackURL = get_option('siteurl');
            $successURL = $postbackURL . '?hyperpayapple_callback=1&success=1';
            $failURL = $postbackURL . '?hyperpayapple_callback=1&fail=1';
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable'),
                    'type' => 'checkbox',
                    'label' => __('Enable Hyperpay Payment Module.'),
                    'default' => 'no'
                ),
                'lang' => array(
                    'title' => __('Language'),
                    'type' => 'select',
                    'options' => array('en' => __('English'), 'ar' => __('Arabic')),
                    'description' => 'Form Language',
                ),
                'testmode' => array(
                    'title' => __('Test mode'),
                    'type' => 'select',
                    'options' => array('0' => __('Off'), '1' => __('On')),
                    'description' => '',
                ),
                'title' => array(
                    'title' => __('Title:'),
                    'type' => 'text',
                    'description' => ' ' . __('This controls the title which the user sees during checkout.'),
                    'default' => __('ApplePay')
                ),
                'trans_type' => array(
                    'title' => __('Transaction type'),
                    'type' => 'select',
                    'options' => $this->get_hyperpayApplePay_trans_type(),
                    'description' => ''
                ),
                'trans_mode' => array(
                    'title' => __('Transaction mode'),
                    'type' => 'select',
                    'options' => $this->get_hyperpayApplePay_trans_mode(),
                    'description' => ''
                ),
                'connector_type' => array(
                    'title' => __('Connector Type'),
                    'type' => 'select',
                    'options' => $this->get_hyperpayApplePay_connector_type(),
                    'description' => ''
                ),
                'accesstoken' => array(
                    'title' => __('Access Token'),
                    'type' => 'text',
                    'description' => ''
                ),
                'entityId' => array(
                    'title' => __('Entity ID'),
                    'type' => 'text',
                    'description' => ''
                ),
                'brands' => array(
                    'title' => __('Brands'),
                    'type' => 'multiselect',
                    'options' => $this->get_hyperpayApplePay_payment_methods(),
                    'description' => ''
                ),
                'supportedNetworks' => array(
                    'title' => __('Supported Networks'),
                    'type' => 'multiselect',
                    'options' => $this->get_hyperpayApplePay_supported_networks(),
                    'description' => ''
                ),
                'payment_style' => array(
                    'title' => __('Payment Style'),
                    'type' => 'select',
                    'options' => $this->get_hyperpayApplePay_payment_style(),
                    'description' => ''
                ),
                'country_code' => array(
                    'title' => __('Country Code'),
                    'type' => 'select',
                    'options' => $this->get_hyperpayApplePay_country_code(),
                    'description' => 'Select your country'
                ),
                'mailerrors' => array(
                    'title' => __('Enable error logging by email?'),
                    'type' => 'checkbox',
                    'label' => __('Yes'),
                    'default' => 'no',
                    'description' => __('If checked, an email will be sent to ' . get_bloginfo('admin_email') . ' whenever a callback fails.'),
                ),
                'redirect_page_id' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this->get_pages('Select Page'),
                    'description' => "URL of success page"
                )
            );
        }

        function get_hyperpayApplePay_trans_type()
        {
            $hyperpayApplePay_trans_type = array(
                'DB' => 'Debit',
                'PA' => 'Pre-Authorization'
            );

            return $hyperpayApplePay_trans_type;
        }

        function get_hyperpayApplePay_trans_mode()
        {
            $hyperpayApplePay_trans_mode = array(
                'CONNECTOR_TEST' => 'CONNECTOR_TEST',
                'INTEGRATOR_TEST' => 'INTEGRATOR_TEST',
                'LIVE' => 'LIVE'
            );

            return $hyperpayApplePay_trans_mode;
        }

        function get_hyperpayApplePay_connector_type()
        {
            $hyperpayApplePay_connector_type = array(
                'MPGS' => 'MPGS',
                'VISA_ACP' => 'VISA_ACP'
            );

            return $hyperpayApplePay_connector_type;
        }

        function get_hyperpayApplePay_payment_methods()
        {
            $hyperpayApplePay_payments = array(
                'APPLEPAY' => 'Apple Pay',
                'VISA' => 'Visa',
                'MASTER' => 'MasterCard',
                'MADA' => 'Mada',
                'AMEX' => 'American Express'
            );

            return $hyperpayApplePay_payments;
        }

        function get_hyperpayApplePay_supported_networks()
        {
            $hyperpayApplePay_supported_networks = array(
                "amex" => "Amex",
                "masterCard" => "MasterCard",
                "visa" => "Visa",
                "mada" => "Mada"
            );

            return $hyperpayApplePay_supported_networks;
        }

        function get_hyperpayApplePay_payment_style()
        {
            $hyperpayApplePay_payment_style = array(
                'card' => 'Card',
                'plain' => 'Plain'
            );

            return $hyperpayApplePay_payment_style;
        }

        function get_hyperpayApplePay_country_code()
        {
            $hyperpayApplePay_country_code = array
            (
                'SA' => 'Saudi Arabia',
                'AE' => 'United Arab Emirates',
                'JO' => 'Jordan',
                'BH' => 'Bahrain',
                'US' => 'United States',
                'EG' => 'Egypt',
                '' => 'Others',
            ); 

            return $hyperpayApplePay_country_code;
        }

        function receipt_page($order)
        {
            global $woocommerce;
            $order = new WC_Order($order);
            $error = false; // used to rerender the form in case of an error

            if (isset($_GET['g2p_token'])) {
                $token = $_GET['g2p_token'];
                $this->renderPaymentForm($order, $token);
            }

            if (isset($_GET['id'])) {
                $token = $_GET['id'];

                if ($this->testmode == 0) {
                    $url = $this->transaction_status_url;
                } else {
                    $url = $this->transaction_status_url_test;
                }

                $url = str_replace('##TOKEN##', $token, $url);
                $url .= "?entityId=" . $this->entityid;
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Authorization:Bearer ' . $this->accesstoken
                ));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                $resultPayment = curl_exec($ch);
                curl_close($ch);
                $resultJson = json_decode($resultPayment, true);

                $sccuess = 0;
                $failed_msg = '';
                $orderid = '';
                
                if (isset($resultJson['result']['code'])) {
                    $successCodePattern = '/^(000\.000\.|000\.100\.1|000\.[36])/';
                    $successManualReviewCodePattern = '/^(000\.400\.0|000\.400\.100)/';
                    //success status
                    if (preg_match($successCodePattern, $resultJson['result']['code']) || preg_match($successManualReviewCodePattern, $resultJson['result']['code'])) {
                        $sccuess = 1;
                    } else {
                        //fail case
                        $failed_msg = $resultJson['result']['description'];
                    }
                    $orderid = '';

                    if (isset($resultJson['merchantTransactionId'])) {
                        $orderid = $resultJson['merchantTransactionId'];
                    }

                    $order_response = new WC_Order($orderid);
                    if ($order_response) {
                        if ($sccuess == 1) {
                            WC()->session->set('hp_payment_retry', 0);
                            if ($order->status != 'completed') {
                                $order->payment_complete();
                                $woocommerce->cart->empty_cart();


                                $uniqueId = $resultJson['id'];
                                $order->add_order_note($this->success_message . 'Transaction ID: ' . $uniqueId);
                            }

                            wp_redirect($this->get_return_url($order));

                            /* return array('result'   => 'success',
                              'redirect'  => get_site_url().'/checkout/order-received/'.$order->id.'/?key='.$order->order_key );
                             */
                        } else {
                            $order->add_order_note($this->failed_message . $failed_msg);
                            $order->update_status('cancelled');

                            if ($this->lang == 'ar') {

                                wc_add_notice(__('حدث خطأ في عملية الدفع والسبب <br/>' . $failed_msg . '<br/>' . 'يرجى المحاولة مرة أخرى'), 'error');
                            } else {
                                wc_add_notice(__('(Transaction Error) ' . $failed_msg), 'error');
                            }
                            wc_print_notices();
                            $error = true;
                        }
                    } else {
                        $order->add_order_note($this->failed_message);
                        $order->update_status('cancelled');
                        if ($this->lang == 'ar') {
                            wc_add_notice(__('(حدث خطأ في عملية الدفع يرجى المحاولة مرة أخرى) '), 'error');
                        } else {
                            wc_add_notice(__('(Transaction Error) Error processing payment.'), 'error');
                        }
                        wc_print_notices();
                        $error = true;
                    }
                } else {
                    $order->add_order_note($this->failed_message);
                    $order->update_status('cancelled');

                    if ($this->lang == 'ar') {
                        wc_add_notice(__('(حدث خطأ في عملية الدفع يرجى المحاولة مرة أخرى) '), 'error');
                    } else {
                        wc_add_notice(__('(Transaction Error) Error processing payment.'), 'error');
                    }
                    wc_print_notices();
                    $error = true;
                }
            }
        }

        private function renderPaymentForm($order, $token)
        {
            if ($token) {

                $order_id = $order->get_id();

                if ($this->testmode == 0) {
                    $scriptURL = $this->script_url;
                } else {
                    $scriptURL = $this->script_url_test;
                }

                $scriptURL .= $token;

                $payment_brands = implode(' ', $this->brands);
                $supportedNetworks = json_encode($this->supportedNetworks);

                $postbackURL = $order->get_checkout_payment_url(true);

                $country_code = '';
                if(isset($this->country_code) && !empty($this->country_code != '')){
                    $country_code = ' countryCode: "'.$this->country_code . '",';
                }

                echo '<script>
                            var wpwlOptions = {
                                style:"' . $this->payment_style . '",
                                locale:"' . $this->lang . '",
                                paymentTarget: "_top",
                            }
                            wpwlOptions.applePay = {
                                merchantCapabilities:["supports3DS"],
                                supportedNetworks: ' . $supportedNetworks . ',
                                displayName: "' . get_bloginfo() . '",
                                total: { label: "' . get_bloginfo() . ', INC." },
                                '.$country_code.'
                            }
                    </script>';
                //if the lang is Arabic change the direction to ltr
                if ($this->lang == 'ar') {
                    echo '<style>
                            .wpwl-group{
                            local: "ar",
                            direction:ltr !important;
                            }
                          </style>';
                };
                // payment form
                echo '<script  src="' . $scriptURL . '"></script>';
                echo '<form action="' . $postbackURL . '" class="paymentWidgets" data-brands="'. $payment_brands .'">
                        </form>';
            }
        }

        public function process_payment($order_id)
        {
            global $woocommerce;


            $order = new WC_Order($order_id);
            $this->console_log($this->get_return_url($order));
            $user = $order->get_user();



            if ($this->testmode == 0) {
                $url = $this->token_url;
            } else {
                $url = $this->token_url_test;
            }

            $orderAmount = number_format($order->get_total(), 2, '.', '');

            $orderid = $order_id;

            $accesstoken = $this->accesstoken;
            $entityid = $this->entityid;
            $mode = $this->trans_mode;
            $type = $this->trans_type;
            $amount = number_format(round($orderAmount, 2), 2, '.', '');
            $currency = get_woocommerce_currency();
            $transactionID = $orderid;
            $firstName = $order->get_billing_first_name();
            $family = $order->get_billing_last_name();
            $street = $order->get_billing_address_1();
            $zip = $order->get_billing_postcode();
            $city = $order->get_billing_city();
            $state = $order->get_billing_state();
            $country = $order->get_billing_country();
            $email = $order->get_billing_email();

            if (empty($state)) {
                $state = $city;
            }

            $data = "entityId=$entityid" .
                "&amount=$amount" .
                "&currency=$currency" .
                "&paymentType=$type" .
                "&merchantTransactionId=$transactionID" .
                "&customer.email=$email";

            $data .= '&notificationUrl=' . $order->get_checkout_payment_url(true);

            if ($mode == 'CONNECTOR_TEST') {
                $data .= "&testMode=EXTERNAL";
            }

            if ($this->connector_type == 'VISA_ACP') {
                $data .= "&billing.street1=$street";
                $data .= "&billing.city=$city";
                $data .= "&billing.state=$state";
                $data .= "&billing.country=$country";
            }




            $customerID = $order->get_customer_id();

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization:Bearer ' . $accesstoken
            ));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                wc_add_notice(__('Hyperpay error:', 'woocommerce') . "Problem with $url, $php_errormsg", 'error');
            }
            curl_close($ch);
            if ($response === false) {
                wc_add_notice(__('Hyperpay error:', 'woocommerce') . "Problem reading data from $url, $php_errormsg", 'error');
            }

            $result = json_decode($response);


            $token = '';

            if (isset($result->id)) {
                $token = $result->id;
            }

            return array(
                'result' => 'success',
                'redirect' => add_query_arg('g2p_token', $token, $order->get_checkout_payment_url(true))
            );
        }

        function get_pages($title = false, $indent = true)
        {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();

            if ($title)
                $page_list[] = $title;

            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

        function console_log($output, $with_script_tags = true)
        {
            $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) .
                ');';
            if ($with_script_tags) {
                $js_code = '<script>' . $js_code . '</script>';
            }
            echo $js_code;
        }
    }
}

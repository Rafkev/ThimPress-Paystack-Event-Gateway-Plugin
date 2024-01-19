<?php
//security
if (!defined('ABSPATH')) {
    exit();
}

class Thimpress_Paystack_Payment_Gateway extends WPEMS_Abstract_Payment_Gateway
{

    /**
     * id of payment
     * @var null
     */
    public $id = 'paystack';
    // title
    public $title = null;
    // public_key
    protected $public_key = null;
    // secret_key
    protected $secret_key = null;
    // enable
    protected static $enable = false;

    public function __construct()
    {
        $this->title = __('PayStack', THIMPRESS_PAYSTACK_EVENT_TEXT_DOMAIN);
        $this->icon = THIMPRESS_PAYSTACK_EVENT_ASSETS_URL . '/img/logo.png';
        parent::__construct();

        // test environment
        $this->public_key    = wpems_get_option('paystack_public_test_key') ? wpems_get_option('paystack_public_test_key') : '';
        $this->secret_key    = wpems_get_option('paystack_secret_test_key') ? wpems_get_option('paystack_secret_test_key') : '';

        if (wpems_get_option('paystack_mode') == 'live') {
            $this->public_key = wpems_get_option('paystack_public_live_key') ? wpems_get_option('paystack_public_live_key') : '';
            $this->secret_key = wpems_get_option('paystack_secret_live_key') ? wpems_get_option('paystack_secret_live_key') : '';
        }
        // // init process
        $this->payment_validation();
    }

    /*
	 * Check gateway available
	 */
    public function is_available()
    {
        return true;
    }

    /*
	 * Check gateway enable
	 */
    public function is_enable()
    {
        self::$enable = !empty($this->secret_key) && wpems_get_option('paystack_enable') === 'yes';
        return apply_filters('tp_event_enable_paystack_payment', self::$enable);
    }


    // callback
    public function payment_validation()
    {
        if (isset($_GET['event-auth-paystack-payment']) && $_GET['event-auth-paystack-payment']) {
            if (!isset($_GET['tp-event-paystack-nonce']) || !wp_verify_nonce($_GET['tp-event-paystack-nonce'], 'tp-event-paystack-nonce')) {
                wpems_add_notice('error', sprintf(__('Security error. Please contact support.', 'wp-events-manager')));
                return;
            }

            if (sanitize_text_field($_GET['event-auth-paystack-payment']) === 'completed') {
                // Get the transaction reference from the URL
                $transaction_reference = $_GET['reference'];

                // Your Paystack Secret Key
                $secret_key = $this->secret_key;

                // Paystack API url
                $paystack_api_url = 'https://api.paystack.co/transaction/verify/' . rawurlencode($transaction_reference);

                // The arguments for the request
                $args = array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $secret_key,
                    ),
                );

                $response = wp_remote_get($paystack_api_url, $args);

                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body);

                    if ($data->status && $data->data->status === 'success') {
                        //get booking id from meta data
                        $metadata = $data->data->metadata;
                        //get booking
                        $booking_id = $metadata->booking_id;
                        //update booking
                        $book = WPEMS_Booking::instance($booking_id);
                        $status = 'ea-completed';
                        $book->update_status($status);
                        //add notice
                        wpems_add_notice('success', sprintf(__('Payment is completed. We will send you email when payment status is completed', 'wp-events-manager')));
                        //add paystack reference to booking
                        update_post_meta($booking_id, 'paystack_reference', $transaction_reference);
                        //to admin notice
                        wpems_add_notice('success', sprintf(__('Paystack reference is %s', 'wp-events-manager'), $transaction_reference));
                    } else {
                        wpems_add_notice('error', sprintf(__('Payment failed. Please try again.', 'wp-events-manager')));
                    }
                } else {
                    wpems_add_notice('error', sprintf(__('Payment verification failed. Please contact support.', 'wp-events-manager')));
                }
            } else if (sanitize_text_field($_GET['event-auth-paystack-payment']) === 'cancel') {
                wpems_add_notice('success', sprintf(__('Booking is cancel.', 'wp-events-manager')));
            }
            $url = add_query_arg(array('tp-event-paystack-nonce' => esc_url($_GET['tp-event-paystack-nonce'])), wpems_account_url());
            echo '<script>window.location.href="' . $url . '";</script>';
            exit();
        }
    }

    /**
     * fields settings
     * @return array
     */
    public function admin_fields()
    {
        $prefix        = 'thimpress_events_';
        $paypal_enable = wpems_get_option('paypal_enable');
        return apply_filters('tp_event_paypal_admin_fields', array(
            array(
                'type'  => 'section_start',
                'id'    => 'paystack_settings',
                'title' => __('Paystack Settings', 'wp-events-manager'),
                'desc'  => esc_html__('Make payment via Paystack', 'wp-events-manager')
            ),
            array(
                'type'    => 'yes_no',
                'title'   => __('Enable', 'wp-events-manager'),
                'id'      => $prefix . 'paystack_enable',
                'default' => 'no',
                'desc'    => __('Enable Paystack payment gateway', 'wp-events-manager')
            ),
            //select live or test
            array(
                'type'    => 'select',
                'title'   => __('Mode', 'wp-events-manager'),
                'id'      => $prefix . 'paystack_mode',
                'default' => 'test',
                'options' => array(
                    'test' => __('Test', 'wp-events-manager'),
                    'live' => __('Live', 'wp-events-manager')
                ),
                'desc'    => __('Select mode for Paystack', 'wp-events-manager')
            ),
            // public test key
            array(
                'type'    => 'text',
                'title'   => __('Public Test Key', 'wp-events-manager'),
                'id'      => $prefix . 'paystack_public_test_key',
                'default' => '',
                'desc'    => __('Enter your public test key', 'wp-events-manager')
            ),
            // secret test key
            array(
                'type'    => 'text',
                'title'   => __('Secret Test Key', 'wp-events-manager'),
                'id'      => $prefix . 'paystack_secret_test_key',
                'default' => '',
                'desc'    => __('Enter your secret test key', 'wp-events-manager')
            ),
            //live public key
            array(
                'type'    => 'text',
                'title'   => __('Public Live Key', 'wp-events-manager'),
                'id'      => $prefix . 'paystack_public_live_key',
                'default' => '',
                'desc'    => __('Enter your public live key', 'wp-events-manager')
            ),
            //live secret key
            array(
                'type'    => 'text',
                'title'   => __('Secret Live Key', 'wp-events-manager'),
                'id'      => $prefix . 'paystack_secret_live_key',
                'default' => '',
                'desc'    => __('Enter your secret live key', 'wp-events-manager')
            ),
            array(
                'type' => 'section_end',
                'id'   => 'paystack_settings'
            )
        ));
    }

    /**
     * get_item_name
     * @return string
     */
    public function get_item_name($booking_id = null)
    {
        if (!$booking_id)
            return;

        // book
        $book        = WPEMS_Booking::instance($booking_id);
        $description = sprintf('%s(%s)', $book->post->post_title, wpems_format_price($book->price, $book->currency));

        return $description;
    }

    /**
     * checkout url
     * @return url string
     */
    public function checkout_url($booking_id = false)
    {
        if (!$booking_id) {
            wp_send_json(array(
                'status'  => false,
                'message' => __('Booking ID is not exists!', 'wp-events-manager')
            ));
            die();
        }
        // book
        $book = wpems_get_booking($booking_id);

        // create nonce
        $nonce = wp_create_nonce('tp-event-paystack-nonce');

        $user  = get_userdata($book->user_id);
        $email = $user->user_email;

        //add filter to price
        $price = apply_filters('tp_event_paystack_price', $book->price, $booking_id);

        //add filter to currency
        $currency = apply_filters('tp_event_paystack_currency', wpems_get_currency(), $booking_id);

        // query post
        $query = array(
            'email'         => $email,
            'amount'        => (float) $price * 100, // Paystack's API expects the amount in kobo
            'reference'     => "ADE_TP_" . $book->booking_id . '_' . time(),
            'currency'      => $currency,
            'metadata'      => json_encode(array('booking_id' => $booking_id, 'user_id' => $book->user_id)),
            'callback_url'  => add_query_arg(array('event-auth-paystack-payment' => 'completed', 'tp-event-paystack-nonce' => $nonce), wpems_account_url()),
        );

        // allow hook paystack param
        $query = apply_filters('tp_event_paystack_payment_params', $query);

        // The arguments for the request
        $args = array(
            'body'        => json_encode($query),
            'headers'     => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->secret_key
            ),
            'method'      => 'POST',
            'data_format' => 'body',
        );

        //send post to paystack 
        $response = wp_remote_post('https://api.paystack.co/transaction/initialize', $args);

        // Check for errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            wp_send_json(array(
                'status'  => false,
                'message' => "Something went wrong: $error_message"
            ));
            die();
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);
            if ($data->status) {
                $authorization_url = $data->data->authorization_url;
                return $authorization_url;
            } else {
                wp_send_json(array(
                    'status'  => false,
                    'message' => $data->message
                ));
                die();
            }
        }
    }

    public function process($amount = false)
    {
        if (!$this->is_available()) {
            return array(
                'status'  => false,
                'message' => __('Paystack is not available', 'wp-events-manager')
            );
        }
        return array(
            'status' => true,
            'url'    => $this->checkout_url($amount)
        );
    }
}

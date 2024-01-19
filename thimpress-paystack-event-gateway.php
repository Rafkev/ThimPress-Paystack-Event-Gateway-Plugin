<?php

/**
 * Plugin Name: Thimpress Paystack Event Gateway
 * Plugin URI:  https://adeleyeayodeji.com
 * Author:      Adeleye Ayodeji
 * Author URI:  https://adeleyeayodeji.com
 * Description: Paystack payment gateway for WP Events Manager
 * Version:     0.1.0
 * License:     GPL-2.0+
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: thimpress-paystack-event-gateway
 */

//security
defined('ABSPATH') || exit;

//text domain
define("THIMPRESS_PAYSTACK_EVENT_TEXT_DOMAIN", "thimpress-paystack-event-gateway");
//assets url
define("THIMPRESS_PAYSTACK_EVENT_ASSETS_URL", plugin_dir_url(__FILE__) . 'assets');
//path
define("THIMPRESS_PAYSTACK_EVENT_PATH", plugin_dir_path(__FILE__));


//thimpress_paystack_event_gateway
function thimpress_paystack_event_gateway()
{
    //check class exist WPEMS_Abstract_Payment_Gateway
    if (class_exists('WPEMS_Abstract_Payment_Gateway')) {
        //include class
        require_once THIMPRESS_PAYSTACK_EVENT_PATH . 'includes/core.php';
        //add gateway to list
        add_filter('wpems_payment_gateways', function ($gateways) {
            $paystack = new Thimpress_Paystack_Payment_Gateway();
            $gateways[$paystack->id] = $paystack;
            return $gateways;
        });
    }
}
//add action
add_action('plugins_loaded', 'thimpress_paystack_event_gateway');

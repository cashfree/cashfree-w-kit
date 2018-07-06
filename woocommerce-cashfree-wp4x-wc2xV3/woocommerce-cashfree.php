<?php
/*
Plugin Name: Cashfree
Plugin URI: https://www.gocashfree.com
Description: Payment gateway plugin by Cashfree for Woocommerce sites
Version: 1.1
Author: Cashfree Dev
Author URI: techsupport@gocashfree.com
*/
 
// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'woocommerce_cashfree_init', 0 );
define('IMGDIR', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'cashfree_action_links' );

function cashfree_action_links( $links ) {
   $links[] = '<a href="'. esc_url( get_admin_url(null, 'admin.php?page=wc-settings&tab=checkout') ) .'">Setup</a>';
   $links[] = '<a href="https://github.com/cashfree/cashfree_woocommerce_kit/tree/master/woocommerce-cashfree-wp4x-wc2xV3" target="_blank">Github</a>';
   return $links;
}


function woocommerce_cashfree_init() {
  // If the parent WC_Payment_Gateway class doesn't exist
  // it means WooCommerce is not installed on the site
  // so do nothing
  if ( ! class_exists( 'WC_Payment_Gateway' ) ) return; 
   
  // If we made it this far, then include our Gateway Class
  class WC_Gateway_cashfree extends WC_Payment_Gateway {
		
    // Setup our Gateway's id, description and other values
    function __construct() {
  		global $woocommerce;
  		global $wpdb;
  		$this->id = "cashfree";
  		$this->icon = IMGDIR . 'logo.png';
      $this->method_title = __( "Cashfree", 'wc_gateway_cashfree' );
      $this->method_description = "Cashfree settings page";
      $this->title = __( "Cashfree", 'wc_gateway_cashfree' ); 
  		$this->has_fields = false;
  		$this->init_form_fields();
  		$this->init_settings();
  		$this->api_url 				= $this->settings['api_url'];
  		$this->app_id 		= $this->settings['app_id'];
  		$this->secret_key 		= $this->settings['secret_key'];
  		$this->description 		= $this->settings['description'];

      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      if ( isset( $_GET[ 'cashfree_callback'])) {
            $this->check_cashfree_response();
      }
    }
	
	
  // Build the administration fields for this specific Gateway
    public function init_form_fields() {
      $this->form_fields = array(
                'enabled' => array(
                    'title'         => __('Enable/Disable', 'wc_gateway_cashfree'),
                    'type'             => 'checkbox',
                    'label'         => __('Enable Cashfree payment gateway.', 'wc_gateway_cashfree'),
                    'default'         => 'no',
                    'description'     => 'Show in the Payment List as a payment option'
                ),
                  'title' => array(
                    'title'         => __('Title:', 'wc_gateway_cashfree'),
                    'type'            => 'text',
                    'default'         => __('Cashfree', 'wc_gateway_cashfree'),
                    'description'     => __('This controls the title which the user sees during checkout.', 'wc_gateway_cashfree'),
                    'desc_tip'         => true
                ),
                'description' => array(
                    'title'         => __('Description:', 'wc_gateway_cashfree'),
                    'type'             => 'textarea',
                    'default'         => __("Pay securely via Card/Net Banking/Wallet via Cashfree."),
                    'description'     => __('This controls the description which the user sees during checkout.', 'wc_gateway_cashfree'),
                    'desc_tip'         => true
                ),
                'api_url' => array(
                    'title'         => __('API Endpoint', 'wc_gateway_cashfree'),
                    'type'             => 'text',
                    'description'     => __('Refer API documentation or contact Cashfree Team ', 'wc_gateway_cashfree'),
                    'desc_tip'         => true
                ),
                'app_id' => array(
                    'title'         => __('App Id', 'wc_gateway_cashfree'),
                    'type'             => 'text',
                    'description'     => __('Copy from your dashboard or contact Cashfree Team', 'wc_gateway_cashfree'),
                    'desc_tip'         => true
                ),
                'secret_key' => array(
                    'title'         => __('Secret Key', 'wc_gateway_cashfree'),
                    'type'             => 'password',
                    'description'     => __('Copy from your dashboard or contact Cashfree Team', 'wc_gateway_cashfree'),
                    'desc_tip'         => true
                ),
            );
	  }

    function check_cashfree_response(){
      global $woocommerce;
      global $wpdb;
      if(isset($_POST['orderId'])){
         if (isset($_GET["ipn"])) {
            $showContent = false;
          } else {
            $showContent = true;
          }

    	  $order = new WC_Order($_POST['orderId']);
	      if ($order && $order->get_status() == "pending") {
          $cashfree_response = array();
          $cashfree_response["orderId"] = $_POST["orderId"];
          $cashfree_response["orderAmount"] = $_POST["orderAmount"];
          $cashfree_response["txStatus"] = $_POST["txStatus"];
          $cashfree_response["referenceId"] = $_POST["referenceId"];
          $cashfree_response["txTime"] = $_POST["txTime"];
          $cashfree_response["txMsg"] = $_POST["txMsg"];
          $cashfree_response["paymentMode"] = $_POST["paymentMode"];
          $cashfree_response["signature"] = $_POST["signature"];
	
          $secret_key = $this->secret_key;
          $data = "{$cashfree_response['orderId']}{$cashfree_response['orderAmount']}{$cashfree_response['referenceId']}{$cashfree_response['txStatus']}{$cashfree_response['paymentMode']}{$cashfree_response['txMsg']}{$cashfree_response['txTime']}";
          $hash_hmac = hash_hmac('sha256', $data, $secret_key, true) ;
          $computedSignature = base64_encode($hash_hmac);
          if ($cashfree_response["signature"] != $computedSignature) {
                 //error
              	 die();
          } 

          if ($cashfree_response["txStatus"] == 'SUCCESS') {
            $order -> payment_complete();
            $order -> add_order_note('Cashfree payment successful');
            $order -> add_order_note($cashfree_response["txMsg"]);
            $woocommerce -> cart -> empty_cart();
            $this->msg['message'] = "Thank you for shopping with us. Your payment has been confirmed. Cashfree reference id is: <b>".$cashfree_response["referenceId"]."</b>.";
            $this->msg['class'] = 'woocommerce-message';			
          } else if ($cashfree_response["txStatus"] == "CANCELLED") {
            $order->update_status( 'failed', __( 'Payment has been cancelled.', 'woocommerce' ));
            $this->msg['class'] = 'woocommerce-error';
				    $this->msg['message'] = "Your transaction has been cancelled. Please try again.";
          } else if ($cashfree_response["txStatus"] == "PENDING") {
            $order->update_status( 'failed', __( 'Payment is under review.', 'woocommerce' ));
            $this->msg['class'] = 'woocommerce-error';
				    $this->msg['message'] = "Your transaction is under review. Please wait for an status update.";
          } else {
            $order->update_status( 'failed', __( 'Payment Failed', 'woocommerce' ));
            $this->msg['class'] = 'woocommerce-error';
				    $this->msg['message'] = "Your transaction has failed.";
          }
          if ($showContent) {
                add_action('the_content', array(&$this, 'showMessage'));
          } 
        }				
      }
    }

   
	
    function showMessage ($content) {
       return '<div class="woocommerce"><div class="'.$this->msg['class'].'">'.$this->msg['message'].'</div></div>'.$content;
    }
	
	  // Submit payment and handle response
    public function process_payment( $order_id ) {
        global $woocommerce;
        global $wpdb;
        global $current_user;
    		//get user details   
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        $first_name = $current_user->shipping_first_name;
        $last_name = $current_user->shipping_last_name;
        $phone_number = $current_user->billing_phone;
        $customerName = $first_name." ".$last_name;
        $customerEmail = $user_email;
        $customerPhone = $phone_number;

        if ($user_email == ''){
          $user_email = $_POST['billing_email'];
          $first_name = $_POST['billing_first_name'];
          $last_name  = $_POST['billing_last_name'];
          $phone_number = $_POST['billing_phone'];			
          $customerName	= $first_name." ".$last_name;
          $customerEmail = $user_email;
          $customerPhone = $phone_number;
        }

        $order  = new WC_Order( $order_id );
        $this->return_url = add_query_arg(array('cashfree_callback' => 1), $this->get_return_url( $order));
        $this->notify_url = add_query_arg(array('cashfree_callback' => 1, 'ipn' => '1'), $this->get_return_url( $order));
		
	      $cf_request = array();
        $cf_request["appId"] =  $this->app_id;
        $cf_request["secretKey"] = $this->secret_key;
        $cf_request["orderId"] = $order_id;  
        $cf_request["orderAmount"] = $order->get_total();
        $cf_request["orderCurrency"] = $order->get_currency();
        $cf_request["customerPhone"] = $customerPhone;
        $cf_request["customerName"] = $customerName;
        $cf_request["customerEmail"] =  $customerEmail;
        $cf_request["source"] =  "woocommerce";
        $cf_request["returnUrl"] = $this->return_url;
        $cf_request["notifyUrl"] = $this->notify_url;
        $timeout = 10;
 
        $apiEndpoint = $this->api_url;
        $apiEndpoint = rtrim($apiEndpoint, "/");
        $apiEndpoint = $apiEndpoint."/api/v1/order/create";

        $postBody = array("body" => $cf_request);
        $cf_result = wp_remote_retrieve_body(wp_remote_post($apiEndpoint,$postBody));
        
        $jsonResponse = json_decode($cf_result);
        if ($jsonResponse->{'status'} == "OK") {
          $paymentLink = $jsonResponse->{"paymentLink"};
          return array('result' => 'success', 'redirect' => $paymentLink);
        } else {
          return array('result' => 'failed', 'messages' => 'Gateway request failed. Please try again');
        }
        
        exit;
    }
  }
	
    // Now that we have successfully included our class,
    // Lets add it to WooCommerce
  add_filter( 'woocommerce_payment_gateways', 'add_cashfree_gateway' );
  
  function add_cashfree_gateway( $methods ) {
    $methods[] = 'WC_Gateway_cashfree';
		return $methods;
	}
}

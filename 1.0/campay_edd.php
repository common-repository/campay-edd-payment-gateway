<?php

/*
 * Plugin Name: CamPay EDD Payment Gateway
 * Plugin URI: https://campay.net/wordpress/campay-edd-payment-gateway/
 * Description: Accept Mobile Money Payment using CamPay API Services.
 * Author: Gabinho
 * Author URI: 
 * Version: 1.0
 */

define("CAMPAY_EDD_GATEWAY_PLUGIN_URL", plugin_dir_url(__FILE__));
define("CAMPAY_EDD_GATEWAY_PLUGIN_DIR", dirname(__FILE__));
define("CAMPAY_EDD_GATEWAY_PLUGIN_PATH", plugin_dir_path(__FILE__));

class CamPay_EDD_Gateway {
    
    private $app_username;
    private $app_password;
    private $testmode;
    
    public function __construct() {
			
		add_filter('edd_payment_gateways', array($this, 'campay_edd_register_gateway')); 
        add_action('edd_campay_payment_gateway_cc_form', array($this, 'campay_edd_payment_gateway_cc_form'));
        add_filter('edd_settings_gateways', array($this, 'campay_edd_add_settings'));
		add_action( 'wp_enqueue_scripts', array( $this, 'shortcode_payment_js' ) );
		add_action('edd_gateway_campay_payment_gateway', array($this, 'campay_edd_process_payment'));
    }

	// registers the gateway
	function campay_edd_register_gateway($gateways) {
		$gateways['campay_payment_gateway'] = array('admin_label' => 'Campay Payment Gateway', 'checkout_label' => __('CamPay Payment Gateway', 'campay_edd'));
		return $gateways;
	}

	function campay_edd_payment_gateway_cc_form() {
		
		
		?>
			<div class="form_holder">
				<div class="error_holder">
					<span id="campay-number-error"><?php echo _e("The number entered is not a valid MTN or ORANGE number", "campay_edd"); ?></span>
				</div>
				<div class="input_holder">
					<label>Enter a valide the Mobile Money number or Valid Orange Money number </label>
					<input type="text" name="campay_transaction_number" onchange="validate_number(this)" oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/(\.*?)\.*/g, '$1');" />
				</div>
			</div>
		<?php
	}
	
	
   
	// adds the settings to the Payment Gateways section
	function campay_edd_add_settings($settings) {
	 
		$sample_gateway_settings = array(
			array(
				'id' => 'campay_payment_gateway_settings',
				'name' => '<strong>' . __('Campay Payment Gateway Settings', 'campay_edd') . '</strong>',
				'desc' => __('Configure the gateway settings', 'campay_edd'),
				'type' => 'header'
			),
			array(
				'id' => 'campay_api_username',
				'name' => __('Campay App Username', 'campay_edd'),
				'desc' => __('Enter your CamPay App username, found in your Campay Account', 'campay_edd'),
				'type' => 'text',
				'size' => 'regular'
			),
			array(
				'id' => 'campay_api_password',
				'name' => __('CamPay App Password', 'campay_edd'),
				'desc' => __('Enter your CamPay App password, found in your Campay Account', 'campay_edd'),
				'type' => 'password',
				'size' => 'regular'
			)
		);
	 
		return array_merge($settings, $sample_gateway_settings);	
	}
   
    public function shortcode_payment_js() {
        wp_enqueue_script('shortcode_campay_js', plugins_url('assets/js/campay.js', __FILE__), array('jquery'), false, true);
        wp_enqueue_style('shortcode_campay_css', plugins_url('assets/css/campay.css', __FILE__), array(), '1.0.0', 'all');
    }   
   
   
	function campay_edd_process_payment($purchase_data) {
 
		global $edd_options;
		
		$user = $edd_options['campay_api_username'];
		$pass = $edd_options['campay_api_password'];
	 
		/**********************************
		* set transaction mode
		**********************************/
	 
		if(edd_is_test_mode()) {
			$server_uri = "https://demo.campay.net";
		} else {
			// set live credentials here
			$server_uri = "https://campay.net";
		}
	 
		/**********************************
		* check for errors here
		**********************************/
		
		// errors can be set like this
		if( empty( $_POST[ 'campay_transaction_number' ]) ) {
			edd_set_error('invalid_number', __('The mobile number entered for the transaction is not valid!', 'campay_edd'));
			return false;
		}
		else
		{
			if(!is_numeric($_POST['campay_transaction_number']) && strlen($_POST['campay_transaction_number'])!=9)
			{
				edd_set_error('invalid_number', __('The mobile number entered for the transaction is not valid!', 'campay_edd'));
				return false;
			}
		}	
		
		// check for any stored errors
		$errors = edd_get_errors();
		if(!$errors) {
	 
			$purchase_summary = edd_get_purchase_summary($purchase_data);
		
			/**********************************
			* setup the payment details
			**********************************/
	 
			$payment_obj = array( 
				'price' => $purchase_data['price'], 
				'date' => $purchase_data['date'], 
				'user_email' => $purchase_data['user_email'],
				'purchase_key' => $purchase_data['purchase_key'],
				'currency' => $edd_options['currency'],
				'downloads' => $purchase_data['downloads'],
				'cart_details' => $purchase_data['cart_details'],
				'user_info' => $purchase_data['user_info'],
				'status' => 'pending'
			);
	 
			// record the pending payment
			$payment_obj = edd_insert_payment($payment_obj);
	 
			$merchant_payment_confirmed = false;
	 
			/**********************************
			* Process the credit card here.
			* If not using a credit card
			* then redirect to merchant
			* and verify payment with an IPN
			* or proceed any other payment here
			**********************************/
			$trans_number = sanitize_text_field($_POST['campay_transaction_number']);
			$trans_number = "237".$trans_number;
			$trans_number = intval($trans_number);
			$price = (int)$purchase_data['price'];
			$currency = "XAF";
			$payment_timeout = 5;
			$description = "Payment from : ".site_url()." pruchase_key : ".$purchase_data['purchase_key'];
			$external_reference = $this->guidv4();
			
			$date_time = strtotime($purchase_data['date']);
			
			$order_created_date = new DateTime("NOW");
			$order_created_date->setTimestamp($date_time);
			$payment_timeout = 5;
			$order_expiry_time = $order_created_date;
			$order_expiry_time->add(new DateInterval("PT5M"));
			
			

			$token = $this->get_token($server_uri, $user, $pass);

			$params = array(
				"amount" => $price,
				"currency" => $currency,
				"from" => $trans_number,
				"description" => $description,
				"external_reference" => $external_reference
			);

			$params = json_encode($params);
            
            
			$today = strtotime("now");

			$expiry = strtotime("+" . $payment_timeout . " minutes", $today);
			
			$trans = $this->execute_payment($token, $params, $server_uri);

           

			if (!empty($trans) && !is_object($trans)) {
				$payment_completed = false;

				while (strtotime("now") <= $expiry) {

					$payment = $this->check_payment($token, $trans, $server_uri);

					if (!empty($payment)) {
						if (strtoupper($payment->status) == "SUCCESSFUL") {
							// if the merchant payment is complete, set a flag
							$merchant_payment_confirmed = true;		
							break;
						}
						if (strtoupper($payment->status) == "FAILED") {
							break;
						}
					}
				}
			}
	 
			if($merchant_payment_confirmed) { // this is used when processing credit cards on site
	 
				// once a transaction is successful, set the purchase to complete
				edd_update_payment_status($payment_obj, 'complete');
	 
				// go to the success page			
				edd_send_to_success_page();
	 
			} else {
			    edd_set_error('trans_error', __('Transaction failed! Transaction not authorized or insufficient balance', 'campay_edd' ));
				$fail = true; // payment wasn't recorded
			}
	 
		} else {
			$fail = true; // errors were detected
		}
		
		if( $fail !== false ) {
			// if errors are present, send the user back to the purchase page so they can be corrected
			edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
		}
	 
	}
   

	public function get_token($server_uri, $app_user, $app_password)
	{

	$user = $app_user;
	$pass = $app_password;
  
  
	$params = array("username"=>$user, "password"=>$pass);
	//$params = json_encode($params);

	$headers = array('Content-Type: application/json');

	$response = wp_remote_post($server_uri."/api/token/", array(
		"method"=>"POST",
		"sslverify"=>true,
		"headers"=>$headers,
		"body"=>$params
	));
	if(!is_wp_error($response))
	{
		$response_body = wp_remote_retrieve_body($response);
		$resp_array = json_decode($response_body);
		if(isset($resp_array->token))
			return $resp_array->token;
		else
			edd_set_error('token_error', __('Unable to get access token', 'campay_edd' ));
	}
	else
		edd_set_error('trans_error', __('Failed to initiate transaction please try again later', 'campay_edd' ));



	}

	public function execute_payment($token, $params, $server_uri)
	{

	$headers = array(
		'Authorization' => 'Token '.$token,
		'Content-Type' => 'application/json'
		);
		
	$response = wp_remote_post($server_uri."/api/collect/", array(
		"method"=>"POST",
		"sslverify"=>true,
		"body"=>$params,				
		"headers"=>$headers,
		"data_format"=>"body"
	));			

	if(!is_wp_error($response))
	{
		$response_body = wp_remote_retrieve_body($response);
		$resp_array = json_decode($response_body);
		if(isset($resp_array->reference))
			return $resp_array->reference;
		else
			edd_set_error('token_error', __('Invalid Access Token', 'campay_edd' ));
	}
	else
		edd_set_error('trans_error', __('Failed to initiate transaction please try again later', 'campay_edd' ));

	}

	public function check_payment($token, $trans, $server_uri)
	{

	$headers = array(
		'Authorization' => 'Token '.$token,
		'Content-Type' => 'application/json'
	);

	$response = wp_remote_get($server_uri."/api/transaction/".$trans."/", array(
		"sslverify"=>true,				
		"headers"=>$headers,
	));

	if(!is_wp_error($response))
	{
		$response_body = wp_remote_retrieve_body($response);
		$resp_array = json_decode($response_body);
		
		if(isset($resp_array->status))
			return $resp_array;
		else
			edd_set_error('trans_error', __('Invalid Transaction Reference', 'campay_edd' ));
	}
	else
		edd_set_error('trans_error', __('Failed to initiate transaction please try again later', 'campay_edd' ));


	}

    public function guidv4($data = null) {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
       

    public static function run()
    {
        static $instance = NULL;
        if(is_null($instance))
            $instance = new CamPay_EDD_Gateway();
        return $instance;
    }

}

CamPay_EDD_Gateway::run();
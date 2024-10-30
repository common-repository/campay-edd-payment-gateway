<?php

/*
 * Plugin Name: CamPay EDD Payment Gateway
 * Plugin URI: https://campay.net/wordpress/campay-edd-payment-gateway/
 * Description: Accept Mobile Money Payment using CamPay API Services.
 * Author: CamPay
 * Author URI: https://campay.net/
 * Version: 1.3
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
		add_filter('edd_currencies', array($this,'pippin_extra_edd_currencies'));
        add_action('edd_campay_payment_gateway_cc_form', array($this, 'campay_edd_payment_gateway_cc_form'));
        add_action('edd_campay_payment_gateway_cc_form', '__return_false');
        add_filter('edd_settings_gateways', array($this, 'campay_edd_add_settings'));
		add_action('edd_gateway_campay_payment_gateway', array($this, 'campay_edd_process_payment'));
		add_action( 'init', array($this, 'edd_listen_for_campay_return') );	
		add_action('wp_footer', array($this, 'edd_campay_card_payment'));
		
		// We need custom JavaScript to obtain a token
		add_action( 'wp_enqueue_scripts', array( $this, 'campay_edd_scripts' ) );		
		
    }

	// registers the gateway
	function campay_edd_register_gateway($gateways) {
		$gateways['campay_payment_gateway'] = array('admin_label' => 'Campay Payment Gateway', 'checkout_label' => __('CamPay Payment Gateway', 'campay_edd'));
		return $gateways;
	}

	function campay_edd_payment_gateway_cc_form() {
		
		
		?>
			
			          <div class="row css_accordion">
					  <div class="col">
						
						<div class="tabs">
						  <div class="tab">
							<input type="radio" id="rd1" name="rd" checked="checked">
							<label class="tab-label" for="rd1">PAY WITH MOMO OR OM</label>
							<div class="tab-content">
								<div class="form_holder">
									<!-- payment option input -->
									<input type="hidden" id="campay_payment_option" name="campay_payment_option" value="momo" />
									<div class="error_holder">
										<span id="campay-number-error"><?php echo _e("The number entered is not a valid MTN or ORANGE number", "campay_edd"); ?></span>
									</div>
									<div class="input_holder">
										<label>Enter a valide the Mobile Money number or Valid Orange Money number </label>
										<input type="text" name="campay_transaction_number" onchange="validate_number(this)" oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/(\.*?)\.*/g, '$1');" />
									</div>
								</div>
							</div>
						  </div>
						  <div class="tab">
							<input type="radio" id="rd2" name="rd">
							<label class="tab-label" for="rd2">PAY WITH VISA CARD</label>
							<div class="tab-content">
								<button id="pay_with_visacard" type="button" onclick="pay_card()" class="button_primary">Click here to pay using a credit or debit card</button>
							</div>
						  </div>
						</div>
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
			),
			array(
				'id' => 'campay_api_usd_rate',
				'name' => __('USD to XAF conversion rate', 'campay_edd'),
				'desc' => __('Enter your desired conversion rate between USD and XAF', 'campay_edd'),
				'type' => 'number',
				'size' => 'regular'
			),
			array(
				'id' => 'campay_api_eur_rate',
				'name' => __('EUR to XAF conversion rate', 'campay_edd'),
				'desc' => __('Enter your desired conversion rate between EUR and XAF', 'campay_edd'),
				'type' => 'number',
				'size' => 'regular'
			)
		);
	 
		return array_merge($settings, $sample_gateway_settings);	
	}
   
    public function campay_edd_scripts() {
        wp_enqueue_script('campay_edd_scripts', plugins_url('assets/js/campay.js', __FILE__), array('jquery'), false, true);
        wp_enqueue_style('campay_edd_scripts', plugins_url('assets/css/campay.css', __FILE__), array(), '1.0.0', 'all');
    }   
   
   
	function campay_edd_process_payment($purchase_data) {
		
		
		
		if( ! wp_verify_nonce( $purchase_data['gateway_nonce'], 'edd-gateway' ) ) {
			wp_die( __( 'Nonce verification has failed', 'easy-digital-downloads' ), __( 'Error', 'easy-digital-downloads' ), array( 'response' => 403 ) );
		}		
 
		global $edd_options;
		
		$user = $edd_options['campay_api_username'];
		$pass = $edd_options['campay_api_password'];
		$usd_xaf = $edd_options['campay_api_usd_rate'];
		$eur_xaf = $edd_options['campay_api_eur_rate'];
		/**********************************
		* set transaction mode
		**********************************/
	 
		if(edd_is_test_mode()) {
			$server_uri = "https://demo.campay.net";
		} else {
			// set live credentials here
			$server_uri = "https://www.campay.net";
		}
	 
		/**********************************
		* check for errors here
		**********************************/

	 
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
	 
	 
			/**********************************
			* Process the credit card here.
			* If not using a credit card
			* then redirect to merchant
			* and verify payment with an IPN
			* or proceed any other payment here
			**********************************/
			$payment_method = sanitize_text_field($_POST['campay_payment_option']);
			
			$trans_number = sanitize_text_field($_POST['campay_transaction_number']);
			$trans_number = "237".$trans_number;
			$trans_number = intval($trans_number);
			$price = (int)$purchase_data['price'];
			$currency = "XAF";
			$order_currency = $edd_options['currency'];
			$description = "Payment from : ".site_url()." purchase_key : ".$purchase_data['purchase_key'];
			
			$external_reference = $this->guidv4();
			
			$date_time = strtotime($purchase_data['date']);
			
			$order_created_date = new DateTime("NOW");
			$order_created_date->setTimestamp($date_time);
			$payment_timeout = 5;
			$order_expiry_time = $order_created_date;
			$order_expiry_time->add(new DateInterval("PT5M"));			
			
			if($payment_obj)
			{
				// Only send to Campay if the pending payment is created successfully
				
				$token = $this->get_token($server_uri, $user, $pass);

				if(strtolower($order_currency)=="usd" && strtolower($order_currency) !="eur")
				{
					$conversion_rate = (int) sanitize_text_field($usd_xaf);
					$converted_price = round(($conversion_rate * $price), 0);
					$price = $converted_price;
				}
				elseif(strtolower($order_currency) !="usd" && strtolower($order_currency) =="eur")
				{
					$conversion_rate = (int) sanitize_text_field($eur_xaf);
					$converted_price = round(($conversion_rate * $price), 0);
					$price = $converted_price;

				}
				elseif(strtolower($order_currency)=="xaf")
				{
					$price = (int)$purchase_data['price'];
				}
				else
				{
					edd_set_error('trans_error', __('Transaction failed! currency not supported', 'campay_edd' ));
					edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
				}

				if($payment_method == "card")
				{
					$params = array(
						"amount" => $price,
						"currency" => $currency,
						"from" => $trans_number,
						"description" => $description,
						"payment_options"=>"CARD",
						"external_reference" => "EDD - ".base64_encode($payment_obj),
						"redirect_url"=>site_url()
					);

					$params = json_encode($params);
					
					// Set the session data to recover this payment in the event of abandonment or error.
					EDD()->session->set( 'edd_resume_payment', $payment_obj );				
					
					$trans = $this->get_payment_link($token, $params, $server_uri);
					
					wp_redirect($trans);					
				}
				elseif($payment_method == "momo")
				{
					
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
							edd_set_error('trans_error', __('Transaction failed! Transaction not authorized or insufficient balance'.'  Amount : '.$price.' Order Currency : '.$order_currency, 'campay_edd' ));
							$fail = true; // payment wasn't recorded
						}

						if( $fail !== false ) {
							// if errors are present, send the user back to the purchase page so they can be corrected
							edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
						}							
					
					
				}
				else{
					edd_set_error('unkown_method', __('Payment method doesn\'t exist ', 'campay_edd' ));
					edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);					
				}


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
			if(isset($resp_array->token) && !isset($resp_array->non_field_errors))
				return $resp_array->token;
			elseif(!isset($resp_array->token) && isset($resp_array->non_field_errors))
				edd_set_error('token_error', __($resp_array->non_field_errors[0], 'campay_edd' ));
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
		if(!isset($resp_array->reference) && isset($resp_array->message))
			edd_set_error('transaction_error', __($resp_array->message."  params : ".serialize($params), 'campay_edd' ));
			
	}
	else
		edd_set_error('transaction_error', __('Failed to initiate transaction please try again later', 'campay_edd' ));

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

	public function get_payment_link($token, $params, $server_uri)
	{

						
		$headers = array(
						'Authorization' => 'Token '.$token,
						'Content-Type' => 'application/json'
		);
					
		$response = wp_remote_post($server_uri."/api/get_payment_link/", array(
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

			if(isset($resp_array->link))
				return $resp_array->link;
			else
				edd_set_error(  'Invalid Transaction Reference', 'error' );
							 
							 
		}
		else
			edd_set_error(  'Failed to initiate transaction please try again later', 'error' );	

	}


		/**
		 * Listens for a Campay IPN requests and then sends to the processing function
		 *
		 * @since 1.0
		 * @return void
		 */
		function edd_listen_for_campay_return() {
			
			if(isset($_GET['external_reference']) && strstr($_GET['external_reference'], "EDD"))
			{
				
				//$payment_id = (int) strip_tags(explode("-", $_GET['external_reference'])[1]);
				$payment_id = (int) base64_decode(strip_tags(explode("-", $_GET['external_reference'])[1]));
				$status = strip_tags($_GET['status']);
				$operator = strip_tags($_GET['operator']);
				$code = strip_tags($_GET['code']);
				$op_ref = strip_tags($_GET['operator_reference']);
				
				$payment = new EDD_Payment($payment_id);
			
				if($payment)
				{
    				if(strtolower($status)=="successful")
    				{
    				    $payment->status = 'complete';
    				    $payment->save();
    				    $payment->update_status('complete');
    				    $payment->save();
    				    $note = "Payment completed on ".date()." with operator : ".$operator.PHP_EOL."Payment code: ".$code.PHP_EOL."Operator reference: ".$op_ref;
    				    $payment->add_note($note);
    				    edd_send_to_success_page();
    				    
    				}
    				
    				if(strtolower($status)=="pending")
    				{
    				    
    				    edd_set_error('trans_error', __('Payment pending', 'campay_edd' ));
    					edd_send_back_to_checkout();
    				    
    				}
    				
    				if(strtolower($status)=="canceled" || strtolower($status)=="cancelled")
    				{
    				    
    				    edd_set_error('trans_error', __('User canceled payment', 'campay_edd' ));
    					edd_send_back_to_checkout();
    				    
    				}
    								
    				if(strtolower($status)=="timeout" || strtolower($status)=="cancelled")
    				{
    				    
    				    edd_set_error('trans_error', __('Payment window timeout', 'campay_edd' ));
    					edd_send_back_to_checkout();
    				    
    				}
    			
				}
			}

			
	}

	public function pippin_extra_edd_currencies( $currencies ) {
		$currencies['XAF'] = __('Francs CFA', 'edd');
		return $currencies;
	}

	public function edd_campay_card_payment()
	{
		?>
		
			<script>
				function pay_card()
				{
					
					event.preventDefault();
					document.getElementById("campay_payment_option").value="card";
					document.getElementById("edd-purchase-button").click();
						
					
				}
			
			</script>
		
		<?php
	}

    public static function run()
    {
        static $instance = NULL;
        if(is_null($instance))
            $instance = new CamPay_EDD_Gateway();
        return $instance;
    }

}
$all_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if (stripos(implode($all_plugins), 'easy-digital-downloads.php')) {
CamPay_EDD_Gateway::run();
}
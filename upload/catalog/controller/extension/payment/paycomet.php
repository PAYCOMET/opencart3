<?php


class ControllerExtensionPaymentPaycomet extends Controller {

	public $url_paycomet = "https://api.paycomet.com/gateway/bnkgateway.php";
	private $_client = null;

	
	public function index() {
		$this->load->model('extension/payment/paycomet');
    	$this->load->language('extension/payment/paycomet');

    	$data['paycomet_iframe'] = $this->paycomet_iframe_URL();

    	$paycomet_client  = $this->config->get('payment_paycomet_client');
    	$paycomet_terminal  = $this->config->get('payment_paycomet_terminal');
    	$paycomet_password  = $this->config->get('payment_paycomet_password');
    	$data['paycomet_commerce_password'] = $this->config->get('payment_paycomet_commerce_password');

		$data['months'] = array();

		for ($i = 1; $i <= 12; $i++) {
			$data['months'][] = array(
				'text'  => strftime('%B', mktime(0, 0, 0, $i, 1, 2000)),
				'value' => sprintf('%02d', $i)
			);
		}

		$today = getdate();

		$data['year_expire'] = array();

		for ($i = $today['year']; $i < $today['year'] + 11; $i++) {
			$data['year_expire'][] = array(
				'text'  => strftime('%Y', mktime(0, 0, 0, 1, 1, $i)),
				'value' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i))
			);
		}

		$data['saved_cards'] = $this->model_extension_payment_paycomet->getCards($this->customer->getId());

		$data['order_id'] = $this->session->data['order_id'];

		// Add Agree
		$customer_id = (isset($this->session->data['customer_id']))?$this->session->data['customer_id']:0;
		$data['show_savecard'] = ($customer_id>0)?1:0;

		$this->model_extension_payment_paycomet->addOrderAgree($this->session->data['order_id'],$customer_id);
		
        return $this->load->view('extension/payment/paycomet', $data);
	}


	public function paycomet_iframe_URL(){
		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		$amount = number_format($this->currency->format($order_info['total'], $order_info['currency_code'], false, false)*100,0, '.', '');
		$paycomet_client  = $this->config->get('payment_paycomet_client');
		$paycomet_apikey  = $this->config->get('payment_paycomet_apikey');
    	$paycomet_terminal  = $this->config->get('payment_paycomet_terminal');
    	$paycomet_password  = $this->config->get('payment_paycomet_password');

    	$paycomet_client  = $this->config->get('payment_paycomet_client');
    	$paycomet_terminal  = $this->config->get('payment_paycomet_terminal');
		$paycomet_password  = $this->config->get('payment_paycomet_password');
		
		$currency_iso_code = $order_info['currency_code'];
		$language = $this->config->get('config_language');

		$URLKO = HTTPS_SERVER . 'index.php?route=extension/payment/paycomet/paymenterror';
		$URLOK = HTTPS_SERVER . 'index.php?route=extension/payment/paycomet/paymentreturn';

		$paycomet_order_ref = $this->session->data['order_id'];

		$importe  = number_format($amount/ 100, 2, ".","");

		$secure_pay = $this->isSecureTransaction($importe,0)?1:0;

		$OPERATION = "1";

		if($paycomet_apikey != '') {
			include_once(DIR_APPLICATION . 'controller/extension/payment/PaycometApiRest.php');

			$apiRest = new PaycometApiRest($paycomet_apikey);

			$userInteraction = 1;
			$merchantData = $this->getMerchantData($order_info);

			$payment =  [
				'terminal' => (int) $paycomet_terminal,
				'order' => (string) $paycomet_order_ref,
				'amount' => (string) $amount,
				'currency' => (string) $currency_iso_code,
				'userInteraction' => (int) $userInteraction,
				'secure' => (int) $secure_pay,
				'merchantData' => $merchantData,
				'urlOk' => $URLOK,
				'urlKo' => $URLKO
			];

			$formResponse = $apiRest->form(
				$OPERATION,
				substr($language, 0, 2),
				$paycomet_terminal,
				'',
				$payment
			);

			if(isset($formResponse->challengeUrl)) {
				$url_paycomet = $formResponse->challengeUrl;
			} else {
				var_dump("Se ha producido un error: " . $formResponse->errorCode);
				die;
			}

		} else {
			// Cálculo Firma
			$signature = md5($paycomet_client.$paycomet_terminal.$OPERATION.$paycomet_order_ref.$amount.$currency_iso_code.md5($paycomet_password));
			$fields = array
			(
				'MERCHANT_MERCHANTCODE' => $paycomet_client,
				'MERCHANT_TERMINAL' => $paycomet_terminal,
				'OPERATION' => $OPERATION,
				'LANGUAGE' => $language,
				'MERCHANT_MERCHANTSIGNATURE' => $signature,
				'MERCHANT_ORDER' => $paycomet_order_ref,
				'MERCHANT_AMOUNT' => $amount,
				'MERCHANT_CURRENCY' => $currency_iso_code,
				'URLOK' => $URLOK,
				'URLKO' => $URLKO,
				'3DSECURE' => $secure_pay
			);
			$query = http_build_query($fields);
			
			$url_paycomet = $this->url_paycomet . "?".$query;
		}
		
		return $url_paycomet;
	}

	public function isSecureTransaction($importe,$card){
		$terminales = $this->config->get('payment_paycomet_terminales');
        $tdfirst = $this->config->get('payment_paycomet_tdfirst');
        $tdmin = $this->config->get('payment_paycomet_tdmin');
        // Transaccion Segura:
        
        // Si solo tiene Terminal Seguro
        if ($terminales==0)
            return true;   
        // Si esta definido que el pago es 3d secure y no estamos usando una tarjeta tokenizada
        if ($tdfirst && $card==0)
            return true;
        // Si se supera el importe maximo para compra segura
        if ($terminales==2 && ($tdmin>0 && $tdmin < $importe))
            return true;
         // Si esta definido como que la primera compra es Segura y es la primera compra aunque este tokenizada
        if ($terminales==2 && $tdfirst && $card>0 && $this->isFirstPurchaseToken($this->session->data['customer_id'],$card))
            return true;
        
        return false;
    }

    public function isFirstPurchaseToken($customer_id, $card){
    	$customer_id = $this->session->data['customer_id'];
    	$result = $this->db->query("SELECT * FROM " . DB_PREFIX . "paycomet_order WHERE id_customer = " . $customer_id . " AND paycomet_iduser = ". $card);
		return ($result->num_rows>0)?false:true;
		
    }

    public function saveOrderInfo(){
    	$this->load->model('checkout/order');

    	$paycomet_agree = $_POST["paycomet_agree"];
    	$order_id = $_POST["order_id"];

    	$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

    	if ($this->session->data['customer_id']==$order_info["customer_id"]){
    		$this->load->model('extension/payment/paycomet');
    		$this->model_extension_payment_paycomet->saveOrderInfo($this->customer->getId(),$order_id,$paycomet_agree);
    	}   	
    }

    public function paymentReturn(){
    	$data['url'] = $this->url->link('checkout/checkout');
    	$this->response->redirect($this->url->link('checkout/success', '', 'SSL'));
    }

    public function paymentError(){
    	$data['url'] = $this->url->link('checkout/checkout');
		$this->response->redirect($this->url->link('checkout/checkout', '', 'SSL'));
    }


	public function callback()
	{
		$this->load->model('extension/payment/paycomet');
		$this->load->model('checkout/order');

		$this->language->load('extension/payment/paycomet');

		$error = false;

		if (isset($this->request->post['TransactionType']) &&  isset($this->request->post['Response'])){

			if ($this->request->post['TransactionType']==1){
				$importe  = number_format($this->request->post['Amount']/ 100, 2, ".","");
				$order_id = $this->request->post['Order'];
				$result = $this->request->post['Response']=='OK'?1:0;
				$sign = $this->request->post['ExtendedSignature'];
				$esURLOK = false;
				$paycomet_client  = $this->config->get('payment_paycomet_client');
				$apiKey  = $this->config->get('payment_paycomet_apikey');
	    		$paycomet_terminal  = $this->config->get('payment_paycomet_terminal');
	    		$pass  = $this->config->get('payment_paycomet_password');

				$local_sign = md5($paycomet_client.$this->request->post['TpvID'].$this->request->post['TransactionType'].$order_id.$this->request->post['Amount'].$this->request->post['Currency'].md5($pass).$this->request->post['BankDateTime'].$this->request->post['Response']);
				
				// Check Signature
				if ($sign!=$local_sign)	die('Error 1');

				if($result == 1 && isset($this->request->post['IdUser']) && isset($this->request->post['TokenUser'])){

					$this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_paycomet_order_status_id'));
					
					if ($this->request->post['TransactionType']==1){
						$data = array(
							'Order'       	=> $this->request->post['Order'],
							'IdUser'       	=> $this->request->post['IdUser'],
							'TokenUser' 	=> $this->request->post['TokenUser'],
							'TransactionType'      => $this->request->post['TransactionType'],
							'AuthCode'      => $this->request->post['AuthCode'],
							'Amount'      	=> $importe,
							'Response'    	=> $result
						);
						
						$this->model_extension_payment_paycomet->updateOrder($data);

						$paycomet_order_info = $this->model_extension_payment_paycomet->getOrderInfo($order_id);
						$saveCard = ($paycomet_order_info["paycometagree"] && $paycomet_order_info["id_customer"]>0);


						if ($saveCard && !empty($this->request->post['IdUser']) && !empty($this->request->post['TokenUser'])) {

							if($apiKey != '') {
								include_once(DIR_APPLICATION . 'controller/extension/payment/PaycometApiRest.php');

								$apiRest = new PaycometApiRest($apiKey);

								$infoUserResponse = $apiRest->infoUser(
									$this->request->post['IdUser'],
									$this->request->post['TokenUser'],
									$paycomet_terminal
								);
		
								if ($infoUserResponse->errorCode == 0) {
									$result['DS_MERCHANT_PAN'] = $infoUserResponse->pan;
									$result['DS_CARD_BRAND'] = $infoUserResponse->cardBrand;
								}
							} else {
								$result = $this->infoUser($this->request->post['IdUser'],$this->request->post['TokenUser']);
							}
							
							$order_info = $this->model_checkout_order->getOrder($order_id);


							$this->model_extension_payment_paycomet->addCard($this->request->post['IdUser'],$this->request->post['TokenUser'],$result['DS_MERCHANT_PAN'],$result['DS_CARD_BRAND'],"",$order_info["customer_id"]);
						}
						print "Pedido procesado";
					}else{
						print "Procesado " . $this->request->post['TransactionType'];
					}
				}
			// Add User
			} else if ($this->request->post['TransactionType']==107){

				$ref = $this->request->post['Order'];
				$sign = $this->request->post['Signature'];
				$esURLOK = false;
				$pass = $this->config->get('payment_paycomet_password');
				$apiKey  = $this->config->get('payment_paycomet_apikey');
				$local_sign = md5($this->config->get('payment_paycomet_client').$this->config->get('payment_paycomet_terminal').$this->request->post['TransactionType'].$ref.$this->request->post['DateTime'].md5($pass));
				// Check Signature
				if ($sign!=$local_sign)	die('Error 2');

				if($apiKey != '') {
					include_once(DIR_APPLICATION . 'controller/extension/payment/PaycometApiRest.php');

					$apiRest = new PaycometApiRest($apiKey);

					$infoUserResponse = $apiRest->infoUser(
						$this->request->post['IdUser'],
						$this->request->post['TokenUser'],
						$this->config->get('payment_paycomet_terminal')
					);

					if ($infoUserResponse->errorCode == 0) {
						$result['DS_MERCHANT_PAN'] = $infoUserResponse->pan;
						$result['DS_CARD_BRAND'] = $infoUserResponse->cardBrand;
					}
				} else {
					$result = $this->infoUser( $this->request->post['IdUser'],$this->request->post['TokenUser']);
				}
				
				$this->model_extension_payment_paycomet->addCard($this->request->post['IdUser'],$this->request->post['TokenUser'],$result['DS_MERCHANT_PAN'],$result['DS_CARD_BRAND'],"",$this->request->post['Order']);

				print "Add User procesado";
			}
		
		}else{
			print "Error. No Transaction. No Response";
		}
	}


	public function validPassword($customer_id,$passwd){
		$result = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer WHERE customer_id = " . $customer_id . " AND (password = SHA1(CONCAT(salt, SHA1(CONCAT(salt, SHA1('" . $passwd . "'))))) OR password = '" . md5($passwd) . "') AND status = '1'");
		
		return ($result->num_rows>0)?true:false;
		
	}

	public function directPay()
	{
		$this->load->model('extension/payment/paycomet');
		$this->load->model('checkout/order');
		$this->load->language('extension/payment/paycomet');

		$card = $_POST["card"];
		$commerce_password = (isset($_POST["commerce_password"]))?$_POST["commerce_password"]:"";

		$customer_id = $this->session->data['customer_id'];

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
	

		if ($this->config->get('payment_paycomet_commerce_password')){
	        if (!$this->validPassword($customer_id,$commerce_password)){
	        	$json['error'] = $this->language->get('err_commerce_password');
	        	$this->response->addHeader('Content-Type: application/json');
				$this->response->setOutput(json_encode($json));
	        }
	    }

		if (isset($this->session->data['order_id']) && $card!=""){

			$card_data = $this->model_extension_payment_paycomet->getCard($this->session->data['customer_id'],$card);
			// Si la tarjeta no está asociada al usuario->Error
			if (empty($card_data)){
				$json['error'] = $this->language->get('err_card_user');
			}

			if (!isset($json['error'])){
				$order_id = $this->session->data['order_id'];
				$order_info = $this->model_checkout_order->getOrder($order_id);

				$amount = number_format($this->currency->format($order_info['total'], $order_info['currency_code'], false, false)*100,0, '.', '');	

				$importe  = number_format($amount/ 100, 2, ".","");
				
				$paycomet_client  = $this->config->get('payment_paycomet_client');
				$paycomet_apikey  = $this->config->get('payment_paycomet_apikey');
	    		$paycomet_terminal  = $this->config->get('payment_paycomet_terminal');
	    		$paycomet_password  = $this->config->get('payment_paycomet_password');
	    		$currency_iso_code = $order_info['currency_code'];

	    		$secure_pay = $this->isSecureTransaction($importe,$card)?1:0;

	    		if ($secure_pay){
					$language = $this->config->get('config_language');

					$paycomet_order_ref = $this->session->data['order_id'];

					$URLKO = HTTPS_SERVER . 'index.php?route=extension/payment/paycomet/paymenterror';
					$URLOK = HTTPS_SERVER . 'index.php?route=extension/payment/paycomet/paymentreturn';

	    			$OPERATION = "109"; //exec_purchase_token
	    			// Cálculo Firma
	    			$signature = md5($paycomet_client.$card_data['paycomet_iduser'].$card_data['paycomet_tokenuser'].$paycomet_terminal.$OPERATION.$paycomet_order_ref.$amount.$currency_iso_code.md5($paycomet_password));
		
					$fields = array
					(
						'MERCHANT_MERCHANTCODE' => $paycomet_client,
						'MERCHANT_TERMINAL' => $paycomet_terminal,
						'OPERATION' => $OPERATION,
						'LANGUAGE' => $language,
						'MERCHANT_MERCHANTSIGNATURE' => $signature,
						'MERCHANT_ORDER' => $paycomet_order_ref,
						'MERCHANT_AMOUNT' => $amount,
						'MERCHANT_CURRENCY' => $currency_iso_code,
						'IDUSER' => $card_data['paycomet_iduser'],
						'TOKEN_USER' => $card_data['paycomet_tokenuser'],
						'3DSECURE' => 1,
						'URLOK' => $URLOK,
						'URLKO' => $URLKO
					);

					$query = http_build_query($fields);
					
					$url_paycomet = $this->url_paycomet . "?".$query;

					$json['ACSURL'] = $url_paycomet;
	    		} else {
					if($paycomet_apikey != '') {
						include_once(DIR_APPLICATION . 'controller/extension/payment/PaycometApiRest.php');

						$userInteraction = '1';
						$methodId = '1';
						$notifyDirectPayment = 1;
						$merchantData = $this->getMerchantData($order_info);
						$URLKO = HTTPS_SERVER . 'index.php?route=extension/payment/paycomet/paymenterror';
						$URLOK = HTTPS_SERVER . 'index.php?route=extension/payment/paycomet/paymentreturn';

						$executePurchaseResponse = $apiRest->executePurchase(
							$paycomet_terminal,
							$order_id,
							$importe,
							$currency_iso_code,
							$methodId,
							Tools::getRemoteAddr(),
							$secure_pay,
							$data["IDUSER"],
							$data['TOKEN_USER'],
							$URLOK,
							$URLKO,
							'',
							'',
							'',
							$userInteraction,
							[],
							'',
							'',
							$merchantData,
							$notifyDirectPayment
						);

						$charge = array();
						$charge["DS_RESPONSE"] = ($executePurchaseResponse->errorCode > 0)? 0 : 1;
						$charge['DS_ERROR_ID'] = $executePurchaseResponse->errorCode;
					} else {
						$charge = $this->executePurchase($card_data['paycomet_iduser'],$card_data['paycomet_tokenuser'],$paycomet_client,$paycomet_terminal,$paycomet_password,$currency_iso_code,$importe,$order_id);
					}

		    		if ( ( int ) $charge[ 'DS_RESPONSE' ] == 1 ) {

		    			$this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_paycomet_order_status_id'));
		    			$OPERATION = "1";

						$data = array(
							'Order'       	=> $order_id,
							'IdUser'       	=> $card_data['paycomet_iduser'],
							'TokenUser' 	=> $card_data['paycomet_tokenuser'],
							'TransactionType'   => $OPERATION,
							'AuthCode'      => $charge['DS_MERCHANT_AUTHCODE'],
							'Amount'      	=> $importe,
							'Response'    	=> $charge['DS_RESPONSE']
						);
						
						$this->model_extension_payment_paycomet->updateOrder($data);

						$json['redirect']  = $this->url->link('checkout/success', '', 'SSL');
						
					}else{

						$json['error']  = $this->language->get('err_error') . $charge['DS_ERROR_ID'];

						$this->response->redirect($this->url->link('checkout/checkout', '', 'SSL'));
					}
				}
			}
		
		}else{
			$json['redirect']  = $this->url->link('account/login', '', 'SSL');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function getMerchantData($order)
	{
		$MERCHANT_EMV3DS = $this->getEMV3DS($order);
		$SHOPPING_CART = $this->getShoppingCart($order);

		$merchantData = array_merge($MERCHANT_EMV3DS,$SHOPPING_CART);

		return $merchantData;
	}

	private function getEMV3DS($order)
	{
		$this->load->model('localisation/country');

		$Merchant_EMV3DS = array();

		// Customer info
		$Merchant_EMV3DS["customer"]["id"] = $order['customer_id'] ?? '';
		$Merchant_EMV3DS["customer"]["name"] = $order['firstname'] ?? '';
		$Merchant_EMV3DS["customer"]["surname"] = $order['lastname'] ?? '';
		$Merchant_EMV3DS["customer"]["email"] = $order['email'] ?? '';

		if(isset($order['telephone'])) {
			$arrDatosHomePhone["cc"] = $order['payment_country'] ? $this->isoCodePhonePrefix($this->model_localisation_country->getCountry($order['payment_country_id'])["iso_code_2"]) : '34';
			$arrDatosHomePhone["subscriber"] = $order['telephone'];
			$Merchant_EMV3DS["customer"]["homePhone"] = $arrDatosHomePhone;
		}

		$billingCountry = $order['payment_country'] ? $this->isoCodeToNumber($this->model_localisation_country->getCountry($order['payment_country_id'])["iso_code_2"]) : '';
		
		// Billing info
		$Merchant_EMV3DS["billing"]["billAddrCity"] = $order['payment_city'] ?? '';
		$Merchant_EMV3DS["billing"]["billAddrCountry"] = $billingCountry;
		$Merchant_EMV3DS["billing"]["billAddrLine1"] = $order['payment_address_1'] ?? '';
		$Merchant_EMV3DS["billing"]["billAddrLine2"] = $order['payment_address_2'] ?? '';
		$Merchant_EMV3DS["billing"]["billAddrPostCode"] = $order['payment_postcode'] ?? '';
		if($billingCountry != '') {
			$Merchant_EMV3DS["billing"]["billAddrState"] = $order['payment_zone'] ?? '';
		}
		
		$shippingCountry = $order['shipping_country'] ? $this->isoCodeToNumber($this->model_localisation_country->getCountry($order['shipping_country_id'])["iso_code_2"]) : '';

		// Shipping info
		$Merchant_EMV3DS["shipping"]["shipAddrCity"] = $order['shipping_city'] ?? '';
		$Merchant_EMV3DS["shipping"]["shipAddrCountry"] = $shippingCountry;
		$Merchant_EMV3DS["shipping"]["shipAddrLine1"] = $order['shipping_address_1'] ?? '';
		$Merchant_EMV3DS["shipping"]["shipAddrLine2"] = $order['shipping_address_2'] ?? '';
		$Merchant_EMV3DS["shipping"]["shipAddrPostCode"] = $order['shipping_postcode'] ?? '';
		if($shippingCountry != '') {
			$Merchant_EMV3DS["shipping"]["shipAddrState"] = $order['shipping_zone'] ?? '';
		}

		// Account Info
		$Merchant_EMV3DS["acctInfo"] = $this->accountInfo($order);

		// 3DS Info
		$Merchant_EMV3DS["threeDSRequestorAuthenticationInfo"] = $this->threeDSRequestorAuthenticationInfo();

		// Address Match
		$Merchant_EMV3DS["addrMatch"] = $order['payment_city'] == $order['shipping_city'] && 
										$order['payment_country'] == $order['shipping_country'] &&
										$order['payment_address_1'] == $order['shipping_address_1'] ? "Y" : "N";

		$Merchant_EMV3DS["challengeWindowSize"] = 05;

		return $Merchant_EMV3DS;
	}

	public function accountInfo($order)
	{
		$acctInfoData = array();
		$date_now = new DateTime("now");

		if (!isset($order['customer_id'])){
			$acctInfoData["chAccAgeInd"] = "01";
		} else {
			$userRegistryDate = $this->db->query("SELECT date_added FROM " . DB_PREFIX . "customer WHERE customer_id = " . $order['customer_id']);
			$registryDate = new DateTime($userRegistryDate->row['date_added']);
			$date_now = new DateTime("now");
			$diff = $date_now->diff($registryDate);
			$dias = $diff->days;
			
			if ($dias == 0) {
				$acctInfoData["chAccAgeInd"] = "02";
			} else if ($dias < 30) {
				$acctInfoData["chAccAgeInd"] = "03";
			} else if ($dias < 60) {
				$acctInfoData["chAccAgeInd"] = "04";
			} else {
				$acctInfoData["chAccAgeInd"] = "05";
			}
			
			if(isset($order['customer_id'])) {
				$acctInfoData["nbPurchaseAccount"] = $this->getUserCompletedOrders($order['customer_id']);
				$acctInfoData["txnActivityDay"] = $this->getUserTransactions($order['customer_id'],'1 day');
				$acctInfoData["txnActivityYear"] = $this->getUserTransactions($order['customer_id'],'1 year');
			}

			if(($this->customer->getFirstName() != $order['firstname']) || ($this->customer->getLastName() != $order['lastname'])) {
				$acctInfoData["shipNameIndicator"] = "02";
			} else {
				$acctInfoData["shipNameIndicator"] = "01";
			}
		}

		$acctInfoData["suspiciousAccActivity"] = "01";

		return $acctInfoData;
	}

	public function threeDSRequestorAuthenticationInfo()
	{
		$threeDSRequestorAuthenticationInfo = array();
		$threeDSRequestorAuthenticationInfo["threeDSReqAuthData"] = "";
		$threeDSRequestorAuthenticationInfo["threeDSReqAuthMethod"] = ($this->customer->isLogged()) ? "02" : "01";

		return $threeDSRequestorAuthenticationInfo;
	}

	private function getUserCompletedOrders($customerId)
	{
		$date = new DateTime("now");
		$date->modify('+6 months');
		$fecha = (string) $date->format('Y-m-d');

		$userCompletedOrders = $this->db->query("SELECT * FROM " . DB_PREFIX . "order WHERE customer_id = " . $customerId . " AND order_status_id = 5 AND date_modified > " . $fecha);

		return $userCompletedOrders->num_rows;
	}

	private function getUserTransactions($customerId, $time)
	{
		$date = new DateTime("now");
		$date->modify('+' . $time);
		$fecha = (string) $date->format('Y-m-d');

		$userCompletedOrders = $this->db->query("SELECT * FROM " . DB_PREFIX . "order WHERE customer_id = " . $customerId . " AND date_modified > " . $fecha);

		return $userCompletedOrders->num_rows;
	}

	private function getShoppingCart($order)
	{
		$shoppingCartData = array();

		$products = $this->cart->getProducts();

		$this->load->model('catalog/product');

		foreach($products as $product) {
			$productInfo = $this->model_catalog_product->getProduct($product['product_id']);

			$productId = $product['product_id'];
			$shoppingCartData[$productId]["sku"] = $productInfo['sku'];
			$shoppingCartData[$productId]["quantity"] = $product['quantity'];
			$shoppingCartData[$productId]["unitPrice"] = $product['price'];
			$shoppingCartData[$productId]["name"] = $product['name'];
		}

		return array("shoppingCart"=>array_values($shoppingCartData));
	}

	public function isoCodeToNumber($code)
    {
        $arrCode = array(
            "AF" => "004", "AX" => "248", "AL" => "008", "DE" => "276", "AD" => "020", "AO" => "024", "AI" => "660",
            "AQ" => "010", "AG" => "028", "SA" => "682", "DZ" => "012", "AR" => "032", "AM" => "051", "AW" => "533",
            "AU" => "036", "AT" => "040", "AZ" => "031", "BS" => "044", "BD" => "050", "BB" => "052", "BH" => "048",
            "BE" => "056", "BZ" => "084", "BJ" => "204", "BM" => "060", "BY" => "112", "BO" => "068", "BQ" => "535",
            "BA" => "070", "BW" => "072", "BR" => "076", "BN" => "096", "BG" => "100", "BF" => "854", "BI" => "108",
            "BT" => "064", "CV" => "132", "KH" => "116", "CM" => "120", "CA" => "124", "QA" => "634", "TD" => "148",
            "CL" => "52", "CN" => "156", "CY" => "196", "CO" => "170", "KM" => "174", "KP" => "408", "KR" => "410",
            "CI" => "384", "CR" => "188", "HR" => "191", "CU" => "192", "CW" => "531", "DK" => "208", "DM" => "212",
            "EC" => "218", "EG" => "818", "SV" => "222", "AE" => "784", "ER" => "232", "SK" => "703", "SI" => "705",
            "ES" => "724", "US" => "840", "EE" => "233", "ET" => "231", "PH" => "608", "FI" => "246", "FJ" => "242",
            "FR" => "250", "GA" => "266", "GM" => "270", "GE" => "268", "GH" => "288", "GI" => "292", "GD" => "308",
            "GR" => "300", "GL" => "304", "GP" => "312", "GU" => "316", "GT" => "320", "GF" => "254", "GG" => "831",
            "GN" => "324", "GW" => "624", "GQ" => "226", "GY" => "328", "HT" => "332", "HN" => "340", "HK" => "344",
            "HU" => "348", "IN" => "356", "ID" => "360", "IQ" => "368", "IR" => "364", "IE" => "372", "BV" => "074",
            "IM" => "833", "CX" => "162", "IS" => "352", "KY" => "136", "CC" => "166", "CK" => "184", "FO" => "234",
            "GS" => "239", "HM" => "334", "FK" => "238", "MP" => "580", "MH" => "584", "PN" => "612", "SB" => "090",
            "TC" => "796", "UM" => "581", "VG" => "092", "VI" => "850", "IL" => "376", "IT" => "380", "JM" => "388",
            "JP" => "392", "JE" => "832", "JO" => "400", "KZ" => "398", "KE" => "404", "KG" => "417", "KI" => "296",
            "KW" => "414", "LA" => "418", "LS" => "426", "LV" => "428", "LB" => "422", "LR" => "430", "LY" => "434",
            "LI" => "438", "LT" => "440", "LU" => "442", "MO" => "446", "MK" => "807", "MG" => "450", "MY" => "458",
            "MW" => "454", "MV" => "462", "ML" => "466", "MT" => "470", "MA" => "504", "MQ" => "474", "MU" => "480",
            "MR" => "478", "YT" => "175", "MX" => "484", "FM" => "583", "MD" => "498", "MC" => "492", "MN" => "496",
            "ME" => "499", "MS" => "500", "MZ" => "508", "MM" => "104", "NA" => "516", "NR" => "520", "NP" => "524",
            "NI" => "558", "NE" => "562", "NG" => "566", "NU" => "570", "NF" => "574", "NO" => "578", "NC" => "540",
            "NZ" => "554", "OM" => "512", "NL" => "528", "PK" => "586", "PW" => "585", "PS" => "275", "PA" => "591",
            "PG" => "598", "PY" => "600", "PE" => "604", "PF" => "258", "PL" => "616", "PT" => "620", "PR" => "630",
            "GB" => "826", "EH" => "732", "CF" => "140", "CZ" => "203", "CG" => "178", "CD" => "180", "DO" => "214",
            "RE" => "638", "RW" => "646", "RO" => "642", "RU" => "643", "WS" => "882", "AS" => "016", "BL" => "652",
            "KN" => "659", "SM" => "674", "MF" => "663", "PM" => "666", "VC" => "670", "SH" => "654", "LC" => "662",
            "ST" => "678", "SN" => "686", "RS" => "688", "SC" => "690", "SL" => "694", "SG" => "702", "SX" => "534",
            "SY" => "760", "SO" => "706", "LK" => "144", "SZ" => "748", "ZA" => "710", "SD" => "729", "SS" => "728",
            "SE" => "752", "CH" => "756", "SR" => "740", "SJ" => "744", "TH" => "764", "TW" => "158", "TZ" => "834",
            "TJ" => "762", "IO" => "086", "TF" => "260", "TL" => "626", "TG" => "768", "TK" => "772", "TO" => "776",
            "TT" => "780", "TN" => "788", "TM" => "795", "TR" => "792", "TV" => "798", "UA" => "804", "UG" => "800",
            "UY" => "858", "UZ" => "860", "VU" => "548", "VA" => "336", "VE" => "862", "VN" => "704", "WF" => "876",
            "YE" => "887", "DJ" => "262", "ZM" => "894", "ZW" => "716");

        return $arrCode[$code];
	}
	
	public function isoCodePhonePrefix($code)
	{
		try {
			$arrCode = array("AC" => "247", "AD" => "376", "AE" => "971", "AF" => "93","AG" => "268", "AI" => "264", "AL" => "355", "AM" => "374", "AN" => "599", "AO" => "244", "AR" => "54", "AS" => "684", "AT" => "43", "AU" => "61", "AW" => "297", "AX" => "358", "AZ" => "374", "AZ" => "994", "BA" => "387", "BB" => "246", "BD" => "880", "BE" => "32", "BF" => "226", "BG" => "359", "BH" => "973", "BI" => "257", "BJ" => "229", "BM" => "441", "BN" => "673", "BO" => "591", "BR" => "55", "BS" => "242", "BT" => "975", "BW" => "267", "BY" => "375", "BZ" => "501", "CA" => "1", "CC" => "61", "CD" => "243", "CF" => "236", "CG" => "242", "CH" => "41", "CI" => "225", "CK" => "682", "CL" => "56", "CM" => "237", "CN" => "86", "CO" => "57", "CR" => "506", "CS" => "381", "CU" => "53", "CV" => "238", "CX" => "61", "CY" => "392", "CY" => "357", "CZ" => "420", "DE" => "49", "DJ" => "253", "DK" => "45", "DM" => "767", "DO" => "809", "DZ" => "213", "EC" => "593", "EE" => "372", "EG" => "20", "EH" => "212", "ER" => "291", "ES" => "34", "ET" => "251", "FI" => "358", "FJ" => "679", "FK" => "500", "FM" => "691", "FO" => "298", "FR" => "33", "GA" => "241", "GB" => "44", "GD" => "473", "GE" => "995", "GF" => "594", "GG" => "44", "GH" => "233", "GI" => "350", "GL" => "299", "GM" => "220", "GN" => "224", "GP" => "590", "GQ" => "240", "GR" => "30", "GT" => "502", "GU" => "671", "GW" => "245", "GY" => "592", "HK" => "852", "HN" => "504", "HR" => "385", "HT" => "509", "HU" => "36", "ID" => "62", "IE" => "353", "IL" => "972", "IM" => "44", "IN" => "91", "IO" => "246", "IQ" => "964", "IR" => "98", "IS" => "354", "IT" => "39", "JE" => "44", "JM" => "876", "JO" => "962", "JP" => "81", "KE" => "254", "KG" => "996", "KH" => "855", "KI" => "686", "KM" => "269", "KN" => "869", "KP" => "850", "KR" => "82", "KW" => "965", "KY" => "345", "KZ" => "7", "LA" => "856", "LB" => "961", "LC" => "758", "LI" => "423", "LK" => "94", "LR" => "231", "LS" => "266", "LT" => "370", "LU" => "352", "LV" => "371", "LY" => "218", "MA" => "212", "MC" => "377", "MD"  > "533", "MD" => "373", "ME" => "382", "MG" => "261", "MH" => "692", "MK" => "389", "ML" => "223", "MM" => "95", "MN" => "976", "MO" => "853", "MP" => "670", "MQ" => "596", "MR" => "222", "MS" => "664", "MT" => "356", "MU" => "230", "MV" => "960", "MW" => "265", "MX" => "52", "MY" => "60", "MZ" => "258", "NA" => "264", "NC" => "687", "NE" => "227", "NF" => "672", "NG" => "234", "NI" => "505", "NL" => "31", "NO" => "47", "NP" => "977", "NR" => "674", "NU" => "683", "NZ" => "64", "OM" => "968", "PA" => "507", "PE" => "51", "PF" => "689", "PG" => "675", "PH" => "63", "PK" => "92", "PL" => "48", "PM" => "508", "PR" => "787", "PS" => "970", "PT" => "351", "PW" => "680", "PY" => "595", "QA" => "974", "RE" => "262", "RO" => "40", "RS" => "381", "RU" => "7", "RW" => "250", "SA" => "966", "SB" => "677", "SC" => "248", "SD" => "249", "SE" => "46", "SG" => "65", "SH" => "290", "SI" => "386", "SJ" => "47", "SK" => "421", "SL" => "232", "SM" => "378", "SN" => "221", "SO" => "252", "SO" => "252", "SR"  > "597", "ST" => "239", "SV" => "503", "SY" => "963", "SZ" => "268", "TA" => "290", "TC" => "649", "TD" => "235", "TG" => "228", "TH" => "66", "TJ" => "992", "TK" =>  "690", "TL" => "670", "TM" => "993", "TN" => "216", "TO" => "676", "TR" => "90", "TT" => "868", "TV" => "688", "TW" => "886", "TZ" => "255", "UA" => "380", "UG" =>  "256", "US" => "1", "UY" => "598", "UZ" => "998", "VA" => "379", "VC" => "784", "VE" => "58", "VG" => "284", "VI" => "340", "VN" => "84", "VU" => "678", "WF" => "681", "WS" => "685", "YE" => "967", "YT" => "262", "ZA" => "27","ZM" => "260", "ZW" => "263");
			return $arrCode[$code];
		} catch (exception $e) {}

		return "";
	}
	
	private function getClient()
    {
		// Para entornos de desarrollo con certificados propios
		$context = stream_context_create(array(
			'ssl' => array(
				'verify_peer' => true,
				'verify_peer_name' => false,
				'allow_self_signed' => true
			)
		));

        if (null == $this->_client)
            $this->_client = new SoapClient('https://api.paycomet.com/gateway/xml_bankstore.php?wsdl', array('stream_context' => $context));
 
        return $this->_client;
    }

    private function infoUser($DS_IDUSER, $DS_TOKEN_USER)
    {
        $this->load->model('extension/payment/paycomet');

        $DS_MERCHANT_MERCHANTCODE = $this->config->get('payment_paycomet_client');
        $DS_MERCHANT_TERMINAL = $this->config->get('payment_paycomet_terminal');
        $DS_MERCHANT_PASSWORD = $this->config->get('payment_paycomet_password');
        $DS_MERCHANT_MERCHANTSIGNATURE = hash('sha512', $DS_MERCHANT_MERCHANTCODE . $DS_IDUSER . $DS_TOKEN_USER . $DS_MERCHANT_TERMINAL . $DS_MERCHANT_PASSWORD);
        
        return $this->getClient()->info_user(
            $DS_MERCHANT_MERCHANTCODE, $DS_MERCHANT_TERMINAL, $DS_IDUSER, $DS_TOKEN_USER, $DS_MERCHANT_MERCHANTSIGNATURE, $_SERVER['REMOTE_ADDR']);
    }


    private function executePurchase($paycomet_iduser, $paycomet_tokenuser, $paycomet_client,$paycomet_terminal,$paycomet_password,$currency_iso_code,$importe,$orderid)
    {
        $DS_MERCHANT_MERCHANTCODE = $paycomet_client;
        $DS_IDUSER = $paycomet_iduser;
        $DS_TOKEN_USER = $paycomet_tokenuser;
        $DS_MERCHANT_AMOUNT = round($importe * 100);
        $DS_MERCHANT_ORDER = $orderid;
        $DS_MERCHANT_CURRENCY = $currency_iso_code;
        $DS_MERCHANT_TERMINAL = $paycomet_terminal;
        $DS_MERCHANT_MERCHANTSIGNATURE = hash('sha512', $DS_MERCHANT_MERCHANTCODE . $DS_IDUSER . $DS_TOKEN_USER . $DS_MERCHANT_TERMINAL . $DS_MERCHANT_AMOUNT . $DS_MERCHANT_ORDER . $paycomet_password);
        $DS_ORIGINAL_IP = $_SERVER['REMOTE_ADDR'];
        $DS_MERCHANT_PRODUCTDESCRIPTION = $orderid;
        $DS_MERCHANT_OWNER = '';

        $DS_MERCHANT_OWNER = ''; /*@TODO: Set owner*/
        return $this->getClient()->execute_purchase(
            $DS_MERCHANT_MERCHANTCODE,
            $DS_MERCHANT_TERMINAL,
            $DS_IDUSER,
            $DS_TOKEN_USER,
            $DS_MERCHANT_AMOUNT,
            $DS_MERCHANT_ORDER,
            $DS_MERCHANT_CURRENCY,
            $DS_MERCHANT_MERCHANTSIGNATURE,
            $DS_ORIGINAL_IP,
            $DS_MERCHANT_PRODUCTDESCRIPTION,
            $DS_MERCHANT_OWNER
        );
    }

}

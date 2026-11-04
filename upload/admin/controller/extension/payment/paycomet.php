<?php
class ControllerExtensionPaymentPaycomet extends Controller {
	private $error = array();		

	public function index() {

		$this->load->language('extension/payment/paycomet');
		
		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('setting/setting');


		/* END ERRORS */

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_paycomet', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

   		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/paycomet', 'user_token=' . $this->session->data['user_token'], true)
   		);

   		
   		$data['action'] = $this->url->link('extension/payment/paycomet', 'user_token=' . $this->session->data['user_token'], 'SSL');
		$data['cancel'] = $this->url->link('extension/extension/payment', 'user_token=' . $this->session->data['user_token'], 'SSL');
		
		$this->id       = 'content';
		

        if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		/* END COMMON STUFF */


		/* START FIELDS */
		$data['extension_class'] = 'payment';
		$data['tab_class'] = 'htabs';

		if (isset($this->request->post['payment_paycomet_status'])) {
			$data['payment_paycomet_status'] = $this->request->post['payment_paycomet_status'];
		} else {
			$data['payment_paycomet_status'] = $this->config->get('payment_paycomet_status');
		}

		if (isset($this->request->post['payment_paycomet_client'])) {
			$data['payment_paycomet_client'] = $this->request->post['payment_paycomet_client'];
		} else {
			$data['payment_paycomet_client'] = $this->config->get('payment_paycomet_client');
		}

		if (isset($this->request->post['payment_paycomet_apikey'])) {
			$data['payment_paycomet_apikey'] = $this->request->post['payment_paycomet_apikey'];
		} else {
			$data['payment_paycomet_apikey'] = $this->config->get('payment_paycomet_apikey');
		}

		if (isset($this->request->post['payment_paycomet_apikey'])) {
			$data['payment_paycomet_apikey'] = $this->request->post['payment_paycomet_apikey'];
		} else {
			$data['payment_paycomet_apikey'] = $this->config->get('payment_paycomet_apikey');
		}

		if (isset($this->request->post['payment_paycomet_terminal'])) {
			$data['payment_paycomet_terminal'] = $this->request->post['payment_paycomet_terminal'];
		} else {
			$data['payment_paycomet_terminal'] = $this->config->get('payment_paycomet_terminal');
		}

		if (isset($this->request->post['payment_paycomet_password'])) {
			$data['payment_paycomet_password'] = $this->request->post['payment_paycomet_password'];
		} else {
			$data['payment_paycomet_password'] = $this->config->get('payment_paycomet_password');
		}		

		if (isset($this->request->post['payment_paycomet'])) {
			$data['payment_paycomet'] = $this->request->post['payment_paycomet'];
		} else {
			$data['payment_paycomet'] = $this->config->get('payment_paycomet');
		}

		$this->load->model('localisation/order_status');
		
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		
		if (isset($this->request->post['payment_paycomet_order_status_id'])) {
			$data['payment_paycomet_order_status_id'] = $this->request->post['payment_paycomet_order_status_id'];
		} else {
			$data['payment_paycomet_order_status_id'] = $this->config->get('payment_paycomet_order_status_id');
		}

		if (isset($this->request->post['payment_paycomet_sort_order'])) {
			$data['payment_paycomet_sort_order'] = $this->request->post['payment_paycomet_sort_order'];
		} else {
			$data['payment_paycomet_sort_order'] = $this->config->get('payment_paycomet_sort_order');
		}
			
		if (isset($this->error['client'])) {
			$data['error_client'] = $this->error['client'];
		} else {
			$data['error_client'] = '';
		}

		if (isset($this->error['apikey'])) {
			$data['error_apikey'] = $this->error['apikey'];
		} else {
			$data['error_apikey'] = '';
		}

		if (isset($this->error['terminal'])) {
			$data['error_terminal'] = $this->error['terminal'];
		} else {
			$data['error_terminal'] = '';
		}

		if (isset($this->error['password'])) {
			$data['error_password'] = $this->error['password'];
		} else {
			$data['error_password'] = '';
		}

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/paycomet', $data));
	}

	private function validate($errors = array()) {
		if (!$this->user->hasPermission('modify', 'extension/payment/paycomet')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['payment_paycomet_client']) {
			$this->error['client'] = $this->language->get('error_client');
		}

		if (!$this->request->post['payment_paycomet_apikey']) {
			$this->error['apikey'] = $this->language->get('error_apikey');
		}

		if (!$this->request->post['payment_paycomet_terminal']) {
			$this->error['terminal'] = $this->language->get('error_terminal');
		}

		if (!$this->request->post['payment_paycomet_password']) {
			$this->error['password'] = $this->language->get('error_password');
		}
		
		foreach ($errors as $error) {
			if (isset($this->request->post[$this->name . '_' . $error]) && !$this->request->post[$this->name . '_' . $error]) {
				$this->error[$error] = $this->language->get('error_' . $error);
			}
		}

		if (!$this->error) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	public function install() {
		$this->load->model('extension/payment/paycomet');
		$this->model_extension_payment_paycomet->install();
	}

	public function uninstall(){
		$this->load->model('extension/payment/paycomet');
		$this->model_extension_payment_paycomet->uninstall();
	}

	public function order() {
		
		if ($this->config->get('payment_paycomet_status')) {
			$this->load->language('extension/payment/paycomet_order');
			

			if (isset($this->request->get['order_id'])) {
				$order_id = $this->request->get['order_id'];
			} else {
				$order_id = 0;
			}

			$this->load->model('extension/payment/paycomet');

			$paycomet_info = $this->model_extension_payment_paycomet->getPaycometOrder($order_id);

			if ($paycomet_info) {

				$data['user_token'] = $this->session->data['user_token'];

				$data['order_id'] = $this->request->get['order_id'];

				$data['capture_status'] = ($paycomet_info['result'])?"OK":"KO";

				$data['total'] = $paycomet_info['amount'];

				$captured = number_format($this->model_extension_payment_paycomet->totalCaptured($this->customer->getId(), $this->request->get['order_id']), 2);

				$data['captured'] = $captured;

				$data['capture_remaining'] = number_format($paycomet_info['amount'] - $captured, 2);

				$refunded = number_format($this->model_extension_payment_paycomet->totalRefundedOrder($this->request->get['order_id']), 2);

				$data['refunded'] = $refunded;

				return $this->load->view('extension/payment/paycomet_order', $data);
			}
		}
	}

	public function transaction() {

					
		$this->load->language('extension/payment/paycomet_order');

		$data['transactions'] = array();

		if (isset($this->request->get['order_id'])) {
			$order_id = $this->request->get['order_id'];
		} else {
			$order_id = 0;
		}

		$this->load->model('extension/payment/paycomet');

		$paycomet_info = $this->model_extension_payment_paycomet->getOrder($order_id);

		if ($paycomet_info){

			$results = $this->model_extension_payment_paycomet->getPaycometOrderRefund($order_id);
			$total_refunded = 0;
			$data2 = array();
			if ($results){
				
				foreach ($results as $result) {

					$data2[] = array(
						'transaction_id' => $result['authcode'],
						'amount'         => $result['amount'],
						'date'           => $result['date'],
						'type'           => "refund",
						'type_text'      => $this->language->get('txt_refund'),
						'refund'         => ""
					);
					$total_refunded += $result['amount'];
				}
			}


			$data['transactions'][] = array(
					'total_refunded'  => $total_refunded,
					'transaction_id' => $paycomet_info['authcode'],
					'amount'         => $paycomet_info['amount'],
					'date'           => $paycomet_info['date'],
					'type'           => "capture",
					'type_text'           => $this->language->get('txt_payment'),
					'refund'         => $this->url->link('extension/payment/paycomet/refund', 'token=' . $this->session->data['user_token'] . '&order_id=' . $order_id, true),
					
				);


			$data['transactions'] = array_merge($data['transactions'],$data2);

		}
		//return $this->load->view('extension/payment/paycomet_transaction', $data);
		$this->response->setOutput($this->load->view('extension/payment/paycomet_transaction', $data));
	}

	public function refund(){
		
		$this->load->model('extension/payment/paycomet');
		$this->load->model('sale/order');
		$this->load->language('extension/payment/paycomet');

		$transaction = $this->model_extension_payment_paycomet->getOrder($this->request->get['order_id']);

		if ($transaction) {
			$this->document->setTitle($this->language->get('heading_refund'));

			$data['breadcrumbs'] = array();

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home', 'token=' . $this->session->data['user_token'], 'SSL')
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_payment'),
				'href' => $this->url->link('extension/payment', 'token=' . $this->session->data['user_token'], 'SSL')
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/payment/paycomet', 'token=' . $this->session->data['user_token'], 'SSL')
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_refund'),
				'href' => $this->url->link('extension/payment/paycomet/refund', 'order_id=' . $this->request->get['order_id'] . '&token=' . $this->session->data['user_token'], 'SSL')
			);

			$data['transaction_reference'] = $transaction['order_id'];
			$data['transaction_amount'] = number_format($transaction['amount'], 2);
			$data['cancel'] = $this->url->link('sale/order/info', 'token=' . $this->session->data['user_token'] . '&order_id=' . $transaction['order_id'], 'SSL');

			$data['user_token'] = $this->session->data['user_token'];

			$data['header'] = $this->load->controller('common/header');
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['footer'] = $this->load->controller('common/footer');

			return $this->load->view('extension/payment/paycomet_refund', $data);
		} else {
			return $this->forward('error/not_found');
		}
	}

	public function doRefund(){
		$this->load->model('extension/payment/paycomet');
		$this->load->language('extension/payment/paycomet');
		$this->load->model('sale/order');

		$json = array();

		if (isset($this->request->post['order_id']) && isset($this->request->post['amount'])) {

			$order_id = $this->request->post['order_id'];
			$totalRefund = $this->request->post['totalRefund'];
			
			$order_info = $this->model_sale_order->getOrder($order_id);
			$paycomet_order_info = $this->model_extension_payment_paycomet->getOrderInfo($order_id);

			if ($order_info) {

				$total_refunded = 0;
				$results = $this->model_extension_payment_paycomet->getPaycometOrderRefund($order_id);
				if ($results){
					
					foreach ($results as $result) {
						$total_refunded += $result['amount'];
					}
				}

				// Partial Refund
				if ($totalRefund==0)
					$amount = $this->request->post['amount'];
				// Total Refund
				else{
					$amount = $paycomet_order_info["amount"] - $total_refunded;
				}

				if ($amount>0){
	    			$currency_iso_code = $order_info['currency_code'];

			        $DS_IDUSER = $paycomet_order_info["paycomet_iduser"];
			        $DS_TOKEN_USER = $paycomet_order_info["paycomet_tokenuser"];
			        $DS_MERCHANT_ORDER = trim($order_id);
			        $DS_MERCHANT_CURRENCY = $currency_iso_code;
			        $DS_MERCHANT_TERMINAL = $this->config->get('payment_paycomet_terminal');
			        $DS_MERCHANT_AUTHCODE = $paycomet_order_info["authcode"];			        
			        $DS_ORIGINAL_IP = $this->request->server['REMOTE_ADDR'];
					$DS_MERCHANT_AMOUNT = round($amount * 100);

					if($this->config->get('payment_paycomet_apikey') != '')
					{
						include_once(DIR_SYSTEM . 'library/paycomet/paycomet.php');
			
						$apiRest = new Paycomet($this->config->get('payment_paycomet_apikey'));

						$executeRefundReponse = $apiRest->executeRefund(
							$DS_MERCHANT_ORDER,
							$DS_MERCHANT_TERMINAL,
							$DS_MERCHANT_AMOUNT,
							$DS_MERCHANT_CURRENCY,
							$DS_MERCHANT_AUTHCODE,
							$DS_ORIGINAL_IP
						);

						$result["DS_RESPONSE"] = ($executeRefundReponse->errorCode > 0) ? 0 : 1;
						$result["DS_ERROR_ID"] = $executeRefundReponse->errorCode;
						if ($executeRefundReponse->errorCode == 0) {
							$result['DS_MERCHANT_AUTHCODE'] = $executeRefundReponse->authCode;
						}
					}
					
					if ($result[ 'DS_RESPONSE'] == 1){
						$json['success'] = $this->language->get('text_refund_issued');
						$total_refunded += $amount;

						$json['total_refunded'] = number_format($total_refunded,2,".","");

						$data = array(
							'order_id' => $order_id,
							'authcode' => $result['DS_MERCHANT_AUTHCODE'],
							'result' => $result[ 'DS_RESPONSE'],
							'amount' => $amount,
						);

						$this->model_extension_payment_paycomet->addRefund($data);
					} else {
						$json['error'] = "Error (".$result['DS_ERROR_ID'].")";
					}
				}else{
					$json['error'] = $this->language->get('error_min_amount_refund');
				}
			} else {
				$json['error'] = $this->language->get('error_missing_order');
			}
		} else {
			$json['error'] = $this->language->get('error_missing_data');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function orderAction() {
		if ($this->config->get('payment_paycomet_status')) {
			
			if (isset($this->request->get['order_id'])) {
				$order_id = $this->request->get['order_id'];
			} else {
				$order_id = 0;
			}

			$this->load->model('extension/payment/paycomet');

			$paycomet_info = $this->model_extension_payment_paycomet->getPaycometOrder($order_id);

			if ($paycomet_info) {

				$data['user_token'] = $this->session->data['user_token'];

				$data['order_id'] = $this->request->get['order_id'];

				$data['capture_status'] = ($paycomet_info['result'])?"OK":"KO";

				$data['total'] = $paycomet_info['amount'];

				$captured = number_format($this->model_extension_payment_paycomet->totalCaptured($this->request->get['order_id']), 2);

				$data['captured'] = $captured;

				$data['capture_remaining'] = number_format($paycomet_info['amount'] - $captured, 2);

				$refunded = number_format($this->model_extension_payment_paycomet->totalRefundedOrder($this->request->get['order_id']), 2);

				$data['refunded'] = $refunded;

				return $this->load->view('extension/payment/paycomet_order', $data);

			}
		}
	}	
}

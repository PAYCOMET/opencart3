<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);

class ControllerExtensionCreditCardPaycomet extends Controller {
	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/account', '', true);

			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('extension/credit_card/paycomet');

		$this->load->model('extension/payment/paycomet');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home'),
			'separator' => $this->language->get('text_separator')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true),
			'separator' => $this->language->get('text_separator')
		);


		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		} else {
			$data['error_warning'] = '';
		}
		
		$data['cards'] = $this->model_extension_payment_paycomet->getCards($this->customer->getId());
		$data['delete'] = $this->url->link('extension/credit_card/paycomet/delete', 'card_id=', true);

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		$cards_total = count($data['cards']);

		$pagination = new Pagination();
		$pagination->total = $cards_total;
		$pagination->page = $page;
		$pagination->limit = 10;
		$pagination->url = $this->url->link('extension/credit_card/paycomet', 'page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($cards_total) ? (($page - 1) * 10) + 1 : 0, ((($page - 1) * 10) > ($cards_total - 10)) ? $cards_total : ((($page - 1) * 10) + 10), $cards_total, ceil($cards_total / 10));
		
		$data['back'] = $this->url->link('account/account', '', true);
		$data['add'] = $this->url->link('extension/credit_card/paycomet/add', '', true);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('extension/credit_card/paycomet_list', $data));

		// Add Agree
	}

	public function add() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/account', '', true);

			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('extension/credit_card/paycomet');

		$this->load->model('extension/payment/paycomet');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);

		$data['back'] = $this->url->link('extension/credit_card/paycomet', '', true);
		$data['add'] = $this->url->link('extension/credit_card/paycomet/addCard', '', true);

		try {
			$data['txt_url_paycomet'] = $this->paycomet_iframe_URL();
		} catch (Exception $e) {
			$data['error'] = $e->getMessage();
		}		

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('extension/credit_card/paycomet_form', $data));
	}


	public function paycomet_iframe_URL(){
		$this->load->model('extension/payment/paycomet');

		$paycomet_apikey  = $this->config->get('payment_paycomet_apikey');		
    	$paycomet_terminal  = $this->config->get('payment_paycomet_terminal');    	

		$language = $this->language->get('code');

		$URLKO = $this->url->link('extension/credit_card/paycomet', '', true);
		$URLOK = $this->url->link('extension/credit_card/paycomet', '', true);

		$paycomet_order_ref = $this->customer->getId();		
	
		$operation = 107;

		if ($paycomet_apikey != '') {
			include_once(DIR_SYSTEM . 'library/paycomet/paycomet.php');

			$apiRest = new Paycomet($paycomet_apikey);		
			$payment =  [
				'terminal' => (int) $paycomet_terminal,
				'order' => (string) $paycomet_order_ref,				
				'urlOk' => $URLOK,
				'urlKo' => $URLKO
			];

			$formResponse = $apiRest->form(
				$operation,
				substr($language, 0, 2),
				$paycomet_terminal,
				'',
				$payment
			);

			if(isset($formResponse->challengeUrl)) {
				$url_paycomet = $formResponse->challengeUrl;
			} else {
				throw new Exception("Se ha producido un error: " . $formResponse->errorCode);				
			}

		}

		return $url_paycomet;
	}

	public function delete() {
		$this->load->language('extension/credit_card/paycomet');
		$this->load->model('extension/payment/paycomet');
		
		$card_id = $this->encryption->decrypt($this->config->get('config_encryption'), $this->request->get['card_id']);
		$card = $this->model_extension_payment_paycomet->getCard($this->customer->getId(), $card_id);

		if (!empty($card['paycomet_iduser'])) {
			$this->model_extension_payment_paycomet->deleteCard($this->customer->getId(), $card_id);
			$this->session->data['success'] = $this->language->get('text_success_card');
		} else {
			$this->session->data['error_warning'] = $this->language->get('text_fail_card');
		}
		$this->response->redirect($this->url->link('extension/credit_card/paycomet', '', true));
	}

	
}

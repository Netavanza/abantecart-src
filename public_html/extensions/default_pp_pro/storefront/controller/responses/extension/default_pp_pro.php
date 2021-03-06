<?php
/*------------------------------------------------------------------------------
  $Id$

  AbanteCart, Ideal OpenSource Ecommerce Solution
  http://www.AbanteCart.com

  Copyright © 2011-2013 Belavier Commerce LLC

  This source file is subject to Open Software License (OSL 3.0)
  Lincence details is bundled with this package in the file LICENSE.txt.
  It is also available at this URL:
  <http://www.opensource.org/licenses/OSL-3.0>

 UPGRADE NOTE:
   Do not edit or add to this file if you wish to upgrade AbanteCart to newer
   versions in the future. If you wish to customize AbanteCart for your
   needs please refer to http://www.AbanteCart.com for more information.
------------------------------------------------------------------------------*/
if ( !defined ( 'DIR_CORE' )) {
	header ( 'Location: static_pages/' );
}

class ControllerResponsesExtensionDefaultPPPro extends AController {
	public function main() {
    	$this->loadLanguage('default_pp_pro/default_pp_pro');
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		$data[ 'cc_owner' ] = HtmlElementFactory::create(array(
			'type' => 'input',
			'name' => 'cc_owner',
			'value' => $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'],
			'style' => 'input-medium'
		));

		//load accepted card types
		$cards = array();
		foreach ( unserialize($this->config->get('default_pp_pro_creditcard_types')) as $card) {
			if ($card) {
				$cards[$card] = $card;
			}
		}	
		$data[ 'accepted_cards' ] = $cards;

        $data[ 'cc_type' ] = HtmlElementFactory::create(
			array( 'type' => 'selectbox',
			     'name' => 'cc_type',
			     'value' => '',
			     'options' => $cards,
			     'style' => 'short input-small'
			));

        $data[ 'cc_number' ] = HtmlElementFactory::create(array(
			'type' => 'input',
			'name' => 'cc_number',
			'value' => '',
			'style' => 'input-medium'
		));

		$months = array();
		for ($i = 1; $i <= 12; $i++) {
			$months[ sprintf('%02d', $i) ] = strftime('%B', mktime(0, 0, 0, $i, 1, 2000));
		}
		$data[ 'cc_expire_date_month' ] = HtmlElementFactory::create(
			array( 'type' => 'selectbox',
			     'name' => 'cc_expire_date_month',
			     'value' => sprintf('%02d', date('m')),
			     'options' => $months,
			     'style' => 'short input-small'
			));

        $today = getdate();
		$years = array();
		for ($i = $today[ 'year' ]; $i < $today[ 'year' ] + 11; $i++) {
			$years[ strftime('%Y', mktime(0, 0, 0, 1, 1, $i)) ] = strftime('%Y', mktime(0, 0, 0, 1, 1, $i));
		}
		$data[ 'cc_expire_date_year' ] = HtmlElementFactory::create(array( 'type' => 'selectbox',
		                                                                 'name' => 'cc_expire_date_year',
		                                                                 'value' => sprintf('%02d', date('Y') + 1),
		                                                                 'options' => $years,
		                                                                 'style' => 'short input-small' ));
        $data[ 'cc_start_date_month' ] = HtmlElementFactory::create(
			array( 'type' => 'selectbox',
			     'name' => 'cc_start_date_month',
			     'value' => sprintf('%02d', date('m')),
			     'options' => $months,
			     'style' => 'short input-small'
			));

		$years = array();
		for ($i = $today[ 'year' ]-10; $i < $today[ 'year' ] + 2; $i++) {
			$years[ strftime('%Y', mktime(0, 0, 0, 1, 1, $i)) ] = strftime('%Y', mktime(0, 0, 0, 1, 1, $i));
		}
        $data[ 'cc_start_date_year' ] = HtmlElementFactory::create(array( 'type' => 'selectbox',
		                                                                 'name' => 'cc_start_date_year',
		                                                                 'value' => sprintf('%02d', date('Y') ),
		                                                                 'options' => $years,
		                                                                 'style' => 'short input-small' ));

        $data[ 'cc_cvv2' ] = HtmlElementFactory::create(array( 'type' => 'input',
		                                                     'name' => 'cc_cvv2',
		                                                     'value' => '',
		                                                     'style' => 'short',
		                                                     'attr' => ' size="3" maxlength="4" '
		                                                ));
        $data[ 'cc_issue' ] = HtmlElementFactory::create(array( 'type' => 'input',
		                                                     'name' => 'cc_issue',
		                                                     'value' => '',
		                                                     'style' => 'short',
		                                                     'attr' => ' size="1" maxlength="2" '
		                                                ));

		$back = $this->request->get[ 'rt' ] != 'checkout/guest_step_3' ? $this->html->getSecureURL('checkout/payment')
				: $this->html->getSecureURL('checkout/guest_step_2');
				
		$data[ 'back' ] = HtmlElementFactory::create(array( 'type' => 'button',
		                                                  'name' => 'back',
		                                                  'text' => $this->language->get('button_back'),
		                                                  'style' => 'button',
		                                                  'href' => $back ));

		$data[ 'submit' ] = HtmlElementFactory::create(array( 'type' => 'button',
			                                                  'name' => 'paypal_button',
		                                                      'text' => $this->language->get('button_confirm'),
			                                                  'style' => 'button btn-orange',
		                                               ));

		$this->view->batchAssign( $data );
		$this->processTemplate('responses/default_pp_pro.tpl' );
	}

	public function send() {
		if (!$this->config->get('default_pp_pro_test')) {
			$api_endpoint = 'https://api-3t.paypal.com/nvp';
		} else {
			$api_endpoint = 'https://api-3t.sandbox.paypal.com/nvp';
		}
		
		if (!$this->config->get('default_pp_pro_transaction')) {
			$payment_type = 'Authorization';	
		} else {
			$payment_type = 'Sale';
		}
		
		$this->load->model('checkout/order');
		
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		$payment_data = array(
			'METHOD'         => 'DoDirectPayment', 
			'VERSION'        => '51.0', 
			'USER'           => html_entity_decode($this->config->get('default_pp_pro_username'), ENT_QUOTES, 'UTF-8'),
			'PWD'            => html_entity_decode($this->config->get('default_pp_pro_password'), ENT_QUOTES, 'UTF-8'),
			'SIGNATURE'      => html_entity_decode($this->config->get('default_pp_pro_signature'), ENT_QUOTES, 'UTF-8'),
			'CUSTREF'        => $order_info['order_id'],
			'PAYMENTACTION'  => $payment_type,
			'AMT'            => $this->currency->format($order_info['total'], $order_info['currency'], $order_info['value'], FALSE),
			'CREDITCARDTYPE' => $this->request->post['cc_type'],
			'ACCT'           => str_replace(' ', '', $this->request->post['cc_number']),
			'CARDSTART'      => $this->request->post['cc_start_date_month'] . $this->request->post['cc_start_date_year'],
			'EXPDATE'        => $this->request->post['cc_expire_date_month'] . $this->request->post['cc_expire_date_year'],
			'CVV2'           => $this->request->post['cc_cvv2'],
			'CARDISSUE'      => $this->request->post['cc_issue'],
			'FIRSTNAME'      => $order_info['payment_firstname'],
			'LASTNAME'       => $order_info['payment_lastname'],
			'EMAIL'          => $order_info['email'],
			'PHONENUM'       => $order_info['telephone'],
			'IPADDRESS'      => $this->request->server['REMOTE_ADDR'],
			'STREET'         => $order_info['payment_address_1'],
			'CITY'           => $order_info['payment_city'],
			'STATE'          => ($order_info['payment_iso_code_2'] != 'US') ? $order_info['payment_zone'] : $order_info['payment_zone_code'],
			'ZIP'            => $order_info['payment_postcode'],
			'COUNTRYCODE'    => $order_info['payment_iso_code_2'],
			'CURRENCYCODE'   => $order_info['currency'],
			'BUTTONSOURCE'   => 'Abante_Cart',
		);

		$curl = curl_init($api_endpoint);
		
		curl_setopt($curl, CURLOPT_PORT, 443);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($payment_data));

		$response = curl_exec($curl);
 		
		curl_close($curl);
 
		if (!$response) {
			exit('DoDirectPayment failed: ' . curl_error($curl) . '(' . curl_errno($curl) . ')');
		}
 
 		$response_data = array();

		parse_str($response, $response_data);
		$json = array();
		if (($response_data['ACK'] == 'Success') || ($response_data['ACK'] == 'SuccessWithWarning')) {
			$this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('config_order_status_id'));
			
			$message = '';
			
			if (isset($response_data['AVSCODE'])) {
				$message .= 'AVSCODE: ' . $response_data['AVSCODE'] . "\n";
			}

			if (isset($response_data['CVV2MATCH'])) {
				$message .= 'CVV2MATCH: ' . $response_data['CVV2MATCH'] . "\n";
			}

			if (isset($response_data['TRANSACTIONID'])) {
				$message .= 'TRANSACTIONID: ' . $response_data['TRANSACTIONID'] . "\n";
			}

			$response_data['PAYMENTACTION'] = $payment_type;
			$response_data['payment_method'] = 'default_pp_pro';

			$this->model_checkout_order->updatePaymentMethodData($this->session->data['order_id'], serialize($response_data));
			$this->model_checkout_order->update($this->session->data['order_id'], $this->config->get('default_pp_pro_order_status_id'), $message, FALSE);
		
			$json['success'] = $this->html->getSecureURL('checkout/success');
		} else {
        	$json['error'] = $response_data['L_LONGMESSAGE0'];
        }


		
		$this->load->library('json');
		$this->response->setOutput(AJson::encode($json));
	}
	//TODO: needs to write method that receive IPN-notifications from paypal about changes of status of order
	public function callback(){

	}
}
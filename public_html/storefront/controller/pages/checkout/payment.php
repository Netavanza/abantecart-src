<?php
/*------------------------------------------------------------------------------
  $Id$

  AbanteCart, Ideal OpenSource Ecommerce Solution
  http://www.AbanteCart.com

  Copyright © 2011 Belavier Commerce LLC

  This source file is subject to Open Software License (OSL 3.0)
  License details is bundled with this package in the file LICENSE.txt.
  It is also available at this URL:
  <http://www.opensource.org/licenses/OSL-3.0>

 UPGRADE NOTE:
   Do not edit or add to this file if you wish to upgrade AbanteCart to newer
   versions in the future. If you wish to customize AbanteCart for your
   needs please refer to http://www.AbanteCart.com for more information.
------------------------------------------------------------------------------*/
if (!defined('DIR_CORE')) {
	header('Location: static_pages/');
}
class ControllerPagesCheckoutPayment extends AController {
	private $error = array();
	public $data = array();

	public function main() {

		//init controller data
		$this->extensions->hk_InitData($this, __FUNCTION__);

		if (($this->request->server[ 'REQUEST_METHOD' ] == 'POST') && isset($this->request->post[ 'coupon' ]) && $this->_validateCoupon()) {
			$this->session->data[ 'coupon' ] = $this->request->post[ 'coupon' ];
			$this->session->data[ 'success' ] = $this->language->get('text_success');

			//process data
			$this->extensions->hk_ProcessData($this);

			$this->redirect($this->html->getSecureURL('checkout/payment'));
		}

		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$this->redirect($this->html->getSecureURL('checkout/cart'));
		}

		if (!$this->customer->isLogged()) {
			$this->session->data[ 'redirect' ] = $this->html->getSecureURL('checkout/shipping');

			$this->redirect($this->html->getSecureURL('account/login'));
		}

		$this->loadModel('account/address');

		if ($this->cart->hasShipping()) {
			if (!isset($this->session->data[ 'shipping_address_id' ]) || !$this->session->data[ 'shipping_address_id' ]) {
				$this->redirect($this->html->getSecureURL('checkout/shipping'));
			}

			if (!isset($this->session->data[ 'shipping_method' ])) {
				$this->redirect($this->html->getSecureURL('checkout/shipping'));
			}
		} else {
			unset($this->session->data[ 'shipping_address_id' ]);
			unset($this->session->data[ 'shipping_method' ]);
			unset($this->session->data[ 'shipping_methods' ]);

			//$this->tax->setZone($this->config->get('config_country_id'), $this->config->get('config_zone_id'));
			$this->tax->setZone($this->session->data[ 'country_id' ], $this->session->data[ 'zone_id' ]);
		}

		if (!isset($this->session->data[ 'payment_address_id' ]) && isset($this->session->data[ 'shipping_address_id' ]) && $this->session->data[ 'shipping_address_id' ]) {
			$this->session->data[ 'payment_address_id' ] = $this->session->data[ 'shipping_address_id' ];
		}

		if (!isset($this->session->data[ 'payment_address_id' ])) {
			$this->session->data[ 'payment_address_id' ] = $this->customer->getAddressId();
		}

		if (!$this->session->data[ 'payment_address_id' ]) {
			$this->redirect($this->html->getSecureURL('checkout/address/payment'));
		}

		$total_data = array();
		$total = 0;
		$taxes = $this->cart->getTaxes();

		$this->loadModel('checkout/extension');

		$results = $this->model_checkout_extension->getExtensions('total');
		foreach ($results as $result) {
			if($result['key']=='total'){
				// apply promotions
				$promotions = new APromotion();
				$promotions->apply_promotions($total_data,$total);
				if(time()-$this->session->data['promotion_data']['time']<1){
					$total_data = $this->session->data['promotion_data']['total_data'];
					$total = $this->session->data['promotion_data']['total'];
				}else{
					unset($this->session->data['promotion_data']);
				}
			}
			$this->loadModel('total/' . $result[ 'key' ]);
			$this->{'model_total_' . $result[ 'key' ]}->getTotal($total_data, $total, $taxes);
		}

		$this->loadModel('account/address');
		$payment_address = $this->model_account_address->getAddress($this->session->data[ 'payment_address_id' ]);
		if (!$payment_address) {
			$this->redirect($this->html->getSecureURL('checkout/address/payment'));
		}
		if (!$this->cart->hasShipping() || $this->config->get('config_tax_customer')) {
			$this->tax->setZone($payment_address[ 'country_id' ], $payment_address[ 'zone_id' ]);
		}

		$this->loadModel('checkout/extension');
		$method_data = array();
		$results = $this->model_checkout_extension->getExtensions('payment');

		foreach ($results as $result) {
			$this->loadModel('extension/' . $result[ 'key' ]);
			$method = $this->{'model_extension_' . $result[ 'key' ]}->getMethod($payment_address);
			if ($method) {
				$method_data[ $result[ 'key' ] ] = $method;
			}
		}

		$this->session->data[ 'payment_methods' ] = $method_data;

		if (($this->request->server[ 'REQUEST_METHOD' ] == 'POST') && !isset($this->request->post[ 'coupon' ]) && $this->_validate()) {
			$this->session->data[ 'payment_method' ] = $this->session->data[ 'payment_methods' ][ $this->request->post[ 'payment_method' ] ];
			$this->session->data[ 'comment' ] = strip_tags($this->request->post[ 'comment' ]);

			//process data
			$this->extensions->hk_ProcessData($this);

			$this->redirect($this->html->getSecureURL('checkout/confirm'));
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$this->document->resetBreadcrumbs();

		$this->document->addBreadcrumb(array(
		                                    'href' => $this->html->getURL('index/home'),
		                                    'text' => $this->language->get('text_home'),
		                                    'separator' => FALSE
		                               ));

		$this->document->addBreadcrumb(array(
		                                    'href' => $this->html->getURL('checkout/cart'),
		                                    'text' => $this->language->get('text_basket'),
		                                    'separator' => $this->language->get('text_separator')
		                               ));

		$this->document->addBreadcrumb(array(
		                                    'href' => $this->html->getURL('checkout/shipping'),
		                                    'text' => $this->language->get('text_shipping'),
		                                    'separator' => $this->language->get('text_separator')
		                               ));

		$this->document->addBreadcrumb(array(
		                                    'href' => $this->html->getURL('checkout/payment'),
		                                    'text' => $this->language->get('text_payment'),
		                                    'separator' => $this->language->get('text_separator')
		                               ));

		if (isset($this->session->data[ 'error' ])) {
			$this->view->assign('error_warning', $this->session->data[ 'error' ]);
			unset($this->session->data[ 'error' ]);
		} else {
			$this->view->assign('error_warning', $this->error[ 'warning' ]);
		}

		$this->view->assign('success', $this->session->data[ 'success' ]);
		if (isset($this->session->data[ 'success' ])) {
			unset($this->session->data[ 'success' ]);
		}
		$action = $this->html->getSecureURL('checkout/payment');
		$this->view->assign( 'coupon_status', $this->config->get('coupon_status') );

		$item = HtmlElementFactory::create( array('type' => 'button',
			                                      'name' => 'change_address',
			                                      'style' => 'button',
		                                          'text' => $this->language->get('button_change_address')
		                                    ));
		$this->data['change_address'] = $item->getHTML();
		$this->data['change_address_href'] = $this->html->getSecureURL('checkout/address/payment');

		$form = new AForm();
		$form->setForm(array( 'form_name' => 'coupon' ));
		$this->data['form0'][ 'form_open' ] = $form->getFieldHtml(
                                                                array( 'type' => 'form',
                                                                       'name' => 'coupon',
                                                                       'action' => $action ));
		$this->data['form0'][ 'coupon' ] = $form->getFieldHtml( array(
                                                                       'type' => 'input',
		                                                               'name' => 'coupon',
		                                                               'value' => ( isset($this->request->post[ 'coupon' ]) ? $this->request->post[ 'coupon' ] : $this->session->data[ 'coupon' ] )
		                                                       ));
		$this->data['form0'][ 'submit' ] = $form->getFieldHtml( array(
                                                                       'type' => 'submit',
		                                                               'name' => $this->language->get('button_coupon') ));

		$this->data[ 'address' ] = $this->customer->getFormatedAdress($payment_address, $payment_address[ 'address_format' ] );

		$form = new AForm();
		$form->setForm(array( 'form_name' => 'payment' ));
		$this->data['form'][ 'form_open' ] = $form->getFieldHtml(
                                                                array( 'type' => 'form',
                                                                       'name' => 'payment',
                                                                       'action' => $action ));

		$this->data['payment_methods'] = $this->session->data[ 'payment_methods' ];
		$payment = isset($this->request->post[ 'payment_method' ]) ? $this->request->post[ 'payment_method' ] : $this->session->data[ 'payment_method' ][ 'id' ];

		if($this->data['payment_methods']){
			foreach($this->data['payment_methods'] as $k=>$v){
				//check if we have only one method and select by default if was selected before
				$selected = FALSE;
				if ( count($this->data['payment_methods']) == 1) {
					$selected = TRUE;
				} else if( $payment == $v['id'] )  {
					$selected = TRUE;
				}	
			
				$this->data['payment_methods'][$k]['radio'] = $form->getFieldHtml( array(
					                                                                   'type' => 'radio',
					                                                                   'name' => 'payment_method',
					                                                                   'options' => array($v['id']=>''),
					                                                                   'value' => $selected
				                                                                  ));
			}
		}else{
			$this->data['payment_methods'] = array();
		}

		$this->data['comment'] = isset($this->request->post[ 'comment' ]) ? $this->request->post[ 'comment' ] : $this->session->data[ 'comment' ];
		$this->data['form']['comment'] =  $form->getFieldHtml( array(
																	'type' => 'textarea',
																	'name' => 'comment',
																	'value' => $this->data['comment'],
																	'attr' => ' rows="8" style="width: 99%" ' ));

		if ($this->config->get('config_checkout_id')) {
			$this->loadModel('catalog/content');
			$content_info = $this->model_catalog_content->getContent($this->config->get('config_checkout_id'));
			if ($content_info) {
				$this->data['text_agree'] = $this->language->get('text_agree');
				$this->data['text_agree_href'] = $this->html->getURL('r/content/content/loadInfo', '&content_id=' . $this->config->get('config_checkout_id'));
				$this->data['text_agree_href_text'] = $content_info[ 'title' ];
			} else {
				$this->data['text_agree'] = '';
			}
		} else {
			$this->data['text_agree'] = '';
		}

		if($this->data['text_agree']){
			$this->data['form']['agree'] = $form->getFieldHtml( array(
					                                                  'type' => 'checkbox',
					                                                  'name' => 'agree',
				                                                      'value' => '1',
					                                                  'checked' => ( $this->request->post[ 'agree' ] ? TRUE : FALSE )
				                                                      ));
		}

		$this->data['agree'] = $this->request->post[ 'agree' ];
		$this->data['back'] = $this->cart->hasShipping() ? $this->html->getSecureURL('checkout/shipping') : $this->data['back'] = $this->html->getSecureURL('checkout/cart');

		$this->data['form'][ 'back' ] = $form->getFieldHtml( array( 'type' => 'button',
		                                                            'name' => 'back',
			                                                        'style' => 'button',
		                                                            'text' => $this->language->get('button_back') ));
		$this->data['form'][ 'continue' ] = $form->getFieldHtml( array(
                                                                       'type' => 'submit',
		                                                               'name' => $this->language->get('button_continue') ));


		//render buttons
		$this->view->batchAssign($this->data);
		$this->view->assign('buttons', $this->view->fetch('pages/checkout/payment.buttons.tpl'));

		$this->processTemplate('pages/checkout/payment.tpl');

		//update data before render
		$this->extensions->hk_UpdateData($this, __FUNCTION__);

	}

	private function _validate() {

		if (!isset($this->request->post[ 'payment_method' ])) {
			$this->error[ 'warning' ] = $this->language->get('error_payment');
		} else {
			if (!isset($this->session->data[ 'payment_methods' ][ $this->request->post[ 'payment_method' ] ])) {
				$this->error[ 'warning' ] = $this->language->get('error_payment');
			}
		}

		if ($this->config->get('config_checkout_id')) {
			$this->loadModel('catalog/content');

			$content_info = $this->model_catalog_content->getContent($this->config->get('config_checkout_id'));

			if ($content_info) {
				if (!isset($this->request->post[ 'agree' ])) {
					$this->error[ 'warning' ] = sprintf($this->language->get('error_agree'), $content_info[ 'title' ]);
				}
			}
		}

		//validate post data
		$this->extensions->hk_ValidateData($this);

		if (!$this->error) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	private function _validateCoupon() {
		$promotion = new APromotion();
		$coupon = $promotion->getCouponData($this->request->post[ 'coupon' ]);
		if (!$coupon) {
			$this->error[ 'warning' ] = $this->language->get('error_coupon');
		}

		if (!$this->error) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
}

?>
<?php
/**
 * OpenID strategy for Opauth
 *
 * Implemented with Mewp's LightOpenID Library,
 *   included at Vendor/lightopenid
 *   (https://gitorious.org/lightopenid/lightopenid)
 *
 * More information on Opauth: http://opauth.org
 *
 * @copyright    Copyright © 2012 U-Zyn Chua (http://uzyn.com)
 * @link         http://opauth.org
 * @license      MIT License
 */

namespace Opauth\Strategy\OpenID;

use Opauth\AbstractStrategy;

/**
 * OpenID strategy for Opauth
 *
 */
class Strategy extends AbstractStrategy {

	/**
	 * Compulsory config keys, listed as unassociative arrays
	 */
	public $expects = array();

	/**
	 * Optional config keys with respective default values, listed as associative arrays
	 * eg. array('scope' => 'email');
	 */
	public $defaults = array(
		// Refer to http://openid.net/specs/openid-attribute-properties-list-1_0-01.html if
		// you wish to overwrite these
		'required' => array(
			'contact/email',
			'namePerson',
			'namePerson/first',
			'namePerson/last',
			'namePerson/friendly'
		),
		'optional' => array(
			'contact/phone',
			'contact/web',
			'media/image'
		),
		'identifier_form' => 'identifier_request.html',
	);

	protected $responseMap = array(
		'name' => 'namePerson/first',
		'info.first_name' => 'namePerson/first',
		'info.last_name' => 'namePerson/last',
		'info.email' => 'contact/email',
	);

	/**
	 * Ask for OpenID identifer
	 */
	public function request() {
		$this->loadOpenid();
		if ($this->openid->mode) {
			$error = array(
				'code' => 'bad_mode',
				'message' => 'Callback url is not set'
			);
			return $this->response($this->openid->getAttributes(), $error);
		}

		if (empty($this->strategy['openid_url']) && empty($_POST['openid_url'])) {
			$this->render($this->strategy['identifier_form']);
		}
		if ($_POST['openid_url']) {
			$this->strategy['openid_url'] = $_POST['openid_url'];
		}
		$this->openid->identity = $this->strategy['openid_url'];

		try{
			$url = $this->openid->authUrl();
		} catch (\ErrorException $e) {
			$error = array(
				'code' => 'bad_identifier',
				'message' => $e->getMessage()
			);

			return $this->response($this->openid->data, $error);
		}

		$this->http->redirect($url);
	}

	/**
	 * Render a view
	 */
	protected function render($view, $exit = true){
		require($view);
		if ($exit) exit();
	}

	public function callback() {
		$this->loadOpenid();

		if ($this->openid->mode == 'cancel') {
			$error = array(
				'code' => 'cancel_authentication',
				'message' => 'User has canceled authentication'
			);

			return $this->response($this->openid->data, $error);
		}

		if (!$this->openid->validate()) {
			$error = array(
				'provider' => 'OpenID',
				'code' => 'not_logged_in',
				'message' => 'User has not logged in'
			);

			return $this->response($this->openid->data, $error);
		}

		$attributes = $this->openid->getAttributes();

		$response = $this->response($attributes);
		$response->credentials = array(
			'identity' => $this->openid->identity,
		);
		$response->uid = $this->openid->identity;
		$response->setMap($this->responseMap);
		return $response;
	}

	protected function loadOpenid() {
		require dirname(__FILE__) . '/Vendor/lightopenid/openid.php';
		$url = $this->callbackUrl();
		$this->openid = new \LightOpenID($url);
		$this->openid->returnUrl = $url;
		$this->openid->required = $this->strategy['required'];
		$this->openid->optional = $this->strategy['optional'];
	}

}

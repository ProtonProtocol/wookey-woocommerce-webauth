<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}
final class XPRCheckout_BlocksSupport extends AbstractPaymentMethodType {
	
	private $gateway;
	
	protected $name = 'xprcheckout'; // payment gateway id

	public function initialize() {
		// get payment gateway settings
		$this->settings = get_option( "woocommerce_{$this->name}_settings", array() );
		
		// you can also initialize your payment gateway here
		// $gateways = WC()->payment_gateways->payment_gateways();
		// $this->gateway  = $gateways[ $this->name ];
	}

	public function is_active() {
		return ! empty( $this->settings[ 'enabled' ] ) && 'yes' === $this->settings[ 'enabled' ];
	}

	public function get_payment_method_script_handles() {
    
		wp_register_script(
			'wc-xprcheckout-blocks',
			XPRCHECKOUT_ROOT_URL . 'dist/block/build/app.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
			),
			time(),
			true
			
		);

		return array( 'wc-xprcheckout-blocks' );

	}

	public function get_payment_method_data() {
		return array(
			'title'        => $this->get_setting( 'title' ),
			// almost the same way:
			// 'title'     => isset( $this->settings[ 'title' ] ) ? $this->settings[ 'title' ] : 'Default value';
			'description'  => $this->get_setting( 'description' ),
			// if $this->gateway was initialized on line 15
			// 'supports'  => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),

			// example of getting a public key
			// 'publicKey' => $this->get_publishable_key(),
		);
	}

	//private function get_publishable_key() {
	//	$test_mode   = ( ! empty( $this->settings[ 'testmode' ] ) && 'yes' === $this->settings[ 'testmode' ] );
	//	$setting_key = $test_mode ? 'test_publishable_key' : 'publishable_key';
	//	return ! empty( $this->settings[ $setting_key ] ) ? $this->settings[ $setting_key ] : '';
	//}

}
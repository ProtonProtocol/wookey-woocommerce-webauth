<?php

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Cash on Delivery Gateway.
 *
 * Provides a Webauth Payment Gateway for your customer.
 *
 * @class       WC_Woow_Gateway
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce\Classes\Payment
 */

add_filter('woocommerce_payment_gateways', 'woow_add_gateway_class');
function woow_add_gateway_class($gateways)
{

  $gateways[] = 'WC_Woow_Gateway';
  return $gateways;
}

add_action('plugins_loaded', 'woow_init_gateway_class');
function woow_init_gateway_class()
{
  class WC_Woow_Gateway extends WC_Payment_Gateway
  {

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
      // Setup general properties.
      $this->setup_properties();

      // Load the settings.
      $this->init_form_fields();
      $this->init_settings();

      // Get settings.
      $this->title = $this->get_option('title');
      $this->description = $this->get_option('description');
      $this->mainwallet = $this->get_option('mainwallet');
      $this->testwallet = $this->get_option('testwallet');
      $this->testnet = 'yes' === $this->get_option('testnet');
      $this->enabled = $this->get_option('enabled');
      $this->appName = $this->get_option('appName');
      $this->appLogo = $this->get_option('appLogo');
      $this->allowedTokens = $this->get_option('allowedTokens');

      // Actions.
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
      add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
      add_filter('woocommerce_payment_complete_order_status', array($this, 'change_payment_complete_order_status'), 10, 3);

      // Customer Emails.
      add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
    }

    /**
     * Setup general properties for the gateway.
     */
    protected function setup_properties()
    {
      $this->id                 = 'woow';
      $this->icon               = apply_filters('woocommerce_cod_icon', '');
      $this->method_title       = __('Webauth for woocommerce', 'woow');
      $this->method_description = __('Provides a Webauth Payment Gateway for your customer.', 'woow');
      $this->has_fields         = false;
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
      $this->form_fields = array(
        'enabled' => array(
          'title' => __('Enable/Disable', 'woow'),
          'type' => 'checkbox',
          'label' => __('Enable Webauth Payment', 'woow'),
          'default' => 'yes'
        ),
        'testnet' => array(
          'title' => __('Use testnet', 'woow'),
          'type' => 'checkbox',
          'label' => __('Enable testnet', 'woow'),
          'default' => 'yes'
        ),
        'title' => array(
          'title' => __('Title', 'woow'),
          'type' => 'text',
          'description' => __('This controls the title which the user sees during checkout.', 'woow'),
          'default' => __('WebAuth payment', 'woow'),
          'desc_tip'      => true,
        ),
        'description' => array(
          'title' => __('Title', 'woow'),
          'type' => 'text',
          'description' => __('This controls the title which the user sees during checkout.', 'woow'),
          'default' => __('pay securly with with multiple crypto currencies through Webauth with NO GAS FEE BABY !', 'woow'),
          'desc_tip'      => true,
        ),
        'mainwallet' => array(
          'title' => __('Mainnet account', 'woow'),
          'type' => 'text',
          'description' => __('Set the destination account on mainnet where pay token will be paid. <b>Used only when "Use testnet" option is disabled</b>', 'woow'),
          'default' => __('', 'woow'),
          'desc_tip'      => true,
        ),
        'testwallet' => array(
          'title' => __('Testnet account', 'woow'),
          'type' => 'text',
          'description' => __('Set the destination account on testnet where pay token will be paid. Used only when "Use testnet" option is enabled.', 'woow'),
          'default' => __('', 'woow'),
          'desc_tip'      => true,
        ),
        'appName' => array(
          'title' => __('dApp Name', 'woow'),
          'type' => 'text',
          'description' => __('The application name displayed in the webauth modal', 'woow'),
          'default' => __('', 'woow'),
          'desc_tip'      => true,
        ),
        'appLogo' => array(
          'title' => __('dApp Logo', 'woow'),
          'type' => 'text',
          'description' => __('The application logo displayed in the webauth modal', 'woow'),
          'default' => __('', 'woow'),
          'desc_tip'      => true,
        ),
        'allowedTokens' => array(
          'title' => __('Allowed Tokens', 'woow'),
          'type' => 'text',
          'description' => __('Accepted tokens as payment for transfer, will be displayed in the payments process flow. Specify a uppercase only, coma separated, tokens list', 'woow'),
          'default' => __('', 'woow'),
          'desc_tip'      => true,
        ),
        'polygonKey' => array(
          'title' => __('Polygon API key ', 'woow'),
          'type' => 'text',
          'description' => __('Your key for currency pricing service on polygon.io.', 'woow'),
          'default' => __('', 'woow'),
          'desc_tip'      => true,
        ),
        'registered' => array(
          'title' => __('Register store ', 'woow'),
          'type' => 'woow_register',
          'description' => __('Register you store nearby the smart contract', 'woow'),
          'default' => __('', 'woow'),
          'desc_tip'      => true,
        )

      );
    }

    /**
     * Check If The Gateway Is Available For Use.
     *
     * @return bool
     */
    public function is_available()
    {
      return true;

      return parent::is_available();
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id)
    {

      $order = wc_get_order($order_id);

      if ($order->get_total() > 0) {
        // Mark as processing or on-hold (payment won't be taken until delivery).
        $order->update_status(apply_filters('woocommerce_cod_process_payment_order_status', $order->has_downloadable_item() ? 'on-hold' : 'processing', $order), __('Payment to be made upon delivery.', 'woow'));
      } else {
        $order->payment_complete();
      }

      // Remove cart.
      WC()->cart->empty_cart();

      // Return thankyou redirect.
      return array(
        'result'   => 'success',
        'redirect' => $this->get_return_url($order),
      );
    }

    /** 
     * Render the payment field
     */
    public function payment_fields()
    {

      if ($this->description) {
        // you can instructions for test mode, I mean test card numbers etc.
        $desc = $this->description;
        if ($this->testnet) {
          $desc = ' <b>TESTNET ENABLED.</b><br>';
          $desc .= $this->description;
          $desc  = trim($desc);
        }
        // display the description with <p> tags etc.
        echo wpautop(wp_kses_post($desc));
      }
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page()
    {
      echo "Et mon cul c'est du poulet";
      if ($this->instructions) {
        //echo wp_kses_post(wpautop(wptexturize($this->instructions)));
      }
    }

    /**
     * Change payment complete order status to completed for COD orders.
     *
     * @since  3.1.0
     * @param  string         $status Current order status.
     * @param  int            $order_id Order ID.
     * @param  WC_Order|false $order Order object.
     * @return string
     */
    public function change_payment_complete_order_status($status, $order_id = 0, $order = false)
    {
      if ($order && $this->id === $order->get_payment_method()) {
        $status = 'completed';
      }
      return $status;
    }

    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order Order object.
     * @param bool     $sent_to_admin  Sent to admin.
     * @param bool     $plain_text Email format: plain text or HTML.
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
      if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
        echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
      }
    }
  }
}

<?php

class TokenPrices
{
  private $priceValidityInterval = 21600000;

  public function __construct()
  {
  }

  public function getTokenPrices()
  {
    global $wpdb;
    // Do your code checking stuff here e.g. 
    $myPluginGateway = WC()->payment_gateways->payment_gateways()['xprcheckout'];

    $now = time();
    $savedPriceRatesValidity = $myPluginGateway->get_option('price_rates_validity');
    $savedPriceRates = $myPluginGateway->get_option('price_rates');
    //if (is_null($savedPriceRates) || $now > $savedPriceRatesValidity) {

    $headers = array(
      'Content-Type: application/json'
    );
    $url = "https://www.api.bloks.io/proton/tokens";

    $response = wp_remote_get($url, array(
      'headers' => $headers,
      'timeout' => 45
    ));

    if (is_wp_error($response)) {
      return null; // Handle error
    }

    $responseData = json_decode(wp_remote_retrieve_body($response), true);

    if ($responseData) {
      $rawFiltered = array_map(function ($token) use ($wpdb) {
        if (strpos($token['key'], 'test') !== false) return null;
        $tokenBase = [];

        $tokenBase['symbol'] = $token['symbol'];
        $tokenBase['contract'] = $token['account'];
        $tokenBase['decimals'] = $token['supply']['precision'];
        $tokenBase['logo'] = $token['metadata']['logo'];
        $rawPrices = array_filter($token['pairs'], function ($pair) {
          return $pair['pair_quote'] == 'USD';
        });

        $prices = array_values($rawPrices);

        if (isset($prices[0])) {
          $mergedToken = array_merge($prices[0], $tokenBase);
          if ($mergedToken['pair_base'] == "XPR") error_log(print_r($mergedToken['quote']['price_usd'], 1));
          $sql = "INSERT INTO wp_" . XPRCHECKOUT_TABLE_TOKEN_RATES . " (symbol,contract,token_precision,rate) VALUES (%s,%s,%d,%.12f) ON DUPLICATE KEY UPDATE rate = %.12f";
          $sql = $wpdb->prepare($sql, $mergedToken['pair_base'], $mergedToken['contract'], $mergedToken['decimals'], $mergedToken['quote']['price_usd'], $mergedToken['quote']['price_usd']);
          $res = $wpdb->query($sql);

          return $mergedToken;
        } else {
          return null;
        }
      }, $responseData);

      return array_values(array_filter($rawFiltered, function ($token) {
        return !is_null($token);
      }));
    }
  }
}

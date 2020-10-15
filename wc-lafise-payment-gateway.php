<?php
/*
Plugin Name: Gateway SMART MONEY LAFISE
Description: Extiende WooCommerce añadiendo un gateway de pago de LAFISE.
Version: 1.0.0
Author: Román Romero
*/

add_action( 'plugins_loaded', 'lafise_payment_init', 0 );
  function lafise_payment_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
    
    include_once( 'wc-lafise-payment.php' );

    add_filter( 'woocommerce_payment_gateways', 'add_lafise_payment_gateway' );
    function add_lafise_payment_gateway( $methods ) {
      $methods[] = 'Lafise_Payment_Gateway';
      return $methods;
    }
  }

  add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'lafise_payment_action_links' );
  function lafise_payment_action_links( $links ) {
    $plugin_links = array(
      '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'lafise-payment' ) . '</a>',
    );
    return array_merge( $plugin_links, $links );
  }
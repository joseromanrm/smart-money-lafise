<?php

class Lafise_Payment_Gateway extends WC_Payment_Gateway_CC {

  function __construct() {

    $this->id = "lafise_payment";
    $this->method_title = __( "LAFISE PAYMENT GATEWAY", 'lafise-payment' );
    $this->method_description = __( "LAFISE Payment Gateway Plug-in for WooCommerce", 'lafise-payment' );
    $this->title = __( "LAFISE Payment Gateway", 'lafise-payment' );
    $this->icon = null;
    $this->has_fields = false;
    $this->init_form_fields();
    $this->init_settings();
    
    foreach ( $this->settings as $setting_key => $value ) {
      $this->$setting_key = $value;
    }

    add_action( 'admin_notices', array( $this,  'do_ssl_check' ) );
    
    if ( is_admin() ) {
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }   
  } 


  public function init_form_fields() {
    $this->form_fields = array(
      'enabled' => array(
        'title'   => __( 'Activar / Desactivar', 'lafise-payment' ),
        'label'   => __( 'Activar este metodo de pago', 'lafise-payment' ),
        'type'    => 'checkbox',
        'default' => 'no',
      ),
      'title' => array(
        'title'   => __( 'Título', 'lafise-payment' ),
        'type'    => 'text',
        'desc_tip'  => __( 'Título de pago que el cliente verá durante el proceso de pago.', 'lafise-payment' ),
        'default' => __( 'Tarjeta de crédito', 'lafise-payment' ),
      ),
      'description' => array(
        'title'   => __( 'Descripción', 'lafise-payment' ),
        'type'    => 'textarea',
        'desc_tip'  => __( 'Descripción de pago que el cliente verá durante el proceso de pago.', 'lafise-payment' ),
        'default' => __( 'Pague con seguridad usando su tarjeta de crédito.', 'lafise-payment' ),
        'css'   => 'max-width:350px;'
      ),
      'l_user' => array(
        'title'   => __( 'User', 'lafise-payment' ),
        'type'    => 'text',
        'desc_tip'  => __( 'Username for merchant.', 'lafise-payment' ),
        'default' => '',
      ),
      'l_pass' => array(
        'title'   => __( 'Password', 'lafise-payment' ),
        'type'    => 'text',
        'desc_tip'  => __( 'Password for merchant.', 'lafise-payment' ),
        'default' => '',
      ),
      'merchant_id' => array(
        'title'   => __( 'Merchant ID', 'lafise-payment' ),
        'type'    => 'text',
        'desc_tip'  => __( 'Merchant ID para integración del comercio electrónico.', 'lafise-payment' ),
        'default' => '',
      ),
      'terminal_id' => array(
        'title'   => __( 'Terminal ID', 'lafise-payment' ),
        'type'    => 'text',
        'desc_tip'  => __( 'Terminal ID para integración del comercio electrónico.', 'lafise-payment' ),
        'default' => '',
      ),
    );    
  }

  public function process_payment( $order_id ) {
    global $woocommerce;
    
    $customer_order = new WC_Order( $order_id );
    $login_url = 'https://mobilcashd.bancolafise.com.ni/pay/login';
    $environment_url = 'https://mobilcashd.bancolafise.com.ni/pay/paymentDirect';
    $time = time();
    
    $l_user = $this->l_user;
    $l_pass = $this->l_pass;
    $merchant_id = $this->merchant_id;
    $terminal_id = $this->terminal_id;

    $payload = array(
      "user"  => $l_user,
      "password"  => $l_pass,
    );

    $headers = array(
      'content-type' => 'application/json',
      "X-Merchant"  => $merchant_id,
    );

    $response = wp_remote_post( $login_url, array(
      'method'    => 'POST',
      'headers'   => $headers,
      'body'      => json_encode($payload) ,
    ) );
    
    
    if ( is_wp_error( $response ) ) {
      throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'lafise-payment' ) );
    }    

    if ( empty( $response['body'] ) )
      throw new Exception( __( 'LAFISE\'s Response was empty.', 'lafise-payment' ) );

    $response_body = wp_remote_retrieve_body( $response );

    $resp = json_decode($response_body, true);

    if ( $resp['status'] == 200  ) {
      
      $token = $resp['data']['token'];
      $orderid = str_replace( "#", "", $customer_order->get_order_number() );
      $coment = $customer_order->get_customer_note();
      $expire = explode('/',$_POST['lafise_payment-card-expiry']);

      $headers_payment = array(
        'content-type' => 'application/json',
        "X-Merchant"  => $merchant_id,
        "X-Token"  => $token
      );

      $payload_payment = array(
        "referenceExternal" => (string)time(),
        "number" => str_replace( array(' ', '-' ), '', $_POST['lafise_payment-card-number'] ),
        "month" => trim($expire[0]),
        "year" => trim($expire[1]),
        "cvc" => ( isset( $_POST['lafise_payment-card-cvc'] ) ) ? $_POST['lafise_payment-card-cvc'] : '',
        "amount" => (float)$woocommerce->cart->total,
        //floatval( preg_replace( '#[^\d.]#', '', $woocommerce->cart->get_cart_total() ) )
        "commerceID" => (int)$merchant_id,
        "comment" => $coment,
        "terminalID" => $terminal_id
      );
  
      $response_payment = wp_remote_post( $environment_url, array(
        'method'    => 'POST',
        'headers'   => $headers_payment,
        'body'      => json_encode($payload_payment, true),
      ) );

      if ( is_wp_error( $response_payment ) ) {
        throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'lafise-payment' ) );
      }
      
      if ( empty( $response_payment['body'] ) )
        throw new Exception( __( 'LAFISE\'s Response was empty.', 'lafise-payment' ) );
  
      $response_payment_body = wp_remote_retrieve_body( $response_payment );
      $resp_payment = json_decode($response_payment_body, true);
      
      //var_dump($headers_payment);
      //var_dump($payload_payment);
      //var_dump(json_encode($payload_payment, true));
      //var_dump($response_payment_body);

      if ( $resp_payment['status'] == 200  ) {
        $customer_order->add_order_note( __( 'LAFISE payment completed.', 'lafise-payment' ) );
        $order_id = method_exists( $customer_order, 'get_id' ) ? $customer_order->get_id() : $customer_order->ID;
        update_post_meta($order_id , '_wc_order_lafise_authcode', $resp_payment['data']['authorizationCode'] );
        update_post_meta($order_id , '_wc_order_lafise_transactionid', $resp_payment['data']['transactionNumber'] );
        $customer_order->payment_complete();
        $woocommerce->cart->empty_cart();

        return array(
          'result'   => 'success',
          'redirect' => $this->get_return_url( $customer_order ),
        );
      }else{
        wc_add_notice( $resp_payment['message'], 'error' );
        $customer_order->add_order_note( 'Error: '. $resp_payment['message'] );
      }

    } else {
      wc_add_notice( $resp['errors'], 'error' );
      $customer_order->add_order_note( 'Error: '. $resp['errors'] );
    }

  }

  public function validate_fields() {
    return true;
  }
  
  public function do_ssl_check() {
    if( $this->enabled == "yes" ) {
      if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
        echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";  
      }
    }   
  }

}

add_action( 'woocommerce_admin_order_data_after_billing_address', 'show_lafise_info', 10, 1 );
function show_lafise_info( $order ){
    $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
    echo '<p><strong>'.__('LAFISE Auth Code').':</strong> ' . get_post_meta( $order_id, '_wc_order_lafise_authcode', true ) . '</p>';
    echo '<p><strong>'.__('LAFISE Transaction Id').':</strong> ' . get_post_meta( $order_id, '_wc_order_lafise_transactionid', true ) . '</p>';
}

?>
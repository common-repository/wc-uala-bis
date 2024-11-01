<?php
/**
 * Plugin Name: Integrar Ualá Bis con WooCommerce
 * Plugin URI: https://developers.ualabis.com.ar/other-stores/wooCommerce
 * Description: Ualá Bis
 * Author: Ualá Bis
 * Author URI: https://www.ualabis.com.ar/
 * Version: 0.1.4
 * WC tested up to: 8.5.0
 * Text Domain: wc-gateway-uala
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2024 Ualá Bis
 *
 *
 * @package   WC-Gateway-Ualá
 * @author    Wanderlust Codes
 * @category  Admin
 * @copyright Copyright (c) 2010-2024, Wanderlust Codes
 *
 */

  add_action( 'before_woocommerce_init', function() {
          if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                  \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
          }
  } );
 
  add_filter( 'woocommerce_payment_gateways', 'uala_add_gateway_class' );
  add_action( 'plugins_loaded', 'uala_init_gateway_class' );
  add_action( 'wp_head', 'uala_autorization_callback');
  add_action( 'before_woocommerce_pay', 'uala_error_pago_complete' );

  function uala_error_pago_complete( $order_id ) {
    
    $order = wc_get_order( $order_id );
    
    if ( $order instanceof WC_Order ) {
      
      $payment_method = $order->get_payment_method();

      if (strpos($payment_method, 'uala_gateway') !== false) {
        echo '<div style="    background: red;     color: white;     padding: 10px;     text-align: center;">Ualá Bis - Ocurrió un problema con el pago, por favor, volver a intentar.</div>';
      }
      
    }


    
  
  }

  function uala_autorization_callback(){   
    
    if (isset($_GET['state']) && isset($_GET['code'])) { 
      if($_GET['state'] && $_GET['code']){
        $callback = get_site_url();
        $uala_response = wp_remote_get( 'https://checkout-bff.prod.adquirencia.ar.ua.la/1/apps/authorize/?state='.$_GET['state'].'&code='.$_GET['code'] );
        if (! is_wp_error( $uala_response ) ) {
          $response_uala = json_decode($uala_response['body']);
          if($response_uala->username){
            update_option('ualaauthorize', $uala_response['body']);
            $data_autorize = get_option('woocommerce_uala_gateway_settings', true);
            $data_autorize['user_name'] = $response_uala->username;
            $data_autorize['client_id'] = $response_uala->client_id;
            $data_autorize['client_secret_id'] = $response_uala->client_secret_id;
            update_option('woocommerce_uala_gateway_settings', $data_autorize);
            echo ' <script> location.replace("'.$callback.'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=uala_gateway"); </script> ';
            exit();
          }        
        }     
      }
    }
    
  };

  function uala_add_gateway_class( $gateways ) {
    
    $gateways[] = 'WC_Wanderlust_Uala_Gateway'; 
    return $gateways;
    
  }

  function uala_init_gateway_class() {

      class WC_Wanderlust_Uala_Gateway extends WC_Payment_Gateway {

          public function __construct() {
              $this->id = 'uala_gateway';
              $this->icon = apply_filters( 'woocommerce_uala_icon', plugins_url( 'wc-uala-bis/img/logos-tarjetas.png', plugin_dir_path( __FILE__ ) ) );            
              $this->has_fields = false; 
              $this->method_title = 'Ualá Bis';
              $this->method_description = 'Te permite cobrar con tarjetas de crédito y débito. La acreditación es inmediata.</br> Es necesario tener una cuenta en Ualá para activar este medio de pago';
              $this->supports = array( 'products' );
              $this->init_form_fields();
              $this->init_settings();
              $this->title = $this->get_option( 'title' );
              $this->description = $this->get_option( 'description' );
              $this->enabled = $this->get_option( 'enabled' );
              $this->user_name = $this->get_option( 'user_name' );
              $this->client_id =  $this->get_option( 'client_id' ) ;
              $this->client_secret_id =  $this->get_option( 'client_secret_id' ) ;
              $this->testmode = 'yes' === $this->get_option( 'testmode' );
              $this->guardar_log = 'yes' === $this->get_option( 'guardar_log' );
              add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
              add_action( 'woocommerce_api_uala', array( $this, 'webhook' ) );
              add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'gracias_compra' ) );
           }
        
          public function admin_options() {
         
          ?>
            <style>
              @import url('https://fonts.cdnfonts.com/css/public-sans');

            #mainform p, #mainform .form-table, #mainform h2 {
                font-family: 'Public Sans', sans-serif;
                line-height: 22px;
                font-weight: 500;
            }

            #mainform .description {
                font-size: 10px !important;
            }

            .wc_payment_gateways_wrapper .description #logobis {
                background: transparent;
                top: 0px;
                margin: 0px;
            }

            #logobis {
                position: relative;
                top: -45px;
                background: #f0f0f1;
                margin-bottom: -40px;
            }

            #mainform .button-primary {
                background: #3564FD !important;
                border-color: #3564FD;
                height: 48px;
                width: 195px;
                border-radius: 24px;
            }


            #woocommerce_uala_gateway_enabled {
              display: none;
            }

            #woocommerce_uala_gateway_enabled {
              display: inherit;
              position: relative;
              cursor: pointer;
              -webkit-user-select: none;
              -moz-user-select: none;
              -ms-user-select: none;
            }

            #woocommerce_uala_gateway_enabled:hover  {
              background: #3564FD;
              box-shadow: inset 0px 0px 0px 2px #3564FD;
            }

            #woocommerce_uala_gateway_enabled   {
              content: "";
              display: inherit;
              width: 1.4em;
              height: 1.4em;
              border: 1px solid #BDBDBD;
              border-radius: 0.2em;
              left: 0;
              top: 0;
              -webkit-transition: all 0.2s, background 0.2s ease-in-out;
              transition: all 0.2s, background 0.2s ease-in-out;
              background: #F5F5F5;
            }
            #woocommerce_uala_gateway_enabled:checked  {
              width: 1.3em;
              height: 1.3em;
              border-radius: 0.2em;
              border: 2px solid #3564FD;
              -webkit-transform: rotate(90deg);
              transform: rotate(90deg);
              background: #3564FD;
              box-shadow: 0 0 0 1px #3564FD;
            }

            #woocommerce_uala_gateway_enabled:checked::before {
              content: "";
            }

            #woocommerce_uala_gateway_testmode {
              display: none;
            }

            #woocommerce_uala_gateway_testmode {
              display: inherit;
              position: relative;
              cursor: pointer;
              -webkit-user-select: none;
              -moz-user-select: none;
              -ms-user-select: none;
            }

            #woocommerce_uala_gateway_testmode:hover  {
              background: #3564FD;
              box-shadow: inset 0px 0px 0px 2px #3564FD;
            }

            #woocommerce_uala_gateway_testmode   {
              content: "";
              display: inherit;
              width: 1.4em;
              height: 1.4em;
              border: 1px solid #BDBDBD;
              border-radius: 0.2em;
              left: 0;
              top: 0;
              -webkit-transition: all 0.2s, background 0.2s ease-in-out;
              transition: all 0.2s, background 0.2s ease-in-out;
              background: #F5F5F5;
            }
            #woocommerce_uala_gateway_testmode:checked  {
              width: 1.3em;
              height: 1.3em;
              border-radius: 0.2em;
              border: 2px solid #3564FD;
              -webkit-transform: rotate(90deg);
              transform: rotate(90deg);
              background: #3564FD;
              box-shadow: 0 0 0 1px #3564FD;
            }

            #woocommerce_uala_gateway_testmode:checked::before {
              content: "";
            }

            #woocommerce_uala_gateway_vaciar_carro {
              display: none;
            }

            #woocommerce_uala_gateway_vaciar_carro {
              display: inherit;
              position: relative;
              cursor: pointer;
              -webkit-user-select: none;
              -moz-user-select: none;
              -ms-user-select: none;
            }

            #woocommerce_uala_gateway_vaciar_carro:hover  {
              background: #3564FD;
              box-shadow: inset 0px 0px 0px 2px #3564FD;
            }

            #woocommerce_uala_gateway_vaciar_carro   {
              content: "";
              display: inherit;
              width: 1.4em;
              height: 1.4em;
              border: 1px solid #BDBDBD;
              border-radius: 0.2em;
              left: 0;
              top: 0;
              -webkit-transition: all 0.2s, background 0.2s ease-in-out;
              transition: all 0.2s, background 0.2s ease-in-out;
              background: #F5F5F5;
            }
            #woocommerce_uala_gateway_vaciar_carro:checked  {
              width: 1.3em;
              height: 1.3em;
              border-radius: 0.2em;
              border: 2px solid #3564FD;
              -webkit-transform: rotate(90deg);
              transform: rotate(90deg);
              background: #3564FD;
              box-shadow: 0 0 0 1px #3564FD;
            }

            #woocommerce_uala_gateway_vaciar_carro:checked::before {
              content: "";
            }

            </style>

            <script>
              jQuery("body").on("click", "#woocommerce_uala_gateway_testmode", function(e) {
                if(jQuery("#woocommerce_uala_gateway_testmode").is(':checked') === true){
                  jQuery("#woocommerce_uala_gateway_user_name").val('new_user_1631906477');
                  jQuery("#woocommerce_uala_gateway_client_id").val('5qqGKGm4EaawnAH0J6xluc6AWdQBvLW3');
                  jQuery("#woocommerce_uala_gateway_client_secret_id").val('cVp1iGEB-DE6KtL4Hi7tocdopP2pZxzaEVciACApWH92e8_Hloe8CD5ilM63NppG');
                }
                
              });
            </script>

          <?php

            echo '<h3>Ualá Bis</h3>';
            echo '<p><img id="logobis" src="'.plugins_url( 'wc-uala-bis/img/logobis.png', plugin_dir_path( __FILE__ ) ).'" alt="Pagar con Ualá"> <br> <span>Te permite cobrar con tarjetas de crédito y débito. La acreditación es inmediata.</br> Es necesario tener una cuenta en Ualá para activar este medio de pago. </span><br></p>';
            echo '<table class="form-table woo-uala">';

            $this->generate_settings_html();

            echo '</table>';
            $data_autorize = get_option('woocommerce_uala_gateway_settings', true);  
            if(isset($_POST['woocommerce_uala_gateway_testmode'])) {
              if($_POST['woocommerce_uala_gateway_testmode'] == 1){             
                $data_autorize['user_name'] = 'new_user_1631906477';
                $data_autorize['client_id'] = '5qqGKGm4EaawnAH0J6xluc6AWdQBvLW3';
                $data_autorize['client_secret_id'] = 'cVp1iGEB-DE6KtL4Hi7tocdopP2pZxzaEVciACApWH92e8_Hloe8CD5ilM63NppG';
                update_option('woocommerce_uala_gateway_settings', $data_autorize);
              }
            }
            
           

        }

          public function init_form_fields(){ 
            
              $callback = get_site_url();
              $url_api_cred = 'https://web.prod.adquirencia.ar.ua.la/?callbackUrl='.$callback;
              
              $this->form_fields = array(
                
                  'title' => array(
                      'title'       => 'Título',
                      'type'        => 'text',
                      'description' => 'Texto que se va a mostrar en el checkout.',
                      'default'     => 'Pagar con tarjeta de crédito o débito',
                      'desc_tip'    => true,
                  ),
                  'description' => array(
                      'title'       => 'Descripción',
                      'type'        => 'textarea',
                      'description' => 'Esta descripción se mostrará en el checkout',
                      'default'     => 'Servicio provisto por Ualá Bis.',
                      'css'         => 'max-width: 400px;',
                  ),
                  array(
                      'title'       => __( 'Credenciales', 'woocommerce' ),
                      'type'        => 'title',
                      'id'          => 'custom_settings_2',
                      'class'       => 'custom_settings_2',
                      'description' =>  'Si solicitaste tus credenciales a Ualá y las recibiste por email, ingresalas a continuación.</br> Si todavía no las pediste, <a style="color: #3564FD;" href="'.$url_api_cred.'">hacé clic en este enlace </a> (te lleva a tu cuenta Ualá y luego te devuelve a esta pantalla con los datos autocompletados).'
                  ),
                  'user_name' => array(
                      'title'       => 'Usuario',
                      'type'        => 'text',
                  ),
                  'client_id' => array(
                      'title'       => 'Clientld',
                      'type'        => 'password',
                  ),  
                  'client_secret_id' => array(
                      'title'       => 'ClientSecret',
                      'type'        => 'password',
                  ), 
                  array(
                      'title'       => __( 'Opciones Checkout', 'woocommerce' ),
                      'type'        => 'title',
                      'id'          => 'custom_settings_2', 
                      'description' =>  ' Te permite activar / desactivar las opciones de pago entre otras configuraciones.',
                      'class'       => '',
                  ),
                  'enabled' => array(
                      'title'       => '',
                      'label'       => 'Activar Ualá Bis en el checkout',
                      'type'        => 'checkbox',
                      'description' => '',
                      'class'       => 'checkbox-rect',
                      'default'     => 'no'
                  ),
                  'testmode' => array(
                      'title'       => '',
                      'label'       => 'Activar modo de prueba',
                      'type'        => 'checkbox',
                      'description' => 'Se cargarán de forma automática credenciales de prueba provistas por Ualá',
                      'default'     => 'no'
                  ),
                  'vaciar_carro' => array(
                      'title'       => '',
                      'label'       => 'Vaciar el carrito al redirigir a Ualá Bis',
                      'type'        => 'checkbox',
                      'description' => 'En caso de estar desactivado el carrito se vacia en la pagina de agradecimiento.',
                      'default'     => 'no'
                  ),                              
                  'guardar_log' => array(
                      'title'       => '',
                      'label'       => 'Guardar log de webhooks',
                      'type'        => 'checkbox',
                      'description' => 'En caso de estar desactivado, no se guardaran los registros de webhooks.',
                      'default'     => 'no'
                  ),                  
                
                
              ) ;
            
          }

          public function payment_fields() {
            
            if ( $this->description ) {
              if ( $this->testmode ) {
                $this->description .= 'MODO TESTEO ACTIVADO';
                $this->description  = trim( $this->description );
              }
              echo wpautop( wp_kses_post( $this->description ) );
            }

          }

          public function get_token() {
            
            if ( $this->testmode ) {
              $token = 'https://auth.stage.ua.la/1/auth/token';
            } else {
              $token = 'https://auth.prod.ua.la/1/auth/token';
            }

            $body = [
                'user_name'  => $this->user_name,
                'client_id' =>  $this->client_id,
                'client_secret_id' => $this->client_secret_id,
                'grant_type' => 'client_credentials',
            ];

            $body = wp_json_encode( $body );

            $options = [
                'body'        => $body,
                'headers'     => [
                    'Content-Type' => 'application/json',
                ],
                'timeout'     => 60,
                'redirection' => 5,
                'blocking'    => true,
                'httpversion' => '1.0',
                'sslverify'   => false,
                'data_format' => 'body',
            ];

           $respuesta =  wp_remote_post( $token, $options );   

           if ( !is_wp_error( $respuesta ) ) {
              $token_response = json_decode($respuesta['body']);
               if($token_response->access_token){
                $token = $token_response->access_token;    
                return $token; 
               }
            }
          }

          public function process_payment( $order_id ) {
            
              global $woocommerce;
              $order = wc_get_order( $order_id );
              $nombre = '';
              $items = $order->get_items();
              foreach( $items as $item ) {    				 
                  if ( $item['product_id'] > 0 ) {
                    $product = wc_get_product( $item['product_id'] );
                    if(empty($nombre)){
                      $nombre = $product->get_name();
                    } else {
                      $nombre = $nombre . ' - ' .$product->get_name();
                    }

                  }
              }

            $token = $this->get_token();
            
            if($token){
              if ( $this->testmode ) {
                $checkout = 'https://checkout.stage.ua.la/1/checkout';
              } else {
                $checkout = 'https://checkout.prod.ua.la/1/checkout';
              }           

              $site_url =  get_site_url(); 
              $notificaciones = $site_url . '/?wc-api=uala';

              $pload = [
                'origin' => 'woocommerce_uala',
                'amount' =>	$order->get_total(),
                'description' => $nombre,
                'userName' => $this->user_name,
                'notification_url' => $notificaciones,
                'callback_fail' => $order->get_checkout_payment_url(),
                'callback_success' => $order->get_checkout_order_received_url()
              ];

              $body = wp_json_encode( $pload );

              $options = [
                  'body'        => $body,
                  'headers'     => [
                    'Authorization'=> 'Bearer ' .$token,
                    'Content-Type' => 'application/json',
                  ],
                  'timeout'     => 60,
                  'redirection' => 5,
                  'blocking'    => true,
                  'httpversion' => '1.0',
                  'sslverify'   => false,
                  'data_format' => 'body',
              ];

              $url_redirect = wp_remote_post( $checkout, $options );      

              if ( !is_wp_error( $url_redirect ) ) {
               
                $response = json_decode($url_redirect['body']);
                update_option($response->uuid, $order_id);
                
                $order->update_meta_data('_uala', $url_redirect['body']); 
                $order->save(); 
                
                if($response->links->checkoutLink){
                  $order->reduce_order_stock();

                  if ( $this->settings['vaciar_carro'] == 'yes' ) { 
                    WC()->cart->empty_cart();
                  }  

                  return array(
                    'result' => 'success',
                    'redirect' => $response->links->checkoutLink
                  );                  
                } else {
                  wc_add_notice( __( 'Error al obtener el checkoutLink - Ualá Bis .', 'woocommerce' ), 'error' );
                }
 

              } else {
                update_post_meta($order_id, 'uala_error', $url_redirect['body']);
                wc_add_notice( __( 'Error en la generacion de Checkout - Ualá Bis .', 'woocommerce' ), 'error' );
              }              
            } else {
                  wc_add_notice( __( 'Error en el TOKEN - Ualá Bis .', 'woocommerce' ), 'error' );
            }
            


          }
        
          public function gracias_compra($order_id) {
            
            if ( $this->settings['vaciar_carro'] != 'yes' ) {
              WC()->cart->empty_cart();
            }  
            
          } 
        
          public function webhook() { 
              $log = new WC_Logger();
              header( 'HTTP/1.1 200 OK' );
              $postBody = file_get_contents('php://input');
            
              if($this->guardar_log && $this->guardar_log == 'yes'){
                $log->log('UALA LOG', 'webhook: ' . $postBodye);
              }
            
              $responseipn = json_decode($postBody);
  
              if ($responseipn->uuid) {
                  $order_id = get_option($responseipn->uuid, true);
                  $order = wc_get_order($order_id);

                  if ($order instanceof WC_Order) { 
                      $order->update_meta_data('_uala_response', $postBody); 

                      if ($responseipn->status == 'APPROVED' || $responseipn->status == 'PROCESSED') {
                          $order->add_order_note('Ualá Bis: ' . __('Pago Aprobado.', 'wc-gateway-uala'));
                          $order->payment_complete();
                      } else {
                          $order->add_order_note('Ualá Bis: ' . __('Pago Fallido.', 'wc-gateway-uala'));
                      }

                      $order->save();  
                  }
              }
          }
        
      }
    
  }
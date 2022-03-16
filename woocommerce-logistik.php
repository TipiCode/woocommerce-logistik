<?php
/**
 * Plugin Name: Logistik
 * Plugin URI: https://github.com/TipiCode/woocommerce-logistik
 * Description: Delivery service plugin
 * Version:     1.0.0
 * Requires PHP: 7.2
 * Author:      tipi(code)
 * Author URI: https://codingtipi.com
 * License:     MIT
 * WC requires at least: 5.8.0
 * WC tested up to: 6.0.0
 *
 * @package Logistik
*/

defined('ABSPATH') || exit;

class Logistik {
    function __construct() {
		$this->plugin_name = "logistik";
		add_action('admin_menu', array($this, 'addPluginAdminMenu'));
		add_action( 'init', array( $this, 'enqueque_styles') );
		add_action( 'woocommerce_thankyou', array( $this, 'create_response') );
		add_action('admin_init', array( $this, 'registerAndBuildFields' ));	
        add_filter( 'woocommerce_billing_fields' , array( $this, 'custom_override_checkout_fields') );
        
	}
    function custom_override_checkout_fields( $fields ) {
		$fields['billing_lphone']   = array(
			'label'        => 'Teléfono',
			'required'     => true,
			'class'        => array( 'form-row-wide', 'chekcout-custom-field' ),
			'priority'     => 70,
		);
		
		return $fields;
	}

    function create_response( $order_id ) {
        $order = wc_get_order( $order_id );
		if( $order->get_status() != "processing") {
			return;
		}
        

        //Logistika configuration data
        $username = get_option('logistik_username');
        $password = get_option('logistik_password');
        $db = get_option('logistik_db');
        $url = get_option('logistik_url');
        $description = get_option('logistik_description');

        if($username == "" || $password == "" || $db == "" || $url == "") {
            return;
        }

        //Get order
        $shipping_total = $order->get_shipping_total();
        $pesoTotal = 0;
        $volumenTotal = 0;
        $granTotal = 0;
        
        foreach ( $order->get_items() as $item_id => $item ) {
            $item_data = $item->get_data();

            // Get quantity of the products
		    $product_quantity = $item_data['quantity'];
            // Get total of all products
		    $product_total = $item_data['total'];
            // calculo precio de items redondeado
            $precioUni_round = round(floatval($product_total), 2);

            global $product;
            $product = wc_get_product($item_data['product_id']);
            if ( ! empty( $product ) ) {
                // $attributes = $product->get_attributes();
                $weight = intval($product->get_weight());
                $lenght = intval($product->get_length());
                $width  = intval($product->get_width());
                $height = intval($product->get_height());

            };

            // Calcular el volumen de cada item
            $itemVol = ($lenght*$width*$height) *$product_quantity;
            // Calcular el volumen del pedido completo
            $volumenTotal += intval($itemVol);

            // Calcular el peso de cada item
            $itemWeight = $weight*$product_quantity;
            // Calcular el peso total de todo el pedido
            $pesoTotal += $itemWeight;

            $granTotal += $precioUni_round;
        }
        $granTotal += $shipping_total;

        //User Information
        $data = $order->get_data();
        $receptor = [
            'nombre' => $data['billing']['first_name'] . $data['billing']['last_name'],
            'direccion' => $data['billing']['address_1'],
            'municipio' => "Guatemala",
            'phone' => get_post_meta( $order_id, '_billing_lphone', true ),
        ];

        $ripcordPath = realpath( __DIR__ . './inc/ripcord/ripcord.php');
        include_once $ripcordPath;

        $common = ripcord::client("$url/xmlrpc/2/common");
// 	    print_r($common);
//      $common->version();

        $uid = $common->authenticate($db, $username, $password, array());
	    
        if ($uid) {
            $models = ripcord::client("$url/xmlrpc/2/object");
            $access = $models->execute_kw($db, $uid, $password,
            'pedidos', 'check_access_rights',
            array('read'), array('raise_exception' => false));
           
            if ($access == 1){
                $register_row = array(
                   0 => array(),
                   1 => array(array(
                       "FECHA_REGISTRO"    => date("d/m/Y"),
                       "FECHA_ENTREGA"     => date("d/m/Y", strtotime("+1 day")),
                       "REFERENCIA"        => "order-".$order_id,
                       "CLIENTE_CODIGO"    => strval($order_id),
                       "CLIENTE_NOMBRE"    => $receptor['nombre'],
                       "CLIENTE_MUNICIPIO" => $receptor['municipio'],
                       "CLIENTE_DIRECCION" => $receptor['direccion'],
                       "CLIENTE_TELEFONO"  => $receptor['phone'],
                       "DESCRIPCION"       => $description,
                       "NOTAS"             => $description,
                       "PESO"              => $pesoTotal,
                       "VOLUMEN"           => $volumenTotal,
                       "PRECIO"            => $granTotal,
                   )),
               );
                $models = ripcord::client("$url/xmlrpc/2/object");
                $results = $models->execute_kw($db, $uid, $password, 'pedidos', 'Api_PedidoNuevo', $register_row);
               return ($results);
   
            } else {
               return('Acceso denegado');
            }
   
   
        } else {
           return('No se tiene acceso');
        }
    }

    function enqueque_styles() {
		// wp_register_style('logistika_style', plugins_url('inc/css/style.css',__FILE__ ));
		// wp_enqueue_style('logistika_style');
		// wp_register_script( 'logistika_script', plugins_url('inc/js/index.js',__FILE__ ), array( 'jquery' ), '1.0.0', true);
		// wp_enqueue_script('logistika_script');
	}

    function addPluginAdminMenu() {
		add_menu_page(  $this->plugin_name, 'Logistika', 'administrator', $this->plugin_name, array( $this, 'displayPluginAdminDashboard' ), 'dashicons-chart-area', 26 );
	}
    function displayPluginAdminDashboard() {
		require_once 'temp/'.$this->plugin_name.'-admin.php';
	}

    function registerAndBuildFields() {
		add_settings_section(
			// ID used to identify this section and with which to register options
			'logistik-general-section', 
			// Sub Title to be displayed on the administration page
			'',  
			// Callback used to render the description of the section
			 array( $this, 'logistik_display_general_account' ),    
			// Section on which to add this section of options
			'logistik-settings-form'                   
		);

		//Logistik user name
		add_settings_field(
			//Name of field
			 'logistik_username',
			//Label user see 
			 'User name',
			//Funtion to render html
			 array( $this, 'html_render_input' ),
			//Page slug
			 'logistik-settings-form',
			//Section to add this fields
			 'logistik-general-section',
			//Args to pass to callback
			array('inputName' => 'logistik_username')
		);
		register_setting(
			//Section to render    Field name     Callbackfunction(set default value, validations)
			'logistik-settings-form', 'logistik_username', array('sanitize_callback' => 'sanitize_text_field')
			// 'logistik-settings-form', 'logistik_username', array('sanitize_callback' => array($this, 'validateField'))
		);

		//Logistik Password
		add_settings_field(
			 'logistik_password',
			 'Password',
			 array( $this, 'html_render_input' ),
			 'logistik-settings-form',
			 'logistik-general-section',
			array('inputName' => 'logistik_password')
		);
		register_setting(
			'logistik-settings-form', 'logistik_password', array('sanitize_callback' => 'sanitize_text_field')
		);

        //Logistik Database
		add_settings_field(
            'logistik_db',
            'Database',
            array( $this, 'html_render_input' ),
            'logistik-settings-form',
            'logistik-general-section',
           array('inputName' => 'logistik_db')
       );
       register_setting(
           'logistik-settings-form', 'logistik_db', array('sanitize_callback' => 'sanitize_text_field')
       );

       //Logistik Url
       add_settings_field(
            'logistik_url',
            'Url',
            array( $this, 'html_render_input' ),
            'logistik-settings-form',
            'logistik-general-section',
           array('inputName' => 'logistik_url')
       );
       register_setting(
           'logistik-settings-form', 'logistik_url', array('sanitize_callback' => 'sanitize_text_field')
       );

       //Logistik Description
       add_settings_field(
            'logistik_description',
            'Descripción',
            array( $this, 'html_render_input' ),
            'logistik-settings-form',
            'logistik-general-section',
           array('inputName' => 'logistik_description')
       );
       register_setting(
           'logistik-settings-form', 'logistik_description', array('sanitize_callback' => 'sanitize_text_field')
       );
	}

    function logistik_display_general_account() {
		echo '<p>These settings apply to all Logistik functionality.</p>';
	} 

    function html_render_input($args) { ?>
		<input type="text" name="<?php echo $args["inputName"] ?>" value="<?php echo esc_attr(get_option($args["inputName"])) ?>">
	<?php }

	function validateField($input) {
		if($input == "") {
			//name of the option is validation, slug for particular error, message
			add_settings_error('logistik_username', 'logistik_username_error', 'User name no puede estar vacio');
			return get_option('logistik_username');
		}

		return $input;
	}
}

$logistik = new Logistik();
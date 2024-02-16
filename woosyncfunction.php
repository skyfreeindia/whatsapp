/*************************************************************************************************************************************************/
/*********************************************** This removes checkout cart message ****************************************************************/
/*************************************************************************************************************************************************/

add_filter( 'wc_add_to_cart_message_html', '__return_false' );


/*************************************************************************************************************************************************/
/************************************ Below code will help to create direct add to cart link for multiple products *******************************/
/*************************************************************************************************************************************************/
function webroom_add_multiple_products_to_cart( $url = false ) {
	// Make sure WC is installed, and add-to-cart qauery arg exists, and contains at least one comma.
	if ( ! class_exists( 'WC_Form_Handler' ) || empty( $_REQUEST['add-to-cart'] ) || false === strpos( $_REQUEST['add-to-cart'], ',' ) ) {
		return;
	}

	// Remove WooCommerce's hook, as it's useless (doesn't handle multiple products).
	remove_action( 'wp_loaded', array( 'WC_Form_Handler', 'add_to_cart_action' ), 20 );

	$product_ids = explode( ',', $_REQUEST['add-to-cart'] );
	$count       = count( $product_ids );
	$number      = 0;

	foreach ( $product_ids as $id_and_quantity ) {
		// Check for quantities defined in curie notation (<product_id>:<product_quantity>)
		
		$id_and_quantity = explode( ':', $id_and_quantity );
		$product_id = $id_and_quantity[0];

		$_REQUEST['quantity'] = ! empty( $id_and_quantity[1] ) ? absint( $id_and_quantity[1] ) : 1;

		if ( ++$number === $count ) {
			// Ok, final item, let's send it back to woocommerce's add_to_cart_action method for handling.
			$_REQUEST['add-to-cart'] = $product_id;

			return WC_Form_Handler::add_to_cart_action( $url );
		}

		$product_id        = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $product_id ) );
		$was_added_to_cart = false;
		$adding_to_cart    = wc_get_product( $product_id );

		if ( ! $adding_to_cart ) {
			continue;
		}

		$add_to_cart_handler = apply_filters( 'woocommerce_add_to_cart_handler', $adding_to_cart->get_type(), $adding_to_cart );

		// Variable product handling
		if ( 'variable' === $add_to_cart_handler ) {
			woo_hack_invoke_private_method( 'WC_Form_Handler', 'add_to_cart_handler_variable', $product_id );

		// Grouped Products
		} elseif ( 'grouped' === $add_to_cart_handler ) {
			woo_hack_invoke_private_method( 'WC_Form_Handler', 'add_to_cart_handler_grouped', $product_id );

		// Custom Handler
		} elseif ( has_action( 'woocommerce_add_to_cart_handler_' . $add_to_cart_handler ) ){
			do_action( 'woocommerce_add_to_cart_handler_' . $add_to_cart_handler, $url );

		// Simple Products
		} else {
			woo_hack_invoke_private_method( 'WC_Form_Handler', 'add_to_cart_handler_simple', $product_id );
		}
	}
}

// Fire before the WC_Form_Handler::add_to_cart_action callback.
add_action( 'wp_loaded', 'webroom_add_multiple_products_to_cart', 15 );


/**
 * Invoke class private method
 *
 * @since   0.1.0
 *
 * @param   string $class_name
 * @param   string $methodName
 *
 * @return  mixed
 */
function woo_hack_invoke_private_method( $class_name, $methodName ) {
	if ( version_compare( phpversion(), '5.3', '<' ) ) {
		throw new Exception( 'PHP version does not support ReflectionClass::setAccessible()', __LINE__ );
	}

	$args = func_get_args();
	unset( $args[0], $args[1] );
	$reflection = new ReflectionClass( $class_name );
	$method = $reflection->getMethod( $methodName );
	$method->setAccessible( true );

	//$args = array_merge( array( $class_name ), $args );
	$args = array_merge( array( $reflection ), $args );
	return call_user_func_array( array( $method, 'invoke' ), $args );
}
/*************************************************************************************************************************************************/
/************************************ Below code will create a webhook to be added in reseller panel *******************************/
/*************************************************************************************************************************************************/

// Handle incoming webhook requests
function handle_webhook_request_checkout($request) {
    $data = $request->get_json_params();

    if (empty($data)) {
        return new WP_Error('invalid_json', 'Invalid JSON payload', array('status' => 400));
    }

    // Process the webhook data
    process_webhook_data($data);

    return new WP_REST_Response('Webhook processed successfully', 200);
}

// Process the webhook data
function process_webhook_data($data) {
    if (!isset($data['item_lines']) || !is_array($data['item_lines'])) {
        return new WP_Error('invalid_data', 'No valid items found in the request', array('status' => 400));
    }

    $phone_number = sanitize_text_field($data['customer_phone_number']);
    $customer_name = sanitize_text_field($data['customer_name']);

    $product_ids_quantities = array(); // Array to store product IDs and their quantities

    // Loop through each item in the webhook data
    foreach ($data['item_lines'] as $item) {
        $product_name = sanitize_text_field($item['product_name']);
        $quantity = absint($item['product_quantity']);
        $price = floatval($item['unit_price']);

        // Find the product ID by name and price
        $product_id = find_product_by_name_and_price($product_name, $price);

        // Log product details
        error_log("Product Name: $product_name, Price: $price, Quantity: $quantity, Product ID: $product_id");

        // Collect product IDs and their quantities
        if ($product_id) {
            for ($i = 0; $i < $quantity; $i++) {
                $product_ids_quantities[] = $product_id;
            }
        }
    }

    // Generate the checkout URL with product IDs and quantities
    $checkout_url = wc_get_checkout_url() . '?add-to-cart=' . implode(',', $product_ids_quantities);
	
	// Shorten the URL
    $shortURL = shortenURL($checkout_url);

	// Remove "https://" from the beginning of $shortURL
    $shortURLM = preg_replace('#^https?://#', '', $shortURL);
	
    // Construct message for WhatsApp
    $cart_total = $data['cart_total'];
    $message = "*Your checkout link 🛒*\n\nDear $customer_name,\n\nYou have items in your cart ready for checkout.\n\n";
    $message .= "====================================\n";
    $message .= "Qty\t\tPrice\t\tProduct\n";
    $message .= "====================================\n";

    foreach ($data['item_lines'] as $item) {
        $quantity = absint($item['product_quantity']);
        $name = sanitize_text_field($item['product_name']);
        $price = floatval($item['unit_price']);
        $message .= "$quantity ×\t\t₹$price\t\t$name\n";
    }

    $message .= "=====================================\n\n";
    $message .= "*Total Value:* ₹$cart_total\n\n";
    $message .= "*Checkout Link:* $shortURLM\n\n";
    $message .= "➕ Shipping & Tax at checkout\n\n";
    $message .= "Thanks\nXpressBot";

    // Send a message via WhatsApp API
    $result = send_whatsapp_message($phone_number, $message);
}

/*************************************************************************************************************************************************/
/************************************ Below code will create a short link for above checkout link ************************************************/
/*************************************************************************************************************************************************/
              
// Function to shorten a URL using Short.io
function shortenURL($url) {
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.short.io/links",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode(array(
            'originalURL' => $url,
            'domain' => '<add your sub domin>'
        )),
        CURLOPT_HTTPHEADER => array(
            "authorization: <add your secret key>",
            "content-type: application/json"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        error_log("cURL Error: " . $err);
        return false;
    }

    $response = json_decode($response, true);
    return $response['secureShortURL'] ?? false;
}

 /*************************************************************************************************************************************************/
/************************************ Below code will help to find product id based on product name and price ************************************/
/*************************************************************************************************************************************************/

// Find product ID by name and price
function find_product_by_name_and_price($product_name, $price) {
    global $wpdb;

    // Extract the substring before the hyphen ("-")
    $product_name_parts = explode('-', $product_name);
    $product_name_trimmed = trim($product_name_parts[0]);

    $product_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT p.ID
            FROM {$wpdb->prefix}posts p
            INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
            WHERE p.post_title LIKE %s
            AND pm.meta_key = '_price'
            AND pm.meta_value = %f
            AND p.post_type = 'product_variation'
            AND p.post_status = 'publish'
            LIMIT 1",
            '%' . $wpdb->esc_like($product_name_trimmed) . '%',
            $price
        )
    );

    return $product_id;
}

/*************************************************************************************************************************************************/
/************************************ Below code will help to send whatsapp message *************************************************************/
/*************************************************************************************************************************************************/

// Send a message via WhatsApp API
function send_whatsapp_message($phone_number, $message) {
    // Use the appropriate API URL and parameters for your WhatsApp service
    $api_url = "https://app.xpressbot.org/api/v1/whatsapp/send?apiToken=<your token>&phoneNumberID=<your whatsapp number ></your>&message=" . urlencode($message) . "&sendToPhoneNumber=" . $phone_number;

    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);

    return $response_code === 200;
}

// Hook to initialize your webhook endpoint
add_action('rest_api_init', 'register_webhook_listener_endpoint');

function register_webhook_listener_endpoint() {
    register_rest_route('webhook/v1', '/catalog', array(
        'methods' => 'POST',
        'callback' => 'handle_webhook_request_checkout',
    ));
}

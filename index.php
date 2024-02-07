//********************************************************************************/
//Step 1: Create a PHP website with domain like https://webhook.<your domin>.org and add this code to index.php
//Step 2: Add a webhook to your reseller eCommerce Catalog like https://webhook.<your domin>.org/index.php	
//Setp 3: Modify <Add your domain connected to short.io> with the short link URL
          //Modify <Add your key> with your short.io API key
          //Modify <Add your reseller domain> with your domain name
          //Modify <Add your reseller api key>
        //Modify <Add your phoneNumber id>
//********************************************************************************/
//If you need us to install & host this for you we can do for ₹1500 / Account. Contact on support@skyfree.org.in
//********************************************************************************/
          
<?php
// Function to process the webhook request
function process_webhook_request() {
    // Get the JSON data from the request body
    $request_body = file_get_contents('php://input');
    if (!$request_body) {
        error_log("No data received in webhook request");
        return;
    }

    $data = json_decode($request_body, true);

    if (!$data) {
        error_log("Failed to decode JSON from webhook request");
        return;
    }

    // Extract necessary data from the webhook
    $order_unique_id = isset($data['order_unique_id']) ? $data['order_unique_id'] : '';
 	$order_id = isset($data['order_id']) ? $data['order_id'] : '';
    $customer_name = isset($data['customer_name']) ? $data['customer_name'] : '';
    $cart_total = isset($data['cart_total']) ? $data['cart_total'] : '';
    $cart_currency = isset($data['cart_currency']) ? $data['cart_currency'] : '';
    $chat_id = isset($data['customer_phone_number']) ? $data['customer_phone_number'] : '';
    $item_lines = isset($data['item_lines']) ? $data['item_lines'] : [];

    if (!$order_unique_id || !$chat_id) {
        error_log("Required data missing from webhook request");
        return;
    }

    // Construct the original URL
    $originalURL = "https://<Add your reseller domain>/c/" . $order_unique_id;

    // Shorten the URL
    $shortURL = shortenURL($originalURL);

    if (!$shortURL) {
        error_log("Failed to shorten URL");
        return;
    }

    // Construct the message
    $message = construct_message($order_id, $customer_name, $cart_currency, $cart_total, $item_lines, $shortURL);

    // Send the message via WhatsApp API
    $result = sendWhatsAppMessage($chat_id, $message);

    if ($result !== true) {
        error_log("Failed to send WhatsApp message: " . $result);
    }
}

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
            'domain' => '<Add your domain connected to short.io>'
        )),
        CURLOPT_HTTPHEADER => array(
            "authorization: <Add your key>",
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

// Function to construct the message
function construct_message($order_id, $customer_name, $cart_currency, $cart_total, $item_lines, $shortURL) {
  
 	 // Remove "https://" from the beginning of $shortURL
    $shortURL = preg_replace('#^https?://#', '', $shortURL);
  
    $message = "*Your order checkout link 🛒*\n\nDear $customer_name,\n\nYour order *#$order_id* is ready for payment.\n\n";
    $message .= "==============================================\n";
    $message .= "Qty\t\tPrice\t\tProduct\n";
    $message .= "==============================================\n";

    foreach ($item_lines as $item) {
        $quantity = $item['product_quantity'];
        $name = $item['product_name'];
        $price = $item['unit_price'];
        $message .= "$quantity ×\t\t₹$price\t\t$name\n";
    }

    $message .= "==============================================\n\n";
    $message .= "*Total Value:* ₹$cart_total\n\n";
	$message .= "*Payment Link:* $shortURL\n\n";
	$message .= "➕ Shipping & Tax at checkout\n\n";
  	$message .= "Thanks";

    return $message;
}

// Function to send a message via WhatsApp API
function sendWhatsAppMessage($phoneNumber, $message) {
    $apiURL = "https://<Add your reseller domain>/api/v1/whatsapp/send?apiToken=<Add your reseller api key>&phoneNumberID=<Add your phoneNumber id>&message=" . urlencode($message) . "&sendToPhoneNumber=" . $phoneNumber;

    $response = file_get_contents($apiURL);
    if ($response === false) {
        return "Failed to connect to WhatsApp API";
    }
    return true;
}

// Assuming this script is the endpoint for the webhook
process_webhook_request();
?>

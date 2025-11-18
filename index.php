<?php
// Log the raw input to a file for debugging
file_put_contents('webhook_log.txt', file_get_contents('php://input') . "\n", FILE_APPEND);

include 'fonnte.php';
include 'chatbot.php';

// Get the JSON data from the request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Check if the data is valid
if (isset($data['sender']) && isset($data['message'])) {
    $sender = $data['sender'];
    $message = $data['message'];

    // Get the chatbot's response
    $response = get_response($message);

    // Send the response back to the user
    send_whatsapp_message($sender, $response);
}
?>

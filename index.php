<?php
// Log the raw input to a file for debugging
file_put_contents('webhook_log.txt', file_get_contents('php://input') . "\n", FILE_APPEND);

// Prevent script from stopping if the client (Fonnte) disconnects due to timeout
ignore_user_abort(true);
set_time_limit(0); // Allow infinite execution time

include 'fonnte.php';
include 'chatbot.php';

// Get the JSON data from the request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Check if the data is valid
if (isset($data['sender']) && isset($data['message'])) {
    $sender = $data['sender'];
    $original_message = $data['message'];
    // $message = strtolower($original_message); // Not needed if not matching local keywords

    // --- Logging Start ---
    $log = "========================\n";
    $log .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $log .= "Sender: $sender\n";
    $log .= "Original Message: $original_message\n";
    // $log .= "Lowercase Message: $message\n"; // Not needed
    $log .= "API Keys Loaded: " . count($GLOBALS['apiKeys']) . "\n";
    // --- Logging End ---

    // Construct the prompt for Gemini, asking for a detailed, structured response
    // $prompt = "Jawab pertanyaan berikut sesuai format yang diminta di instruksi sistem (1 paragraf pembuka, lalu poin-poin, dan diakhiri dengan 1 paragraf penutup). Pertanyaan: " . $original_message;
    $log .= "Action: Processing user message.\nMessage: " . $original_message . "\n";
    $response = get_response($original_message);

    // --- Logging ---
    $log .= "Final Response from Gemini: " . ($response ? substr($response, 0, 100) . "..." : 'No response') . "\n";
    
    // Attempt to send the response.
    // Clean the response to remove any potential invalid characters
    $cleanResponse = strip_tags($response);
    // $cleanResponse = preg_replace('/[^\x20-\x7E\n\r\t]/', '', $cleanResponse); // Removed aggressive regex
    $cleanResponse = trim($cleanResponse);

    // Log the cleaned message
    $log .= "Sending Single Message (Length: " . strlen($cleanResponse) . "): " . $cleanResponse . "\n";
    
    $fonnteResponse = send_whatsapp_message($sender, $cleanResponse);
    $log .= "Fonnte Response: " . $fonnteResponse . "\n";
    
    file_put_contents('debug_log.txt', $log, FILE_APPEND);
    // --- Logging End ---
}
?>
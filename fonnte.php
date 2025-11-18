<?php
function send_whatsapp_message($to, $message) {
    $api_key = getenv('FONNTE_API_KEY');
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.fonnte.com/send',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => array(
        'target' => $to,
        'message' => $message,
        'countryCode' => '62', //optional
      ),
      CURLOPT_HTTPHEADER => array(
        "Authorization: $api_key"
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}
?>
<?php
ini_set('max_execution_time', 120); // Set PHP max execution time to 120 seconds

// --- Gemini API Integration with Failover ---

// Daftar API Key Anda untuk failover
$apiKeys = [
    'AIzaSyC4IQ5JW_49Ry00FApxZbJbjmiCYlQX-78',
    'AIzaSyByiFAPuC50IPFvWVtkzO8PujX53pbo-Js',
];

// Model yang akan digunakan. Kita kembali ke flash karena ini yang paling mungkin tersedia untuk Anda.
$model = 'gemini-2.5-pro';
$apiVersion = 'v1'; // Versi API yang akan digunakan

/**
 * Mengirim pesan ke Gemini API dan mendapatkan respons, dengan logika failover.
 *
 * @param string $message Pesan dari pengguna.
 * @return string Jawaban dari chatbot.
 */
function get_response($message) {
    global $apiKeys, $model, $apiVersion;

    $lastErrorResult = null; // Untuk menyimpan detail error terakhir jika semua key gagal

    // Instruksi sistem (tetap sama)
    $system_instruction = "Anda adalah 'WoundCare', sebuah chatbot asisten ahli untuk panduan penanganan luka. Peran Anda adalah memberikan informasi yang detail, spesifik, dan mudah dipahami. Instruksi Tambahan: Jangan gunakan karakter Markdown seperti *, #, atau ** dalam jawaban Anda.";

    $data = [
        'contents' => [['parts' => [['text' => $system_instruction . "\n\nPertanyaan Pengguna: " . $message]]]],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
        ]
    ];
    $jsonData = json_encode($data);

    // Loop melalui setiap API key
    foreach ($apiKeys as $index => $apiKey) {
        $geminiApiUrl = "https://generativelanguage.googleapis.com/{$apiVersion}/models/{$model}:generateContent?key={$apiKey}";

        $ch = curl_init($geminiApiUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Batas waktu 60 detik

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $lastErrorResult = ['curl_error' => $curlError, 'key_index' => $index];
            continue; // Coba key berikutnya jika cURL error
        }

        $result = json_decode($response, true);
        $generatedText = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

        // Jika berhasil mendapatkan teks dan teks tersebut tidak kosong setelah di-trim
        if ($generatedText && trim($generatedText) !== '') {
            return $generatedText;
        }

        // Jika gagal, simpan detail errornya
        $lastErrorResult = $result;
        $lastErrorResult['key_index'] = $index;

        // Periksa kode error. Jika bukan error yang bisa di-retry (bukan 503 atau 429),
        // maka tidak perlu mencoba key lain karena masalahnya fundamental.
        $errorCode = $result['error']['code'] ?? 0;
        if ($errorCode !== 503 && $errorCode !== 429) {
            break; // Keluar dari loop jika error tidak bisa di-retry
        }
    }

    // Jika loop selesai dan tidak ada jawaban (semua key gagal)
    error_log("Gemini API Failover Error (last attempt): " . print_r($lastErrorResult, true));
    return "Maaf, terjadi masalah saat menghubungi layanan AI. Semua upaya gagal. Ini mungkin karena server sedang sibuk atau ada masalah dengan kuota API. Silakan coba lagi nanti.";
}
?>
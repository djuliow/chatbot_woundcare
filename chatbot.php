<?php
ini_set('max_execution_time', 120); // Set PHP max execution time to 120 seconds

/**
 * Loads environment variables from a .env file.
 * This is a simplified implementation since Composer is not available for phpdotenv.
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, "'\""); // Strip single and double quotes

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load environment variables
loadEnv(__DIR__ . '/.env');

// --- Gemini API Integration with Failover ---

// Daftar API Key Anda untuk failover
$apiKeys = [
    getenv('GEMINI_API_KEY_1'),
    getenv('GEMINI_API_KEY_2'),
    getenv('GEMINI_API_KEY_3'),
    getenv('GEMINI_API_KEY_4'),
];

// Filter out any null or empty keys if they are not set in .env
$apiKeys = array_filter($apiKeys);

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

    // Defensive check: Ensure API keys are loaded.
    if (empty($apiKeys)) {
        error_log("FATAL: No API keys loaded. Check .env file and permissions.");
        return "ERROR: Konfigurasi API Key tidak ditemukan. Silakan periksa file .env Anda dan pastikan sudah terisi dengan benar.";
    }

    $lastErrorResult = null; // Untuk menyimpan detail error terakhir jika semua key gagal

    // Instruksi sistem (tetap sama)
    $system_instruction = "Anda adalah 'WoundCare', sebuah chatbot asisten ahli untuk panduan penanganan luka. Peran Anda adalah memberikan jawaban yang sangat detail dan terstruktur. Format jawaban harus: Dimulai dengan sapaan singkat (misal: 'Selamat datang di WoundCare.'), diikuti satu baris kosong, lalu 1 paragraf pembuka, beberapa poin-poin penting, dan diakhiri dengan 1 paragraf penutup. Instruksi Tambahan: Jangan gunakan karakter Markdown seperti *, #, atau ** dalam jawaban Anda.";

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
        // Skip if API key is empty
        if (empty($apiKey)) {
            continue;
        }

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
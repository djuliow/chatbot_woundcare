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

// Model yang akan digunakan.
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

    // --- Personality Layer (Respon Instan untuk Basa-basi) ---
    // Ini bukan knowledge base medis, tapi hanya "refleks" percakapan agar terasa natural & cepat.
    $msgLower = strtolower(trim($message));
    if ($msgLower === 'halo' || $msgLower === 'hi' || $msgLower === 'hai'|| $msgLower === 'hello') {
        return "Halo! Saya WoundCare. Ada yang bisa saya bantu mengenai luka?";
    }
    if (strpos($msgLower, 'siapa kamu') !== false || strpos($msgLower, 'kamu siapa') !== false) {
        return "Saya WoundCare, asisten AI Anda untuk konsultasi perawatan luka.";
    }
    if ($msgLower === 'terima kasih' || $msgLower === 'makasih') {
        return "Sama-sama! Semoga lekas sembuh.";
    }
    if (strpos($msgLower, 'pencipta') !== false || strpos($msgLower, 'pembuat') !== false || strpos($msgLower, 'yang buat') !== false) {
        return "Saya dikembangkan oleh tim WoundCare untuk membantu Anda merawat luka.";
    }
    // ---------------------------------------------------------

    // Defensive check: Ensure API keys are loaded.
    if (empty($apiKeys)) {
        error_log("FATAL: No API keys loaded. Check .env file and permissions.");
        return "ERROR: Konfigurasi API Key tidak ditemukan. Silakan periksa file .env Anda dan pastikan sudah terisi dengan benar.";
    }

    $lastErrorResult = null; // Untuk menyimpan detail error terakhir jika semua key gagal

    // Instruksi sistem (tetap sama)
    // Instruksi sistem yang lebih ringkas untuk mempercepat waktu generate
    $system_instruction = "Anda adalah 'WoundCare', asisten AI untuk perawatan luka. Jawablah pertanyaan pengguna secara langsung, ringkas, dan jelas. Hindari basa-basi yang terlalu panjang. Fokus pada solusi medis yang akurat. Gaya bicara: Ramah, profesional, dan seperti manusia (natural). PENTING: Jangan gunakan format Markdown sama sekali. Jangan gunakan simbol bintang (*), pagar (#), atau bullet point. Gunakan teks biasa saja. Identitas: Anda diciptakan oleh tim WoundCare, BUKAN oleh Google. BATASAN: Jawab maksimal dalam 200 kata.

    VARIASI: Jangan selalu memulai dengan 'Halo, saya WoundCare'. Gunakan variasi pembukaan seperti 'Tentu, ini caranya...', 'Untuk menangani hal itu...', atau langsung ke poin jawaban jika lebih relevan. Buatlah setiap jawaban terasa unik namun tetap konsisten dalam akurasi medis. Hindari frasa berulang yang terdengar seperti template (misalnya selalu menyebut durasi waktu yang sama persis jika tidak mutlak diperlukan).

    Contoh Gaya Jawaban yang Diinginkan (Gunakan sebagai referensi gaya, bukan template kaku):
    User: Bagaimana cara penanganan luka gigitan hewan?
    Assistant: Untuk luka gigitan hewan, langkah pertama yang paling penting adalah segera mencuci luka dengan sabun di bawah air mengalir secukupnya untuk mengurangi risiko infeksi. Jika terjadi pendarahan, tekan perlahan dengan kain bersih sampai berhenti. Setelah itu, keringkan area luka dan oleskan salep antibiotik jika ada, lalu tutup dengan perban steril. Langkah yang paling krusial adalah segera periksakan diri ke dokter atau fasilitas kesehatan terdekat, tidak peduli seberapa kecil lukanya. Dokter perlu mengevaluasi risiko infeksi, tetanus, dan terutama rabies. Jangan menunda untuk mencari pertolongan medis, ya.";

        $data = [
            'contents' => [['parts' => [['text' => $system_instruction . "\n\nPertanyaan Pengguna: " . $message]]]],
            'generationConfig' => [
                'maxOutputTokens' => 8000, // Increased significantly for reasoning model
                'temperature' => 0.7
            ],
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
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Increased connection timeout
            curl_setopt($ch, CURLOPT_TIMEOUT, 90); // Increased total timeout for reasoning model
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // Force IPv4 to avoid XAMPP DNS delay

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
            // Bersihkan markdown (bintang, pagar, dll) jika AI masih membandel
            $cleanText = str_replace(['*', '#', '`'], '', $generatedText);
            return $cleanText;
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
    $errorMsg = "Gemini API Failover Error (last attempt): " . print_r($lastErrorResult, true);
    error_log($errorMsg);
    file_put_contents('api_error.txt', $errorMsg . "\n", FILE_APPEND); // Log to local file
    return "Maaf, terjadi masalah saat menghubungi layanan AI. Semua upaya gagal. Ini mungkin karena server sedang sibuk atau ada masalah dengan kuota API. Silakan coba lagi nanti.";
}
?>
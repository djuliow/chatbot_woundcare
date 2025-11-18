# WoundCare WhatsApp Chatbot

WoundCare adalah sebuah chatbot WhatsApp yang dirancang untuk memberikan informasi dan panduan pertolongan pertama pada berbagai jenis luka. Chatbot ini didukung oleh Google Gemini API untuk memberikan jawaban yang informatif dan relevan.

## ✨ Fitur

- **Panduan Berbagai Jenis Luka:** Memberikan informasi penanganan untuk luka bakar, luka sayat, luka gigitan hewan, dan lainnya.
- **Respons Cerdas:** Menggunakan Google Gemini API untuk memahami pertanyaan dan memberikan jawaban yang kontekstual.
- **Integrasi WhatsApp:** Berinteraksi langsung melalui WhatsApp menggunakan Fonnte API.
- **Manajemen API Key yang Aman:** Menggunakan file `.env` untuk menyimpan API key agar tidak terekspos di repositori.
- **Logika Failover:** Memiliki beberapa API key Gemini dan akan mencoba key berikutnya jika salah satu gagal.

## 🚀 Teknologi yang Digunakan

- **Backend:** PHP
- **AI Engine:** Google Gemini Pro
- **WhatsApp Gateway:** [Fonnte API](https://fonnte.com/)
- **Web Server:** XAMPP (atau server lain yang mendukung PHP)

## ⚙️ Instalasi dan Konfigurasi

Berikut adalah langkah-langkah untuk menjalankan proyek ini di lingkungan lokal Anda.

### 1. Prasyarat

- [XAMPP](https://www.apachefriends.org/index.html) atau web server sejenis dengan PHP.
- API Key untuk [Google Gemini](https://aistudio.google.com/app/apikey).
- API Key untuk [Fonnte](https://fonnte.com/).

### 2. Clone Repositori

Clone repositori ini ke direktori `htdocs` di dalam instalasi XAMPP Anda.

```bash
git clone [URL_REPOSITORI_ANDA] C:/xampp/htdocs/ProjekML
```

### 3. Konfigurasi Environment Variables

Chatbot ini memerlukan API key untuk berfungsi.

1.  Buat salinan dari file `.env.example` dan beri nama `.env`.
    ```bash
    copy .env.example .env
    ```
2.  Buka file `.env` dan isi dengan API key yang Anda miliki.

    ```dotenv
    # Gemini API Keys (isi salah satu atau lebih)
    GEMINI_API_KEY_1="MASUKKAN_API_KEY_GEMINI_ANDA_DI_SINI"
    GEMINI_API_KEY_2="..."
    GEMINI_API_KEY_3="..."
    GEMINI_API_KEY_4="..."
    ```

### 4. Konfigurasi Webhook Fonnte

1.  Jalankan Apache server Anda melalui XAMPP Control Panel.
2.  Pastikan proyek ini bisa diakses melalui web. Anda mungkin perlu menggunakan layanan seperti [ngrok](https://ngrok.com/) untuk mengekspos server lokal Anda ke internet agar bisa diakses oleh Fonnte.
3.  Masuk ke dashboard Fonnte Anda.
4.  Atur URL webhook Anda untuk menunjuk ke file `index.php` di server Anda. Contoh: `https://URL_ANDA/ProjekML/index.php`.

##  usage

Setelah semua konfigurasi selesai, Anda bisa mulai mengirim pesan ke nomor WhatsApp yang terhubung dengan Fonnte. Chatbot akan secara otomatis merespons pertanyaan Anda.

**Contoh Pertanyaan:**
- `hai`
- `bagaimana penanganan luka bakar?`
- `apa saja tanda-tanda infeksi?`
- `penanganan luka gigitan hewan`

## 📂 Struktur File

Berikut adalah penjelasan singkat mengenai file-file penting dalam proyek ini:

-   `index.php`: Titik masuk utama (webhook) yang menerima pesan dari Fonnte, memprosesnya, dan mengirim balasan.
-   `chatbot.php`: Berisi logika untuk berinteraksi dengan Google Gemini API, termasuk fungsi `get_response()`.
-   `fonnte.php`: Berisi fungsi `send_whatsapp_message()` untuk mengirim pesan balasan melalui Fonnte API.
-   `knowledge.php`: (Saat ini tidak digunakan) Berisi data statis yang sebelumnya digunakan sebagai basis pengetahuan lokal.
-   `.env`: File untuk menyimpan kredensial dan API key (tidak dilacak oleh Git).
-   `.env.example`: Contoh file `.env`.
-   `.gitignore`: Berisi daftar file dan direktori yang diabaikan oleh Git.
-   `webhook_log.txt`: File log untuk mencatat semua data mentah yang masuk ke webhook (diabaikan oleh Git).
-   `debug_log.txt`: File log untuk debugging alur kerja internal (diabaikan oleh Git).
-   `README.md`: File yang sedang Anda baca ini.

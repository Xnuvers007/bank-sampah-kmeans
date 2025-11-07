<?php
/**
 * CSRF Protection Class
 * Melindungi aplikasi dari Cross-Site Request Forgery attacks
 */

class CSRF {
    /**
     * Generate CSRF token dan simpan di session
     * 
     * @return string Token yang di-generate
     */
    public static function generateToken() {
        // Pastikan session sudah dimulai
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Generate token baru jika belum ada atau expired
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        } else {
            // Regenerate token setiap 30 menit untuk keamanan ekstra
            if (time() - $_SESSION['csrf_token_time'] > 1800) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['csrf_token_time'] = time();
            }
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token dari request
     * 
     * @param string|null $token Token yang dikirim dari form
     * @return bool True jika valid, false jika tidak
     */
    public static function validateToken($token) {
        // Pastikan session sudah dimulai
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Cek apakah token ada di session
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        // Cek apakah token kosong
        if (empty($token)) {
            return false;
        }

        // Validasi token menggunakan hash_equals untuk mencegah timing attack
        $valid = hash_equals($_SESSION['csrf_token'], $token);

        // Optional: Auto-regenerate token setelah validasi sukses (one-time token)
        // Uncomment jika ingin menggunakan one-time token
        // if ($valid) {
        //     self::regenerateToken();
        // }

        return $valid;
    }

    /**
     * Generate hidden input field untuk form
     * 
     * @return string HTML input field
     */
    public static function getTokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Get token value (untuk AJAX requests)
     * 
     * @return string Token value
     */
    public static function getToken() {
        return self::generateToken();
    }

    /**
     * Regenerate token (untuk one-time use atau security refresh)
     */
    public static function regenerateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();

        return $_SESSION['csrf_token'];
    }

    /**
     * Validate token dan redirect jika invalid
     * 
     * @param string|null $token Token dari request
     * @param string $redirectUrl URL untuk redirect jika invalid
     * @param string $errorMessage Error message yang ditampilkan
     */
    public static function validateOrDie($token, $redirectUrl = null, $errorMessage = null) {
        if (!self::validateToken($token)) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $_SESSION['error'] = $errorMessage ?? 'Token keamanan tidak valid. Silakan coba lagi.';
            
            if ($redirectUrl) {
                header("Location: $redirectUrl");
            } else {
                // Redirect ke referer atau homepage
                $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
                header("Location: $referer");
            }
            exit;
        }
    }

    /**
     * Check if request is POST and validate CSRF
     * 
     * @param string $redirectUrl URL untuk redirect jika invalid
     * @return bool True jika valid POST request
     */
    public static function validatePostRequest($redirectUrl = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }

        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        
        if (!self::validateToken($token)) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $_SESSION['error'] = 'Token keamanan tidak valid. Silakan coba lagi.';
            
            if ($redirectUrl) {
                header("Location: $redirectUrl");
            } else {
                $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
                header("Location: $referer");
            }
            exit;
        }

        return true;
    }

    /**
     * Generate meta tag untuk AJAX requests (optional)
     * 
     * @return string HTML meta tag
     */
    public static function getMetaTag() {
        $token = self::generateToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Destroy CSRF token (untuk logout)
     */
    public static function destroyToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
    }
}
?>
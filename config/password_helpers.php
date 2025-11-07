<?php
class PasswordPolicy {
    private static $minLength = 8;
    private static $requireUppercase = true;
    private static $requireLowercase = true;
    private static $requireNumber = true;
    private static $requireSpecial = true;

    public static function validate($password) {
        $errors = [];

        // Check minimum length
        if (strlen($password) < self::$minLength) {
            $errors[] = "Password minimal " . self::$minLength . " karakter";
        }

        // Check uppercase
        if (self::$requireUppercase && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password harus mengandung huruf besar";
        }

        // Check lowercase
        if (self::$requireLowercase && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password harus mengandung huruf kecil";
        }

        // Check number
        if (self::$requireNumber && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password harus mengandung angka";
        }

        // Check special character
        if (self::$requireSpecial && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password harus mengandung karakter khusus (!@#$%^&*)";
        }

        return $errors;
    }

    public static function generate($length = 12) {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*()';

        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        return str_shuffle($password);
    }

    public static function strength($password) {
        $score = 0;

        // Length bonus
        $score += strlen($password) * 4;

        // Mixed case
        if (preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password)) {
            $score += 10;
        }

        // Numbers
        if (preg_match('/[0-9]/', $password)) {
            $score += 10;
        }

        // Special characters
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $score += 15;
        }

        // Penalty for common patterns
        if (preg_match('/(.)\1{2,}/', $password)) {
            $score -= 10;
        }

        // Return strength level
        if ($score < 30) return 'Lemah';
        if ($score < 60) return 'Sedang';
        if ($score < 90) return 'Kuat';
        return 'Sangat Kuat';
    }
}
?>
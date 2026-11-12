<?php

namespace App\Services\Auth;

/**
 * Verifica o parola fata de un hash WordPress, fara a avea vreodata parola in clar stocata.
 * Folosit o singura data per cont migrat: dupa primul login reusit parola este rehash-uita
 * cu algoritmul Laravel si hash-ul WordPress este sters.
 *
 * Formate acceptate:
 *  - $wp$2y$...  WordPress 6.8+: bcrypt peste HMAC-SHA384 al parolei
 *  - $2y$ / $2a$ bcrypt simplu (pluginuri de parole)
 *  - $P$ / $H$   phpass portabil (MD5 iterat), WordPress mai vechi de 6.8
 */
class WordPressPasswordVerifier
{
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function check(string $password, string $hash): bool
    {
        if ($hash === '' || strlen($password) > 4096) {
            return false;
        }

        if (str_starts_with($hash, '$wp')) {
            $prehashed = base64_encode(hash_hmac('sha384', trim($password), 'wp-sha384', true));

            return password_verify($prehashed, substr($hash, 3));
        }

        if (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$') || str_starts_with($hash, '$argon2')) {
            return password_verify($password, $hash);
        }

        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return hash_equals($hash, $this->phpass($password, $hash));
        }

        return false;
    }

    private function phpass(string $password, string $setting): string
    {
        $countLog2 = strpos(self::ITOA64, $setting[3]);
        if ($countLog2 < 7 || $countLog2 > 30 || strlen($setting) < 12) {
            return '*';
        }

        $count = 1 << $countLog2;
        $salt = substr($setting, 4, 8);
        $hash = md5($salt.$password, true);
        do {
            $hash = md5($hash.$password, true);
        } while (--$count);

        return substr($setting, 0, 12).$this->encode64($hash, 16);
    }

    private function encode64(string $input, int $count): string
    {
        $output = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $output .= self::ITOA64[$value & 0x3F];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= self::ITOA64[($value >> 6) & 0x3F];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= self::ITOA64[($value >> 12) & 0x3F];
            if ($i++ >= $count) {
                break;
            }
            $output .= self::ITOA64[($value >> 18) & 0x3F];
        } while ($i < $count);

        return $output;
    }
}

<?php
namespace Story\Util;

class AES128
{
    public static function encrypt($key, $value)
    {
        $padding_size = 16 - (strlen($value) % 16);
        $value .= str_repeat(chr($padding_size), $padding_size);
        $output = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $value, MCRYPT_MODE_CBC, str_repeat(chr(0), 16));
        return base64_encode($output);
    }

    public static function decrypt($key, $value)
    {
        $value = base64_decode($value);
        $output = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $value, MCRYPT_MODE_CBC, str_repeat(chr(0), 16));

        $value_length = strlen($value);

        if ($value_length % 16 > 0) {
            $output = "";
        }

        $padding_size = ord($output{$value_length - 1});
        if ($padding_size < 1  || $padding_size > 16) {
            $output = "";
        }

        for ($i=0; $i<$padding_size; $i++) {
            if (ord($output{$value_length - $i - 1}) != $padding_size) {
                $output = "";
            }
        }

        $output = substr($output, 0, $value_length - $padding_size);
        return $output;
    }
} 
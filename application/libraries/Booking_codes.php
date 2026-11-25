<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Booking_codes
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function generate()
    {
        $code='';
        for ($i=0;$i<6;$i++) $code.=self::ALPHABET[random_int(0,strlen(self::ALPHABET)-1)];
        return $code;
    }

    public function normalize($value)
    {
        $code=strtoupper(trim((string)$value));
        return preg_match('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}$/',$code)?$code:NULL;
    }
}

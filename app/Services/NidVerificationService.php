<?php


namespace App\Services;


use thiagoalessio\TesseractOCR\TesseractOCR;

class NidVerificationService
{
    public function getNidNumber($path): string
    {
        $str = (new TesseractOCR($path))
            ->lang('eng', 'ben')
            ->run();
        $str = strtolower(str_replace(':', '.', str_replace(' ', '', $str)));
        preg_match_all('/idno.([\d]+)/', $str,$matches);
        return (!empty($matches[1])) ? $matches[1][0] : "";
    }
}

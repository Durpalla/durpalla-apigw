#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$locales = array_keys((require $root.'/config/localization.php')['locales']);

$baseAttributes = [
    'required' => 'The :attribute field is required.',
    'string' => 'The :attribute field must be a string.',
    'email' => 'The :attribute field must be a valid email address.',
    'min' => [
        'numeric' => 'The :attribute field must be at least :min.',
        'string' => 'The :attribute field must be at least :min characters.',
    ],
    'max' => [
        'string' => 'The :attribute field must not be greater than :max characters.',
    ],
];

$localeOverrides = [
    'bn' => [
        'required' => ':attribute আবশ্যক।',
        'string' => ':attribute অবশ্যই একটি টেক্সট হতে হবে।',
        'email' => ':attribute একটি বৈধ ইমেইল হতে হবে।',
    ],
    'hi' => [
        'required' => ':attribute फ़ील्ड आवश्यक है।',
        'string' => ':attribute फ़ील्ड एक पाठ होना चाहिए।',
        'email' => ':attribute एक मान्य ईमेल होना चाहिए।',
    ],
    'ar' => [
        'required' => 'حقل :attribute مطلوب.',
        'string' => 'يجب أن يكون حقل :attribute نصًا.',
        'email' => 'يجب أن يكون :attribute بريدًا إلكترونيًا صالحًا.',
    ],
    'zh' => [
        'required' => ':attribute 为必填项。',
        'string' => ':attribute 必须是字符串。',
        'email' => ':attribute 必须是有效的电子邮件地址。',
    ],
    'ur' => [
        'required' => ':attribute فیلڈ درکار ہے۔',
        'string' => ':attribute فیلڈ متن ہونا چاہیے۔',
        'email' => ':attribute درست ای میل ہونا چاہیے۔',
    ],
    'fa' => [
        'required' => 'فیلد :attribute الزامی است.',
        'string' => 'فیلد :attribute باید متن باشد.',
        'email' => 'فیلد :attribute باید یک ایمیل معتبر باشد.',
    ],
    'tr' => [
        'required' => ':attribute alanı zorunludur.',
        'string' => ':attribute alanı metin olmalıdır.',
        'email' => ':attribute geçerli bir e-posta olmalıdır.',
    ],
    'es' => [
        'required' => 'El campo :attribute es obligatorio.',
        'string' => 'El campo :attribute debe ser texto.',
        'email' => 'El campo :attribute debe ser un correo válido.',
    ],
    'it' => [
        'required' => 'Il campo :attribute è obbligatorio.',
        'string' => 'Il campo :attribute deve essere testo.',
        'email' => 'Il campo :attribute deve essere un\'email valida.',
    ],
];

foreach ($locales as $locale) {
    $dir = $root.'/lang/'.$locale;
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $validation = ['attributes' => []];
    $validation['required'] = $localeOverrides[$locale]['required'] ?? $baseAttributes['required'];
    $validation['string'] = $localeOverrides[$locale]['string'] ?? $baseAttributes['string'];
    $validation['email'] = $localeOverrides[$locale]['email'] ?? $baseAttributes['email'];
    $validation['min'] = $baseAttributes['min'];
    $validation['max'] = $baseAttributes['max'];

    $content = "<?php\n\nreturn ".var_export($validation, true).";\n";
    file_put_contents($dir.'/validation.php', $content);
}

echo 'Seeded validation.php for '.count($locales)." locales.\n";

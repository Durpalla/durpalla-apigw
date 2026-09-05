#!/usr/bin/env php
<?php

/**
 * Create / refresh all supported locale files for every app on APIGW.
 *
 * Usage: php scripts/i18n/generate-all-locale-files.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

passthru('php scripts/i18n/export-all.php', $exportCode);
if ($exportCode !== 0) {
    exit($exportCode);
}

passthru('php scripts/i18n/export-api-messages.php', $apiCode);
if ($apiCode !== 0) {
    exit($apiCode);
}

passthru('php scripts/i18n/seed-validation-lang.php', $valCode);
if ($valCode !== 0) {
    exit($valCode);
}

passthru('python3 scripts/i18n/seed-web-customer-translations.py', $webCode);
if ($webCode !== 0) {
    exit($webCode);
}

passthru('php scripts/i18n/seed-api-translations.php', $seedCode);
if ($seedCode !== 0) {
    exit($seedCode);
}

if (getenv('SKIP_UI_TRANSLATE') !== '1') {
    passthru('php scripts/i18n/seed-ui-remote-translations.php', $uiCode);
    if ($uiCode !== 0) {
        exit($uiCode);
    }
}

passthru('php scripts/i18n/validate-localizations.php', $validateCode);
exit($validateCode);

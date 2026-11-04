<?php
/**
 * Store settings that the owner can change in the admin Settings page (stored in the `settings` table).
 * Every setting has a default, so the site also works before the table exists or is filled in.
 */

const SETTINGS_DEFAULTS = [
    'store_name'      => '[Clothing Brand]',
    'support_email'   => '',
    'support_phone'   => '',
    'store_address'   => '',
    'currency'        => 'LKR',
    'delivery_fee'    => '350',
    'alert_recipients' => '',      // administrators who get an e-mail for every new order (comma separated)
    'alert_extra'     => '',      // optional extra recipients
    'alert_enabled'   => '1',
];

const CURRENCIES = ['LKR', 'USD', 'EUR', 'GBP', 'INR'];

/** All settings (defaults + saved values). Pass true to reload after saving. */
function settings_all(bool $refresh = false): array
{
    static $cache = null;

    if ($cache === null || $refresh) {
        $cache = SETTINGS_DEFAULTS;
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $row) {
                if (array_key_exists($row['setting_key'], SETTINGS_DEFAULTS)) {
                    $cache[$row['setting_key']] = (string) $row['setting_value'];
                }
            }
        } catch (Throwable $e) {
            // the settings table does not exist yet (run migration.php): defaults are used
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    return $all[$key] ?? $default;
}

/** Save several settings at once (unknown keys are ignored). */
function settings_save(array $values): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($values as $key => $value) {
        if (array_key_exists($key, SETTINGS_DEFAULTS)) {
            $stmt->execute([$key, (string) $value]);
        }
    }
    settings_all(true);
}

function app_name(): string
{
    return trim(setting('store_name')) !== '' ? setting('store_name') : SETTINGS_DEFAULTS['store_name'];
}

function currency_code(): string
{
    return in_array(setting('currency'), CURRENCIES, true) ? setting('currency') : 'LKR';
}

function delivery_fee(): float
{
    return max(0.0, (float) setting('delivery_fee', '350'));
}

function support_email(): string
{
    return trim(setting('support_email'));
}

function support_phone(): string
{
    return trim(setting('support_phone'));
}

/** E-mail addresses that receive the "new order" alert. */
function alert_recipients(): array
{
    if (setting('alert_enabled') !== '1') {
        return [];
    }
    $list = preg_split('/[\s,;]+/', setting('alert_recipients') . ',' . setting('alert_extra'), -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_unique(array_filter($list, static fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL))));
}

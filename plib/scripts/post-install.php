<?php
$out = [];
$out[] = '=== LEGACY FIX ' . date('c') . ' ===';

$index = '/var/www/vhosts/bahman.ir/httpdocs/index.php';
if (!is_readable($index) || !is_writable($index)) {
    $out[] = 'index not writable/readable';
    echo implode("\n", $out) . "\n";
    exit(1);
}

$raw = file_get_contents($index);
$backup = $index . '.bak-legacy-' . date('YmdHis');
if (!@copy($index, $backup)) {
    $out[] = 'backup failed';
} else {
    $out[] = 'backup=' . $backup;
}

// Show current snippet
if (preg_match('/\/\/ Per-template legacy redirects.*?^\}$/ms', $raw, $m)) {
    $out[] = "BEFORE:\n" . $m[0];
} else {
    $out[] = 'BEFORE: could not match full block';
    foreach (explode("\n", $raw) as $i => $line) {
        if (stripos($line, 'legacy') !== false || stripos($line, 'TEMPLATE_NAME') !== false && $i > 100 && $i < 160) {
            $out[] = 'L' . ($i + 1) . ': ' . rtrim($line);
        }
    }
}

$old1 = "\$legacyApplyFn = TEMPLATE_NAME . '_apply_legacy_redirects';";
$new1 = "\$legacyApplyFn = __NAMESPACE__ . '\\\\' . TEMPLATE_NAME . '_apply_legacy_redirects';";

$old2 = '$legacyApplyFn = TEMPLATE_NAME . \'_apply_legacy_redirects\';';
$new2 = '$legacyApplyFn = __NAMESPACE__ . \'\\\\\' . TEMPLATE_NAME . \'_apply_legacy_redirects\';';

$changed = false;
if (strpos($raw, $old1) !== false) {
    $raw2 = str_replace($old1, $new1, $raw);
    $changed = true;
    $out[] = 'replaced double-quoted style';
} elseif (strpos($raw, "__NAMESPACE__ . '\\\\' . TEMPLATE_NAME . '_apply_legacy_redirects'") !== false
    || strpos($raw, '__NAMESPACE__ . "\\\\" . TEMPLATE_NAME . \'_apply_legacy_redirects\'') !== false
    || preg_match('/legacyApplyFn\s*=\s*__NAMESPACE__/', $raw)) {
    $raw2 = $raw;
    $out[] = 'already uses __NAMESPACE__';
} else {
    // Broader replace
    $raw2 = preg_replace(
        '/\$legacyApplyFn\s*=\s*TEMPLATE_NAME\s*\.\s*[\'"]_apply_legacy_redirects[\'"]\s*;/',
        "\$legacyApplyFn = __NAMESPACE__ . '\\\\' . TEMPLATE_NAME . '_apply_legacy_redirects';",
        $raw,
        1,
        $count
    );
    if ($count > 0) {
        $changed = true;
        $out[] = 'replaced via regex';
    } else {
        $raw2 = $raw;
        $out[] = 'NO MATCH to replace — manual needed';
    }
}

if ($changed) {
    if (file_put_contents($index, $raw2) === false) {
        $out[] = 'write failed';
    } else {
        $out[] = 'index.php updated';
        // verify
        clearstatcache(true, $index);
        $verify = file_get_contents($index);
        if (preg_match('/\$legacyApplyFn\s*=\s*.+/', $verify, $mm)) {
            $out[] = 'AFTER line: ' . trim($mm[0]);
        }
    }
}

// Quick functional check by including the apply path simulation
$myconf = '/var/www/vhosts/bahman.ir/httpdocs/libs/myconf.php';
$legacy = '/var/www/vhosts/bahman.ir/httpdocs/templates/bahman/legacy_redirects.php';
$out[] = 'TEMPLATE check via define scan:';
if (preg_match('/define\s*\(\s*"TEMPLATE_NAME"\s*,\s*"([^"]+)"/', file_get_contents($myconf), $m)) {
    $out[] = 'TEMPLATE_NAME=' . $m[1];
}

// HTTP retest
$out[] = '';
$out[] = '=== RETEST ===';
$cmd = 'curl -skI --connect-timeout 5 --max-time 15 -H "Host: bahman.ir" https://172.20.14.76/product/231 2>&1 | head -20';
$out[] = trim((string) shell_exec($cmd));
$out[] = '---';
$cmd = 'curl -skI --connect-timeout 5 --max-time 15 -H "Host: bahman.ir" https://172.20.14.76/product/231/FORCE-F38B 2>&1 | head -20';
$out[] = trim((string) shell_exec($cmd));
$out[] = '---';
$cmd = 'curl -skI --connect-timeout 5 --max-time 15 -H "Host: bahman.ir" https://172.20.14.76/product/716 2>&1 | head -20';
$out[] = trim((string) shell_exec($cmd));

$text = implode("\n", $out) . "\n";
@file_put_contents('/tmp/legacyfix_report.txt', $text);
@mkdir('/opt/psa/var/modules/legacyfix', 0755, true);
@file_put_contents('/opt/psa/var/modules/legacyfix/report.txt', $text);
echo $text;

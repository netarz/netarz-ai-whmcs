<?php
/**
 * Turns a staged copy of the NetArz edition into the Gisoo edition, in place.
 *
 *   php editions/gisoo/make-gisoo.php <staged dir>
 *
 * The Gisoo edition is the same code under its own names, so both can be
 * installed side by side without sharing a class, a function, a table or a
 * browser key:
 *   modules/addons/netarz_ai  ->  modules/addons/gisoo_ai  (module gisoo_ai, gisoo_ai_* functions)
 *   NetArz\WhmcsAi            ->  Gisoo\WhmcsAi
 *   mod_netarz_ai_* tables    ->  mod_gisoo_ai_*
 *   Brand::EDITION            ->  'gisoo' (every brand word and link follows from it)
 *
 * Addresses that must stay as they are (the repository, the netarz.ir API
 * address the Brand class names) are protected during the rename. Brand.php
 * itself only gets its namespace and edition changed. A tests/ folder in the
 * stage is renamed too, so the whole suite runs against the Gisoo edition.
 */

if ($argc < 2 || ! is_dir($argv[1])) {
    fwrite(STDERR, "usage: php make-gisoo.php <staged dir>\n");
    exit(64);
}

$root = rtrim($argv[1], '/');
$from = $root.'/modules/addons/netarz_ai';
$to = $root.'/modules/addons/gisoo_ai';
if (! is_dir($from)) {
    fwrite(STDERR, "no modules/addons/netarz_ai in $root\n");
    exit(65);
}

rename($from, $to);
rename($to.'/netarz_ai.php', $to.'/gisoo_ai.php');

$keep = [
    // Placeholders must not contain a brand word, or the rename below would change them too.
    'github.com/netarz/netarz-ai-whmcs' => "\x01K1\x01",
    'netarz.ir' => "\x01K2\x01",
];
$rename = ['NetArz' => 'Gisoo', 'NETARZ' => 'GISOO', 'netarz' => 'gisoo'];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$changed = 0;
foreach ($files as $file) {
    $path = $file->getPathname();
    if (strpos($path, '/vendor/') !== false || ! preg_match('/\.(php|js|cjs|css|json|xml|md|yml)$/', $path)) {
        continue;
    }

    $text = file_get_contents($path);
    $before = $text;

    if (substr($path, -strlen('/lib/Brand.php')) === '/lib/Brand.php') {
        $text = str_replace('namespace NetArz\WhmcsAi;', 'namespace Gisoo\WhmcsAi;', $text);
        $text = str_replace("public const EDITION = 'netarz';", "public const EDITION = 'gisoo';", $text);
    } else {
        $text = strtr($text, $keep);
        $text = strtr($text, $rename);
        $text = strtr($text, array_flip($keep));
    }

    if ($text !== $before) {
        file_put_contents($path, $text);
        $changed++;
    }
}

// Proof: nothing in the module still answers to the NetArz names.
$left = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($to, FilesystemIterator::SKIP_DOTS)) as $file) {
    if (substr($file->getPathname(), -strlen('/lib/Brand.php')) === '/lib/Brand.php') {
        continue;
    }
    $text = file_get_contents($file->getPathname());
    if (preg_match('/netarz_ai|NetArz\\\\WhmcsAi|mod_netarz/', $text)) {
        $left[] = substr($file->getPathname(), strlen($root) + 1);
    }
}
if ($left) {
    fwrite(STDERR, "NetArz names left in: ".implode(', ', $left)."\n");
    exit(1);
}

echo "gisoo edition: $changed files rewritten\n";

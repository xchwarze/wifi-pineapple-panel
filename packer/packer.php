<?php
# by DSR!
# from https://github.com/xchwarze/wifi-pineapple-panel

require_once __DIR__ . '/deps/vendor/autoload.php';
require_once __DIR__ . '/deps/PackPHP.php'; //from: https://github.com/hareko/php-application-packer

use MatthiasMullie\Minify;
use voku\helper\HtmlMin;

error_reporting(E_ALL);


if (!isset($_SERVER['argv']) && !isset($argv)) {
    echo "Please enable the register_argc_argv directive in your php.ini\n";
    exit(1);
} elseif (!isset($argv)) {
    $argv = $_SERVER['argv'];
}

if (!isset($argv[1])) {
    echo "Argument expected: path to folder\n";
    exit(1);
}


function getDirContents($dir) {
    $files = array(); 
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir)
    );

    foreach ($iterator as $file) {
        if ($file->isDir()){ 
            continue;
        }

        $files[] = $file->getPathname(); 
    }

    return $files;
}


echo "******* Project packer by DSR! *******\n";
echo "php deps: sudo apt-get update && sudo apt install php-xml php-mbstring\n\n";
$counter = 0;

echo "Target folder: {$argv[1]}\n";

foreach (getDirContents($argv[1]) as $file) {
    $info = pathinfo($file);
    if (isset($info['extension']) && !fnmatch('*.min', $info['filename'], FNM_CASEFOLD)) {
        try {
            switch ($info['extension']) {
                case 'js':
                    $counter++;
                    $minifier = new Minify\JS($file);
                    $minifier->minify($file);
                    break;
                case 'css':
                    $counter++;
                    $minifier = new Minify\CSS($file);
                    $minifier->minify($file);
                    break;
                case 'php':
                    $counter++;
                    $file_contents = file_get_contents($file);
                    $minified = PackPHP::minify($file_contents, ['min' => true]);
                    file_put_contents($file, $minified);
                    break;
                case 'html':
                    $counter++;                    
                    $file_contents = file_get_contents($file);
                    $minifier = new HtmlMin();
                    $minified = $minifier->minify($file_contents);
                    file_put_contents($file, $minified);
                    break;
            }        
        } catch (Exception $e) {
            echo "[ERROR!] " . $e->getMessage() . "\n";
        }
    }
}

echo "Processed files: {$counter}\n";

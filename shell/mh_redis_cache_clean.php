#!/usr/bin/env php
<?php
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);
$cliRun = (isset($_SERVER['TERM']) && 'dumb' != $_SERVER['TERM']);

$tryRoot = array('.');
$tryRoot[] = dirname(__DIR__);
$tryRoot[] = dirname(dirname($_SERVER['SCRIPT_FILENAME']));
if (!empty($_SERVER['PWD'])) {
    $tryRoot[] = $_SERVER['PWD'];
    $tryRoot[] = dirname($_SERVER['PWD']);
}
if (!empty($_SERVER['HOME'])) {
    $tryRoot[] = $_SERVER['HOME'] . '/httpdocs';
    $tryRoot[] = $_SERVER['HOME'] . '/magento';
    $tryRoot[] = $_SERVER['HOME'] . '/magento2';
    $tryRoot[] = $_SERVER['HOME'] . '/releases/current';
    $tryRoot[] = $_SERVER['HOME'] . '/deployment/current';
    $tryRoot[] = $_SERVER['HOME'] . '/htdocs';
    $tryRoot[] = $_SERVER['HOME'] . '/public_html';
}
$tryRoot = array_unique($tryRoot);

$mageRoot = false;
foreach ($tryRoot as $try) {
    $try = rtrim($try, '/');
    if (file_exists($try . '/lib/Credis/Client.php')) {
        $mageRoot = $try;
        /** @noinspection PhpIncludeInspection */
        require $mageRoot . '/lib/Credis/Client.php';
        break;
    } elseif (file_exists($try . '/app/autoload.php')) {
        $mageRoot = $try;
        /** @noinspection PhpIncludeInspection */
        require $mageRoot . '/app/autoload.php';
        break;
    }
}
if (empty($mageRoot)) {
    die('ERROR: Could not find Magento root dir.');
}

$client = Redis_Connect($mageRoot);
$result = $client->flushdb();
if ($result) {
    if ($cliRun) {
        echo "OK:  Cleaned Magento Cache Redis storage.\n";
    }
} else {
    echo "ERROR:  Error cleaning Magento Cache Redis storage.\n";
}

exit;

function Redis_Connect($mageRoot)
{
    $m1xmlFile = $mageRoot . '/app/etc/local.xml';
    $m2envFile = $mageRoot . '/app/etc/env.php';
    if (file_exists($m2envFile)) {
        $config = include $m2envFile;
        $host = $config['cache']['frontend']['default']['backend_options']['server'];
        $port = $config['cache']['frontend']['default']['backend_options']['port'];
        $db = empty($config['cache']['frontend']['default']['backend_options']['database']) ? 0 : $config['cache']['frontend']['default']['backend_options']['database'];
    } elseif (file_exists($m1xmlFile)) {
        if (!is_readable($m1xmlFile)) {
            throw new Exception(sprintf('File "%s" does not exits or is not readable.', $m1xmlFile));
        }

        $xml = simplexml_load_file($m1xmlFile, 'SimpleXMLElement', LIBXML_NOCDATA);
        /** @noinspection PhpUndefinedFieldInspection */
        $host = strval($xml->global->cache->backend_options->server);
        /** @noinspection PhpUndefinedFieldInspection */
        $port = strval($xml->global->cache->backend_options->port);
        /** @noinspection PhpUndefinedFieldInspection */
        $db = strval($xml->global->cache->backend_options->database);
        if (empty($host)) {
            throw new Exception(sprintf('Redis Cache hostname is not found in "%s".', $m1xmlFile));
        }
        if (is_null($port) || "" === $port) // port can be 0 when using socket
        {
            throw new Exception(sprintf('Redis Cache port is not found in "%s".', $m1xmlFile));
        }
        if (!strlen($db)) {
            throw new Exception(sprintf('Redis Cache database number is not found in "%s".', $m1xmlFile));
        }
    }

    if ('/' == substr($host, 0, 1) || 0 === $port) {
        // Socket
        $server = $host;
    } else {
        // TCP
        $server = sprintf('tcp://%s:%d', $host, $port);
    }


    if (!isset($db) || is_null($db) || "" === $db || empty($server)) {
        throw new Exception('Could not find Redis configuration');
    }

    $client = new \Credis_Client($server);
    $client->select($db);

    return $client;
}


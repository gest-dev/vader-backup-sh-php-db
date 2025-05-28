<?php

require __DIR__ . '/vendor/autoload.php'; // Primeiro carrega o autoload

use Aws\S3\S3Client;
use Carbon\Carbon;
use Dotenv\Dotenv;

// Carrega o .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Dados do S3 da Contabo
define('S3_STORAGE_ACCESS_KEY_ID', $_ENV['S3_STORAGE_ACCESS_KEY_ID']);
define('S3_STORAGE_SECRET_ACCESS_KEY', $_ENV['S3_STORAGE_SECRET_ACCESS_KEY']);
define('S3_STORAGE_ENDPOINT', $_ENV['S3_STORAGE_ENDPOINT']);
define('S3_STORAGE_BUCKET', $_ENV['S3_STORAGE_BUCKET']);

// Função de formatação de tamanho
function formatSizeUnits($bytes)
{
    if ($bytes >= 1073741824)
        return number_format($bytes / 1073741824, 2) . ' GB';
    elseif ($bytes >= 1048576)
        return number_format($bytes / 1048576, 2) . ' MB';
    elseif ($bytes >= 1024)
        return number_format($bytes / 1024, 2) . ' KB';
    elseif ($bytes > 1)
        return $bytes . ' bytes';
    elseif ($bytes == 1)
        return '1 byte';
    else
        return '0 bytes';
}

// Lista de arquivos na pasta de backup
function dirList($directory, $sortOrder = "newestFirst")
{
    $results = [];
    $file_names = [];
    $file_dates = [];
    $handler = opendir($directory);

    while ($file = readdir($handler)) {
        if ($file != '.' && $file != '..') {
            $currentModified = filectime($directory . "/" . $file);
            $file_names[] = $file;
            $file_dates[] = $currentModified;
        }
    }
    closedir($handler);

    $file_dates = array_combine($file_names, $file_dates);
    if ($sortOrder == "newestFirst") {
        arsort($file_dates);
    } else {
        asort($file_dates);
    }

    return array_keys($file_dates);
}

try {
    $client = new S3Client([
        'version' => 'latest',
        'region' => 'us-east-1',
        'endpoint' => S3_STORAGE_ENDPOINT,
        'credentials' => [
            'key' => S3_STORAGE_ACCESS_KEY_ID,
            'secret' => S3_STORAGE_SECRET_ACCESS_KEY,
        ],
        'use_path_style_endpoint' => true,
    ]);

    // Apaga arquivos com mais de 20 dias
    // $objects = $client->listObjects(['Bucket' => S3_STORAGE_BUCKET]);
    // $objects = $objects['Contents'] ?? [];
    $now = Carbon::now()->modify('-6 days');

    // foreach ($objects as $object) {
    //     if (Carbon::parse($object['LastModified'])->lt($now)) {
    //         $client->deleteObject(['Bucket' => S3_STORAGE_BUCKET, 'Key' => $object['Key']]);
    //     }
    // }

    $dir = '/home/u431758052/domains/gestclin.com.br/script_bk/backups_mysql/';
    $newFile = dirList($dir);
    $name = $newFile[0];

    // Upload do backup
    $client->putObject([
        'Bucket' => S3_STORAGE_BUCKET,
        'Key' => $name,
        'Body' => file_get_contents($dir . $name),
    ]);

    $messageFormated = "*Gestclin Hostinger*\n";
    $messageFormated .= "*Type:* Backup\n";
    $messageFormated .= "*Message:* Backup e upload sent success!\n";
    $messageFormated .= "*Status:* Success\n";
    $messageFormated .= "*File:* " . $name . "\n";
    $messageFormated .= "*File:* " . formatSizeUnits(filesize($dir . $name)) . "\n";
    $messageFormated .= "*Date:* " . date('d-m-Y H:i:s');


    // Notificação
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $_ENV['HUBOOT_URL'] . '?key=' . $_ENV['HUBOOT_KEY'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            "id" =>  $_ENV['HUBOOT_GROUP_ID'],
            "message" => $messageFormated,
            "group" => true,
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $_ENV['HUBOOT_TOKEN'],
        ],
    ]);
    curl_exec($curl);
    curl_close($curl);
} catch (\Exception $e) {

    $messageFormated = "*Gestclin Hostinger*\n";
    $messageFormated .= "*Type:* Backup\n";
    $messageFormated .= "*Message:* " . "Erro ao realizar backup \n " . $e->getMessage() . "\n";
    $messageFormated .= "*Status:* Error\n";
    $messageFormated .= "*File:* --\n";
    $messageFormated .= "*File:* --\n";
    $messageFormated .= "*Date:* " . date('d-m-Y H:i:s');
    // Notificação de erro
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $_ENV['HUBOOT_URL'] . '?key=' . $_ENV['HUBOOT_KEY'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            "id" =>  $_ENV['HUBOOT_GROUP_ID'],
            "message" =>  $messageFormated,
            "group" => true,
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $_ENV['HUBOOT_TOKEN'],
        ],
    ]);
    curl_exec($curl);
    curl_close($curl);
}

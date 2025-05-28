<?php

require __DIR__ . '/vendor/autoload.php'; // 1º passo

use Aws\S3\S3Client;
use Carbon\Carbon;

// Pega os argumentos do shell script
// $argv[0] = nome do script
// $argv[1] = S3_STORAGE_ACCESS_KEY_ID
// $argv[2] = S3_STORAGE_SECRET_ACCESS_KEY
// $argv[3] = S3_STORAGE_ENDPOINT
// $argv[4] = S3_STORAGE_BUCKET
// $argv[5] = HUBOOT_URL
// $argv[6] = HUBOOT_KEY
// $argv[7] = HUBOOT_TOKEN
// $argv[8] = HUBOOT_GROUP_ID

if ($argc < 9) {
    die("Erro: faltam parâmetros. Esperado 8 argumentos.\n");
}

define('S3_STORAGE_ACCESS_KEY_ID', $argv[1]);
define('S3_STORAGE_SECRET_ACCESS_KEY', $argv[2]);
define('S3_STORAGE_ENDPOINT', $argv[3]);
define('S3_STORAGE_BUCKET', $argv[4]);

define('HUBOOT_URL', $argv[5]);
define('HUBOOT_KEY', $argv[6]);
define('HUBOOT_TOKEN', $argv[7]);
define('HUBOOT_GROUP_ID', $argv[8]);
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
        CURLOPT_URL => HUBOOT_URL . '?key=' . HUBOOT_KEY,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            "id" => HUBOOT_GROUP_ID,
            "message" => $messageFormated,
            "group" => true,
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . HUBOOT_TOKEN,
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
        CURLOPT_URL => HUBOOT_URL . '?key=' . HUBOOT_KEY,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            "id" => HUBOOT_GROUP_ID,
            "message" => $messageFormated,
            "group" => true,
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . HUBOOT_TOKEN,
        ],
    ]);
    curl_exec($curl);
    curl_close($curl);
}

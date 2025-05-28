<?php
use Google\Cloud\Storage\StorageClient;
use Carbon\Carbon;

require __DIR__ . '/vendor/autoload.php';

function formatSizeUnits($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return $bytes . ' byte';
    } else {
        return '0 bytes';
    }
}

function dirList($directory, $sortOrder)
{
    $results = [];
    $handler = opendir($directory);
    while ($file = readdir($handler)) {
        if ($file != '.' && $file != '..' && $file != "robots.txt" && $file != ".htaccess") {
            $currentModified = filectime($directory . "/" . $file);
            $file_names[] = $file;
            $file_dates[] = $currentModified;
        }
    }
    closedir($handler);

    if ($sortOrder == "newestFirst") {
        arsort($file_dates);
    } else {
        asort($file_dates);
    }

    $file_names_Array = array_keys($file_dates);
    foreach ($file_names_Array as $idx => $name) {
        $name = $file_names[$name];
    }

    $file_dates = array_merge($file_dates);

    $response = [];
    foreach ($file_dates as $file_dates) {
        $date = $file_dates;
        $j = $file_names_Array[$i];
        $file = $file_names[$j];
        $i++;
        $response[] = $file;
    }

    return $response;
}

try {
    // Configuração do cliente GCS
    $projectId = 'ativ-site-beneficios'; // Substitua pelo ID do seu projeto no Google Cloud
    $bucketName = 'backups-ativ'; // Nome do bucket no GCS
    $storage = new StorageClient([
        'projectId' => $projectId,
    ]);

    $bucket = $storage->bucket($bucketName);

    // Apaga arquivos com mais de 20 dias
    $now = Carbon::now()->modify('-20 days');
    foreach ($bucket->objects() as $object) {
        $lastModified = Carbon::parse($object->info()['updated']);
        if ($lastModified->lt($now)) {
            $object->delete();
        }
    }

    // Encontra o arquivo mais recente no diretório local
    $dir = '/backups_mysql/';
    $newFile = dirList($dir, 'newestFirst');
    $name = $newFile[0];

    // Faz o upload para o bucket do GCS
    $filePath = $dir . $name;
    $bucket->upload(
        fopen($filePath, 'r'),
        [
            'name' => $name
        ]
    );

    // Envia uma notificação via cURL
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'http://5.189.148.47:8070/api/message',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_POSTFIELDS => json_encode([
            'project' => 'ativ-backup(novo)',
            'file' => $name,
            'size' => formatSizeUnits(filesize($filePath)),
            'message' => 'Backup realizado com sucesso!'
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer 1|SzCN5SiW7FZ4zSi8ksvCFQEZawNNI26iMiQvutebd3b321f7'
        ],
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

} catch (Exception $e) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'http://5.189.148.47:8070/api/message',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_POSTFIELDS => json_encode([
            'project' => 'ativ-backup(extra)',
            'file' => '',
            'size' => '',
            'message' => "Erro ao realizar backup \n {$e->getMessage()}"
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer 1|SzCN5SiW7FZ4zSi8ksvCFQEZawNNI26iMiQvutebd3b321f7'
        ],
    ]);

    $response = curl_exec($curl);
    curl_close($curl);
}

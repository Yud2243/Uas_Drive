<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// 1. Koneksi internal Docker (buat Upload/Rename/Delete)
$s3 = new S3Client([
    'version'                 => 'latest',
    'region'                  => 'us-east-1',
    'endpoint'                => 'http://storage:9000', // Pake port internal docker (9000)
    'use_path_style_endpoint' => true,
    'credentials' => [
        'key'    => 'admin',
        'secret' => 'password123',
    ],
]);

// 2. Koneksi eksternal (khusus buat ngebenerin error pas klik tombol 'Lihat')
$s3_eksternal = new S3Client([
    'version'                 => 'latest',
    'region'                  => 'us-east-1',
    'endpoint'                => 'http://localhost:9005', // Langsung nembak localhost
    'use_path_style_endpoint' => true,
    'credentials' => [
        'key'    => 'admin',
        'secret' => 'password123',
    ],
]);

$bucketName = 'dibra-drive';

// Otomatis bikin bucket "dibra-drive" kalau belum ada
try {
    if (!$s3->doesBucketExist($bucketName)) {
        $s3->createBucket(['Bucket' => $bucketName]);
    }
} catch (AwsException $e) {
    die("Error koneksi ke MinIO: " . $e->getMessage());
}
?>
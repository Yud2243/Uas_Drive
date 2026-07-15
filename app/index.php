<?php
require 'config.php';

$message = '';

// 1. Proses Multiple Upload File (Create)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['filesToUpload'])) {
    $files = $_FILES['filesToUpload'];
    $totalFiles = count($files['name']);
    $successCount = 0;
    $errorMessages = [];

    for ($i = 0; $i < $totalFiles; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $fileName = basename($files['name'][$i]);
            $filePath = $files['tmp_name'][$i];

            try {
                $s3->putObject([
                    'Bucket' => $bucketName,
                    'Key'    => $fileName,
                    'SourceFile' => $filePath,
                ]);
                $successCount++;
            } catch (Exception $e) {
                $errorMessages[] = "Gagal upload <b>$fileName</b>: " . $e->getMessage();
            }
        }
    }

    if ($successCount > 0) {
        $message .= "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'>
                        <i class='bi bi-check-circle-fill me-2'></i> <b>$successCount</b> file berhasil diupload!
                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                     </div>";
    }
    if (!empty($errorMessages)) {
        foreach ($errorMessages as $err) {
            $message .= "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'>
                            <i class='bi bi-exclamation-triangle-fill me-2'></i> $err
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                         </div>";
        }
    }
}

// 2. Proses Hapus File (Delete)
if (isset($_GET['delete'])) {
    $keyToDelete = $_GET['delete'];
    try {
        $s3->deleteObject([
            'Bucket' => $bucketName,
            'Key'    => $keyToDelete
        ]);
        $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'>
                        <i class='bi bi-trash-fill me-2'></i> File <b>$keyToDelete</b> berhasil dihapus!
                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                    </div>";
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'>Gagal hapus: " . $e->getMessage() . "</div>";
    }
}

// 3. Proses Ubah Nama File (Update / Rename)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['oldName']) && isset($_POST['newName'])) {
    $oldName = $_POST['oldName'];
    $newName = $_POST['newName'];
    
    try {
        $s3->copyObject([
            'Bucket'     => $bucketName,
            'Key'        => $newName,
            'CopySource' => urlencode("$bucketName/$oldName")
        ]);
        $s3->deleteObject([
            'Bucket' => $bucketName,
            'Key'    => $oldName
        ]);
        $message = "<div class='alert alert-info alert-dismissible fade show shadow-sm' role='alert'>
                        <i class='bi bi-pencil-square me-2'></i> Nama file diubah menjadi <b>$newName</b>!
                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                    </div>";
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'>Gagal rename: " . $e->getMessage() . "</div>";
    }
}

// 4. Ambil list file dari MinIO (Read)
$files = [];
try {
    $result = $s3->listObjectsV2(['Bucket' => $bucketName]);
    if (isset($result['Contents'])) {
        $files = $result['Contents'];
    }
} catch (Exception $e) {
    $message = "<div class='alert alert-danger'>Gagal ambil data: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UAS Drive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar-brand { font-weight: bold; font-size: 1.5rem; color: #0d6efd !important; }
        .upload-area {
            border: 2px dashed #0d6efd;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        .upload-area:hover { background: #e9ecef; border-color: #0b5ed7; }
        .file-icon { font-size: 1.5rem; color: #6c757d; }
        .table-hover tbody tr:hover { background-color: #f1f5f9; }
        .btn-action { border-radius: 8px; padding: 0.375rem 0.75rem; }
        .card { border: none; border-radius: 12px; }
        .card-header { border-top-left-radius: 12px !important; border-top-right-radius: 12px !important; background-color: #ffffff; border-bottom: 1px solid #f0f0f0; }
        .file-preview-list { max-height: 150px; overflow-y: auto; }
        .remove-file-btn { cursor: pointer; transition: color 0.2s; }
        .remove-file-btn:hover { color: #dc3545 !important; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-5">
    <div class="container">
        <a class="navbar-brand" href="#">
            <i class="bi bi-cloud-arrow-up-fill me-2"></i>UAS Drive
        </a>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            
            <?= $message ?>

            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <form action="index.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="upload-area mb-3">
                            <i class="bi bi-cloud-arrow-up display-4 text-primary mb-3"></i>
                            <h5 class="mb-3">Upload File Baru</h5>
                            <input class="form-control" type="file" id="fileToUpload" name="filesToUpload[]" multiple required>
                            <small class="text-muted mt-2 d-block">Bisa pilih lebih dari satu file sekaligus.</small>
                            
                            <!-- Tempat nampilin nama-nama file yang mau diupload -->
                            <div id="filePreviewContainer" class="mt-3 text-start d-none">
                                <p class="mb-1 fw-bold text-secondary" style="font-size: 0.9rem;">File yang dipilih:</p>
                                <ul id="fileList" class="list-group file-preview-list shadow-sm"></ul>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill" id="btnUpload">
                                <i class="bi bi-upload me-2"></i> Upload
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-folder2-open me-2 text-warning"></i>Daftar File Tersimpan</h5>
                    <span class="badge bg-primary rounded-pill"><?= count($files) ?> File</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Nama File</th>
                                    <th>Ukuran</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($files)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-5">
                                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                        <p class="text-muted mt-2 mb-0">Belum ada file di dalam drive.</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach($files as $file): 
                                        $cmd = $s3_eksternal->getCommand('GetObject', [
                                            'Bucket' => $bucketName,
                                            'Key'    => $file['Key']
                                        ]);
                                        $request = $s3_eksternal->createPresignedRequest($cmd, '+60 minutes');
                                        $fileUrl = (string)$request->getUri();
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <i class="bi bi-file-earmark-text file-icon me-2"></i>
                                            <span class="fw-medium"><?= htmlspecialchars($file['Key']) ?></span>
                                        </td>
                                        <td class="text-muted"><?= number_format($file['Size'] / 1024, 2) ?> KB</td>
                                        <td class="text-center">
                                            <a href="<?= $fileUrl ?>" target="_blank" class="btn btn-sm btn-info text-white btn-action" title="Lihat">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <button class="btn btn-sm btn-secondary btn-action" onclick="renameFile('<?= htmlspecialchars($file['Key'], ENT_QUOTES) ?>')" title="Rename">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <a href="?delete=<?= urlencode($file['Key']) ?>" class="btn btn-sm btn-danger btn-action" onclick="return confirm('Yakin mau hapus file <?= htmlspecialchars($file['Key'], ENT_QUOTES) ?> secara permanen?')" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<form id="renameForm" method="POST" style="display: none;">
    <input type="hidden" name="oldName" id="oldNameInput">
    <input type="hidden" name="newName" id="newNameInput">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const fileInput = document.getElementById('fileToUpload');
    const fileListContainer = document.getElementById('filePreviewContainer');
    const fileList = document.getElementById('fileList');

    function renderFileList() {
        fileList.innerHTML = '';
        let files = fileInput.files;
        
        if (files.length > 0) {
            fileListContainer.classList.remove('d-none');
            
            for (let i = 0; i < files.length; i++) {
                let li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center py-2';
                
                let fileInfo = document.createElement('div');
                fileInfo.className = 'text-truncate';
                fileInfo.innerHTML = '<i class="bi bi-check2-circle text-success me-2"></i><small>' + files[i].name + '</small>';
                
                let removeBtn = document.createElement('i');
                removeBtn.className = 'bi bi-x-circle text-secondary remove-file-btn'; 
                removeBtn.style.fontSize = '0.85rem'; // Dikecilin ukurannya biar pas sama teks
                removeBtn.title = 'Batal upload file ini';
                removeBtn.onclick = function() {
                    removeFile(i);
                };
                
                li.appendChild(fileInfo);
                li.appendChild(removeBtn);
                fileList.appendChild(li);
            }
        } else {
            fileListContainer.classList.add('d-none');
            fileInput.value = ''; 
        }
    }

    fileInput.addEventListener('change', renderFileList);

    function removeFile(indexToRemove) {
        const dt = new DataTransfer();
        const files = fileInput.files;
        
        for (let i = 0; i < files.length; i++) {
            if (i !== indexToRemove) {
                dt.items.add(files[i]);
            }
        }
        
        fileInput.files = dt.files;
        renderFileList(); 
    }

    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        if(fileInput.files.length === 0) {
            e.preventDefault();
            alert('Pilih file dulu sebelum klik upload!');
            return;
        }
        let btn = document.getElementById('btnUpload');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Mengupload...';
        btn.classList.add('disabled');
    });

    function renameFile(oldName) {
        let newName = prompt("Masukkan nama baru untuk file:", oldName);
        if (newName != null && newName.trim() !== "" && newName !== oldName) {
            document.getElementById('oldNameInput').value = oldName;
            document.getElementById('newNameInput').value = newName.trim();
            document.getElementById('renameForm').submit();
        }
    }

    setTimeout(function() {
        let alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alertNode) {
            let bsAlert = new bootstrap.Alert(alertNode);
            bsAlert.close();
        });
    }, 3000);
</script>

</body>
</html>
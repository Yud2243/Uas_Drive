<?php
require 'config.php';

$message = '';

// Ambil posisi folder saat ini dari URL
$currentPrefix = isset($_GET['prefix']) ? $_GET['prefix'] : '';

// 1. Proses Bikin Folder Baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['folderName'])) {
    $folderName = trim($_POST['folderName']);
    $folderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folderName);
    
    if (!empty($folderName)) {
        $folderKey = $currentPrefix . $folderName . '/'; 
        try {
            $s3->putObject([
                'Bucket' => $bucketName,
                'Key'    => $folderKey,
                'Body'   => '', 
            ]);
            $message .= "<div class='alert alert-success alert-dismissible fade show shadow-sm border-0' role='alert'>
                            <i class='bi bi-folder-plus me-2'></i> Folder <b>$folderName</b> berhasil dibuat!
                            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                         </div>";
        } catch (Exception $e) {
            $message .= "<div class='alert alert-danger alert-dismissible fade show shadow-sm border-0' role='alert'>Gagal bikin folder: " . $e->getMessage() . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }
}

// 2. Proses Multiple Upload File 
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['filesToUpload'])) {
    $files = $_FILES['filesToUpload'];
    $totalFiles = count($files['name']);
    $successCount = 0;
    $errorMessages = [];

    for ($i = 0; $i < $totalFiles; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $fileName = basename($files['name'][$i]);
            $filePath = $files['tmp_name'][$i];
            $objectKey = $currentPrefix . $fileName; 
            
            $contentType = @mime_content_type($filePath) ?: 'application/octet-stream';

            try {
                $s3->putObject([
                    'Bucket'      => $bucketName,
                    'Key'         => $objectKey,
                    'SourceFile'  => $filePath,
                    'ContentType' => $contentType
                ]);
                $successCount++;
            } catch (Exception $e) {
                $errorMessages[] = "Gagal upload <b>$fileName</b>: " . $e->getMessage();
            }
        }
    }

    if ($successCount > 0) {
        $message .= "<div class='alert alert-success alert-dismissible fade show shadow-sm border-0' role='alert'>
                        <i class='bi bi-check-circle-fill me-2'></i> <b>$successCount</b> file berhasil diupload!
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                     </div>";
    }
    if (!empty($errorMessages)) {
        foreach ($errorMessages as $err) {
            $message .= "<div class='alert alert-danger alert-dismissible fade show shadow-sm border-0' role='alert'>
                            <i class='bi bi-exclamation-triangle-fill me-2'></i> $err
                            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                         </div>";
        }
    }
}

// 3. Proses Hapus File/Folder (Delete)
if (isset($_GET['delete'])) {
    $keyToDelete = $_GET['delete'];
    try {
        $s3->deleteObject([
            'Bucket' => $bucketName,
            'Key'    => $keyToDelete
        ]);
        $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm border-0' role='alert'>
                        <i class='bi bi-trash-fill me-2'></i> Item berhasil dihapus!
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                    </div>";
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm border-0' role='alert'>Gagal hapus: " . $e->getMessage() . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// 4. Proses Ubah Nama File (Update / Rename)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['oldName']) && isset($_POST['newName'])) {
    $oldName = $_POST['oldName'];
    $newName = $currentPrefix . $_POST['newName']; 
    
    // Trik biar spasi di-encode, tapi folder slash (/) tetep aman!
    $parts = explode('/', $oldName);
    $encodedParts = array_map('rawurlencode', $parts);
    $encodedOldName = implode('/', $encodedParts);
    $copySource = rawurlencode($bucketName) . '/' . $encodedOldName;
    
    try {
        $s3->copyObject([
            'Bucket'     => $bucketName,
            'Key'        => $newName,
            'CopySource' => $copySource
        ]);
        $s3->deleteObject([
            'Bucket' => $bucketName,
            'Key'    => $oldName
        ]);
        $message = "<div class='alert alert-info alert-dismissible fade show shadow-sm border-0' role='alert'>
                        <i class='bi bi-pencil-square me-2'></i> Nama diubah menjadi <b>" . htmlspecialchars($_POST['newName']) . "</b>!
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                    </div>";
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm border-0' role='alert'>Gagal rename: " . $e->getMessage() . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// 5. Ambil list file & folder
$folders = [];
$files = [];
try {
    $result = $s3->listObjectsV2([
        'Bucket' => $bucketName,
        'Prefix' => $currentPrefix,
        'Delimiter' => '/'
    ]);
    if (isset($result['CommonPrefixes'])) { $folders = $result['CommonPrefixes']; }
    if (isset($result['Contents'])) { $files = $result['Contents']; }
} catch (Exception $e) {
    $message = "<div class='alert alert-danger'>Gagal ambil data: " . $e->getMessage() . "</div>";
}

$parentPrefix = '';
if (!empty($currentPrefix)) {
    $parts = explode('/', rtrim($currentPrefix, '/'));
    array_pop($parts);
    $parentPrefix = empty($parts) ? '' : implode('/', $parts) . '/';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UAS Drive</title>
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { 
            background-color: #f0f2f5; 
            font-family: 'Poppins', sans-serif; 
            color: #333;
        }
        .navbar { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); padding: 15px 0; }
        .navbar-brand { font-weight: 700; color: #fff !important; letter-spacing: 0.5px; }
        
        .upload-card { border: none; border-radius: 16px; transition: transform 0.2s; }
        .upload-area { 
            border: 2px dashed #a5c8fd; 
            border-radius: 12px; 
            padding: 25px; 
            text-align: center; 
            background: #f8fbff; 
            transition: all 0.3s ease;
        }
        .upload-area:hover { background: #eff5ff; border-color: #0d6efd; }
        
        .card-main { border: none; border-radius: 16px; overflow: hidden; }
        .card-header-custom { background-color: #fff; border-bottom: 1px solid #edf2f7; padding: 20px 24px; }
        
        /* FIX LAYOUT: Table fixed biar nggak melar */
        .table-fixed { table-layout: fixed; width: 100%; }
        .table-hover tbody tr { transition: background-color 0.2s; }
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
        th { font-weight: 600; color: #6c757d; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; }
        td { vertical-align: middle; font-size: 0.95rem; }
        
        /* FIX LAYOUT: Nama file yang kepanjangan bakal dipotong (...) */
        .text-truncate-container {
            display: flex;
            align-items: center;
            overflow: hidden;
        }
        
        .folder-link { text-decoration: none; color: #212529; transition: color 0.2s; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .folder-link:hover { color: #0d6efd; }
        .folder-icon { color: #ffc107; font-size: 1.4rem; flex-shrink: 0; }
        
        /* Tombol Aksi */
        .action-btns { white-space: nowrap; }
        .btn-action { border-radius: 8px; padding: 0.35rem 0.6rem; }
        
        .breadcrumb-wrapper { background: #fff; border-radius: 12px; padding: 12px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .breadcrumb { margin-bottom: 0; align-items: center; }
        .breadcrumb-item a { text-decoration: none; color: #0d6efd; font-weight: 500; }
        
        .remove-file-btn { cursor: pointer; color: #adb5bd; transition: color 0.2s; font-size: 0.9rem;}
        .remove-file-btn:hover { color: #dc3545 !important; }
        .file-preview-list { max-height: 120px; overflow-y: auto; border-radius: 8px; border: 1px solid #edf2f7;}
        .list-group-item { border: none; border-bottom: 1px solid #edf2f7; font-size: 0.9rem;}
        .list-group-item:last-child { border-bottom: none; }
        
        /* Styling Tooltip biar keren */
        .tooltip-inner { font-family: 'Poppins', sans-serif; font-size: 0.8rem; padding: 6px 10px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <i class="bi bi-cloud-check-fill fs-3 me-2 text-white"></i> 
            <span>UAS Drive</span>
        </a>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <?= $message ?>

            <!-- BAGIAN NAVIGASI & TOMBOL NEW FOLDER -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="breadcrumb-wrapper flex-grow-1 me-3 text-truncate">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb flex-nowrap overflow-hidden">
                            <li class="breadcrumb-item"><a href="index.php"><i class="bi bi-house-door-fill me-1"></i> Home</a></li>
                            <?php 
                                $paths = explode('/', rtrim($currentPrefix, '/'));
                                $buildPath = '';
                                foreach($paths as $p) {
                                    if(!empty($p)) {
                                        $buildPath .= $p . '/';
                                        echo "<li class='breadcrumb-item active text-truncate' aria-current='page' style='max-width: 150px;'><a href='?prefix=".urlencode($buildPath)."'>$p</a></li>";
                                    }
                                }
                            ?>
                        </ol>
                    </nav>
                </div>
                <button type="button" class="btn btn-primary shadow-sm rounded-pill px-4 fw-medium flex-shrink-0" data-bs-toggle="modal" data-bs-target="#newFolderModal">
                    <i class="bi bi-folder-plus me-2"></i>Buat Folder
                </button>
            </div>

            <!-- KOTAK UPLOAD DENGAN PROGRESS BAR -->
            <div class="card upload-card shadow-sm mb-4">
                <div class="card-body p-4">
                    <form action="?prefix=<?= urlencode($currentPrefix) ?>" method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="upload-area mb-3">
                            <i class="bi bi-cloud-arrow-up display-5 text-primary mb-2"></i>
                            <h5 class="fw-semibold text-dark mb-1">Pilih file untuk diunggah</h5>
                            <p class="text-muted small mb-3">File akan disimpan di direktori saat ini</p>
                            
                            <input class="form-control form-control-sm mx-auto" style="max-width: 400px;" type="file" id="fileToUpload" name="filesToUpload[]" multiple required>
                            
                            <!-- Preview List -->
                            <div id="filePreviewContainer" class="mt-3 text-start d-none mx-auto" style="max-width: 400px;">
                                <ul id="fileList" class="list-group file-preview-list shadow-sm"></ul>
                            </div>

                            <!-- Progress Bar Real-time -->
                            <div class="progress mt-3 d-none mx-auto shadow-sm" id="uploadProgressContainer" style="max-width: 400px; height: 22px; border-radius: 10px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary fw-bold" id="uploadProgressBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-medium shadow-sm" id="btnUpload">
                                <i class="bi bi-upload me-2"></i>Mulai Upload
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TABEL FILE & FOLDER -->
            <div class="card card-main shadow-sm mb-5">
                <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center">
                        <h5 class="mb-0 fw-semibold text-dark me-3"><i class="bi bi-hdd-fill text-secondary me-2"></i>Penyimpanan Saya</h5>
                        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill"><?= count($folders) + count($files) ?> Item</span>
                    </div>
                    <!-- FITUR BONUS: Search Bar -->
                    <div class="input-group" style="max-width: 250px;">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0 ps-0 shadow-none" placeholder="Cari file/folder...">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <!-- FIX LAYOUT: Tambah class table-fixed dan setup colgroup -->
                        <table class="table table-hover align-middle mb-0 table-fixed">
                            <colgroup>
                                <col style="width: 55%;"> <!-- Kolom Nama -->
                                <col style="width: 15%;"> <!-- Kolom Ukuran -->
                                <col style="width: 30%;"> <!-- Kolom Aksi -->
                            </colgroup>
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 border-0 py-3">Nama</th>
                                    <th class="border-0 py-3">Ukuran</th>
                                    <th class="text-end pe-4 border-0 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- BACK BUTTON -->
                                <?php if(!empty($currentPrefix)): ?>
                                <tr>
                                    <td colspan="3" class="ps-4 py-3 bg-light bg-opacity-50">
                                        <a href="?prefix=<?= urlencode($parentPrefix) ?>" class="folder-link fw-medium text-secondary">
                                            <i class="bi bi-arrow-left-circle-fill me-2 fs-5 text-primary flex-shrink-0"></i> Kembali ke luar
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <!-- FOLDER LIST -->
                                <?php foreach($folders as $folder): 
                                    $folderNameOnly = htmlspecialchars(str_replace($currentPrefix, '', rtrim($folder['Prefix'], '/')));
                                ?>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="text-truncate-container">
                                            <i class="bi bi-folder-fill folder-icon me-3"></i>
                                            <a href="?prefix=<?= urlencode($folder['Prefix']) ?>" class="folder-link fw-medium text-truncate" data-bs-toggle="tooltip" data-bs-title="<?= $folderNameOnly ?>">
                                                <?= $folderNameOnly ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td class="text-muted small">-</td>
                                    <td class="text-end pe-4 action-btns">
                                        <a href="?delete=<?= urlencode($folder['Prefix']) ?>&prefix=<?= urlencode($currentPrefix) ?>" class="btn btn-sm btn-light text-danger btn-action shadow-sm" onclick="return confirm('Yakin hapus folder ini?')" data-bs-toggle="tooltip" data-bs-title="Hapus Folder"><i class="bi bi-trash3-fill"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <!-- FILE LIST -->
                                <?php 
                                $hasFiles = false;
                                foreach($files as $file): 
                                    if($file['Key'] === $currentPrefix) continue;
                                    $hasFiles = true;
                                    
                                    // FIX PREVIEW
                                    $ext = strtolower(pathinfo($file['Key'], PATHINFO_EXTENSION));
                                    $contentTypes = [
                                        'pdf' => 'application/pdf',
                                        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                                        'png' => 'image/png', 'gif' => 'image/gif',
                                        'txt' => 'text/plain', 'mp4' => 'video/mp4'
                                    ];
                                    $mime = isset($contentTypes[$ext]) ? $contentTypes[$ext] : 'application/octet-stream';

                                    $cmd = $s3_eksternal->getCommand('GetObject', [
                                        'Bucket' => $bucketName, 
                                        'Key' => $file['Key'],
                                        'ResponseContentType' => $mime,
                                        'ResponseContentDisposition' => 'inline; filename="' . basename($file['Key']) . '"'
                                    ]);
                                    $request = $s3_eksternal->createPresignedRequest($cmd, '+60 minutes');
                                    $fileUrl = (string)$request->getUri();
                                    $displayName = htmlspecialchars(str_replace($currentPrefix, '', $file['Key']));
                                ?>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <!-- FIX LAYOUT: Div container buat motong text pake elipsis -->
                                        <div class="text-truncate-container">
                                            <i class="bi bi-file-earmark-text-fill fs-4 text-primary opacity-75 me-3 flex-shrink-0"></i>
                                            <span class="fw-medium text-dark text-truncate" data-bs-toggle="tooltip" data-bs-title="<?= $displayName ?>"><?= $displayName ?></span>
                                        </div>
                                    </td>
                                    <td class="text-muted small"><?= number_format($file['Size'] / 1024, 2) ?> KB</td>
                                    <td class="text-end pe-4 action-btns">
                                        <!-- UX TOOLTIPS (data-bs-title) -->
                                        <a href="<?= $fileUrl ?>" target="_blank" class="btn btn-sm btn-primary btn-action shadow-sm text-white" data-bs-toggle="tooltip" data-bs-title="Lihat / Preview"><i class="bi bi-eye-fill"></i></a>
                                        
                                        <button class="btn btn-sm btn-light btn-action shadow-sm text-success mx-1" onclick="copyLink('<?= $fileUrl ?>')" data-bs-toggle="tooltip" data-bs-title="Copy Link Share"><i class="bi bi-link-45deg fs-6"></i></button>

                                        <button class="btn btn-sm btn-light btn-action shadow-sm text-dark me-1" onclick="openRenameModal('<?= htmlspecialchars($file['Key'], ENT_QUOTES) ?>', '<?= addslashes($displayName) ?>')" data-bs-toggle="tooltip" data-bs-title="Ubah Nama"><i class="bi bi-pencil-square"></i></button>
                                        
                                        <a href="?delete=<?= urlencode($file['Key']) ?>&prefix=<?= urlencode($currentPrefix) ?>" class="btn btn-sm btn-light btn-action shadow-sm text-danger" onclick="return confirm('Yakin mau hapus file ini?')" data-bs-toggle="tooltip" data-bs-title="Hapus File"><i class="bi bi-trash3-fill"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <!-- EMPTY STATE -->
                                <?php if(empty($folders) && !$hasFiles && empty($currentPrefix)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-5">
                                        <div class="py-4">
                                            <i class="bi bi-inbox text-muted opacity-25" style="font-size: 4rem;"></i>
                                            <h6 class="text-muted mt-3 fw-normal">Drive masih kosong, belum ada file.</h6>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- MODAL BIKIN FOLDER -->
<div class="modal fade" id="newFolderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h1 class="modal-title fs-5 fw-bold">Buat Folder Baru</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="?prefix=<?= urlencode($currentPrefix) ?>" method="POST">
          <div class="modal-body pt-3 pb-4">
            <input type="text" name="folderName" class="form-control form-control-lg" placeholder="Nama folder..." required autofocus>
          </div>
          <div class="modal-footer border-top-0 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary rounded-pill px-4">Buat</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL UBAH NAMA (RENAME) -->
<div class="modal fade" id="renameModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h1 class="modal-title fs-5 fw-bold">Ubah Nama Item</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="?prefix=<?= urlencode($currentPrefix) ?>" method="POST">
          <div class="modal-body pt-3 pb-4">
            <input type="hidden" name="oldName" id="oldNameInput">
            <input type="text" name="newName" id="newNameInput" class="form-control form-control-lg" placeholder="Nama baru..." required autofocus>
          </div>
          <div class="modal-footer border-top-0 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- TOAST NOTIFIKASI COPY LINK -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="copyToast" class="toast align-items-center text-bg-success border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body fw-medium text-white">
        <i class="bi bi-clipboard-check-fill me-2"></i> Link berhasil disalin ke clipboard!
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // INISIALISASI BOOTSTRAP TOOLTIPS BIAR MAKIN PREMIUM
    function initTooltips() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }
    initTooltips();

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
                li.className = 'list-group-item d-flex justify-content-between align-items-center bg-white';
                li.innerHTML = '<div class="text-truncate fw-medium text-secondary" style="max-width:80%;"><i class="bi bi-file-earmark-check-fill text-success me-2"></i>' + files[i].name + '</div>';
                
                let removeBtn = document.createElement('i');
                removeBtn.className = 'bi bi-x-lg remove-file-btn'; 
                removeBtn.setAttribute('data-bs-toggle', 'tooltip');
                removeBtn.setAttribute('data-bs-title', 'Batal upload');
                removeBtn.onclick = function() { removeFile(i); };
                
                li.appendChild(removeBtn);
                fileList.appendChild(li);
            }
            // Re-init tooltips buat list file yang baru dirender
            initTooltips();
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
            if (i !== indexToRemove) dt.items.add(files[i]);
        }
        fileInput.files = dt.files;
        
        // Hide tooltips yang lagi kebuka biar gak nyangkut pas div dihapus
        document.querySelectorAll('.tooltip').forEach(el => el.remove());
        renderFileList(); 
    }

    // FITUR: AJAX PROGRESS BAR UPLOAD FILE BESAR
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        e.preventDefault(); 
        
        if(fileInput.files.length === 0) {
            alert('Pilih file dulu!'); return;
        }
        
        let btn = document.getElementById('btnUpload');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Mengupload...';
        btn.classList.add('disabled');
        
        let progressContainer = document.getElementById('uploadProgressContainer');
        let progressBar = document.getElementById('uploadProgressBar');
        progressContainer.classList.remove('d-none'); 
        
        let formData = new FormData(this);
        let xhr = new XMLHttpRequest();
        
        xhr.open('POST', '?prefix=<?= urlencode($currentPrefix) ?>', true);
        
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                let percentComplete = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressBar.innerHTML = percentComplete + '%';
                progressBar.setAttribute('aria-valuenow', percentComplete);
            }
        };
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                // Reload window biar aman dari bug tooltips yang mati
                document.open();
                document.write(xhr.responseText);
                document.close();
            } else {
                alert('Terjadi kesalahan saat upload.');
                btn.innerHTML = '<i class="bi bi-upload me-2"></i>Mulai Upload';
                btn.classList.remove('disabled');
                progressContainer.classList.add('d-none');
            }
        };
        
        xhr.send(formData);
    });

    // FUNGSI BUKA MODAL RENAME
    function openRenameModal(fullOldKey, shortOldName) {
        document.getElementById('oldNameInput').value = fullOldKey;
        document.getElementById('newNameInput').value = shortOldName;
        // Hide tooltip biar gak nyangkut pas modal kebuka
        document.querySelectorAll('.tooltip').forEach(el => el.remove());
        let renameModal = new bootstrap.Modal(document.getElementById('renameModal'));
        renameModal.show();
    }

    // FITUR BONUS: SEARCH BAR REAL-TIME
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('.table-hover tbody tr');
        
        rows.forEach(row => {
            if(row.innerText.includes('Kembali ke luar') || row.innerText.includes('Drive masih kosong')) return;
            let itemName = row.querySelector('td:first-child').innerText.toLowerCase();
            if (itemName.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // FITUR BONUS: COPY LINK PUBLIC PAKE TOAST 
    function copyLink(url) {
        navigator.clipboard.writeText(url).then(() => {
            const toastEl = document.getElementById('copyToast');
            const toast = new bootstrap.Toast(toastEl);
            toast.show(); 
        }).catch(err => {
            console.error('Gagal menyalin link: ', err);
        });
    }

    // AUTO-CLOSE ALERT NOTIFIKASI
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alertNode => new bootstrap.Alert(alertNode).close());
    }, 3000);
</script>

</body>
</html>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<pre>';
    print_r($_FILES);
    echo '</pre>';
    
    if (isset($_FILES['AdvertisementImage']['tmp_name']['imageFile']) && $_FILES['AdvertisementImage']['error']['imageFile'] === 0) {
        $uploadDir = __DIR__ . '/uploads/advertisements/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $filename = uniqid() . '_' . $_FILES['AdvertisementImage']['name']['imageFile'];
        if (move_uploaded_file($_FILES['AdvertisementImage']['tmp_name']['imageFile'], $uploadDir . $filename)) {
            echo 'File uploaded successfully: ' . $filename;
        } else {
            echo 'Failed to move uploaded file';
        }
    } else {
        echo 'No file uploaded or error occurred';
    }
    exit;
}
?>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="AdvertisementImage[imageFile]">
    <button type="submit">Upload</button>
</form>
<?php

require __DIR__ . '/vendor/autoload.php';

use Shopware\Core\Framework\Uuid\Uuid;

// Database credentials and connection
$host = 'localhost';
$dbname = 'datbase';
$username = 'user';
$password = 'pass';

//Constant variables
$defaultUserId = hex2bin('################################');
$versionId = hex2bin('################################');
$languageId = hex2bin('################################');
$mediaFolderId = hex2bin('################################');
$thumbnailsRo = 'O:77:"Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection":2:{s:13:" * extensions";a:0:{}s:11:" * elements";a:0:{}}';
$metaData = NULL;



// Helper function to convert binary UUID to hex
function binToHex($binaryUuid)
{
    return '0x' . bin2hex($binaryUuid);
}

// Function to generate a unique UUID v4 as binary
function generateUniqueUuidV4Binary()
{
    return Uuid::randomBytes();

}

function getMediaType($fileExtension)
{
    $audioExtensions = ['mp3', 'wav', 'flac'];
    $documentExtensions = ['pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx'];
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'];

    if (in_array($fileExtension, $audioExtensions)) {
        return 'O:47:"Shopware\Core\Content\Media\MediaType\AudioType":3:{s:13:" * extensions";a:0:{}s:7:" * name";s:5:"AUDIO";s:8:" * flags";a:0:{}}';
    } elseif (in_array($fileExtension, $documentExtensions)) {
        return 'O:50:"Shopware\Core\Content\Media\MediaType\DocumentType":3:{s:13:" * extensions";a:0:{}s:7:" * name";s:8:"DOCUMENT";s:8:" * flags";a:0:{}}';
    } elseif (in_array($fileExtension, $imageExtensions)) {
        return 'O:47:"Shopware\Core\Content\Media\MediaType\ImageType":3:{s:13:" * extensions";a:0:{}s:7:" * name";s:5:"IMAGE";s:8:" * flags";a:0:{}}';
    } else {
        // Default to a generic type if extension is not recognized
        return 'O:50:"Shopware\Core\Content\Media\MediaType\UnknownType":3:{s:13:" * extensions";a:0:{}s:7:" * name";s:7:"UNKNOWN";s:8:" * flags";a:0:{}}';
    }
}

try {
    // Connect to the database
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected successfully<br>";

    // Fetch a valid user ID
    $validUserQuery = $conn->query("SELECT id FROM user LIMIT 1");
    $userId = $validUserQuery->fetchColumn() ?: $defaultUserId;

    // Path for ESDRep folder
    $esdRepPath = __DIR__ . '/media/ESDRep';

    if (is_dir($esdRepPath)) {
        echo "<h3>Processing media and product downloads in Shopware 6 database:</h3>";

        foreach (new DirectoryIterator($esdRepPath) as $productFolder) {
            if ($productFolder->isDot() || !$productFolder->isDir()) {
                continue;
            }

            $productName = $productFolder->getFilename();
            echo "<strong>Product:</strong> $productName<br>";

            // Fetch product details using product_number
            $productQuery = $conn->prepare("SELECT id, version_id FROM product WHERE product_number = :product_number LIMIT 1");
            $productQuery->execute([':product_number' => $productName]);
            $product = $productQuery->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                echo "Product not found for product_number: $productName<br>";
                continue;
            }

            $productId = $product['id'];
            $productVersionId = $product['version_id'];
            $position = 1; // Initialize position for product_download

            foreach (new DirectoryIterator($productFolder->getPathname()) as $mediaFile) {
                if ($mediaFile->isDot() || !$mediaFile->isFile()) {
                    continue;
                }

                // Prepare media attributes
                $fileName = pathinfo($mediaFile->getFilename(), PATHINFO_FILENAME);
                $fileExtension = strtolower($mediaFile->getExtension());
                $filePath = $mediaFile->getPathname();
                $fileSize = filesize($filePath);
                $mediaType = getMediaType($fileExtension);

                // Get MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);



                $uploadedAt = date('Y-m-d H:i:s');
                $createdAt = date('Y-m-d H:i:s');
                $updatedAt = date('Y-m-d H:i:s');

                // Set the path to match the actual file structure
                $path = "media/ESDRep/{$productName}/{$fileName}.{$fileExtension}";

                // Check for duplicates in media table
                $checkMediaSql = "SELECT id FROM media WHERE file_name = :file_name AND file_size = :file_size AND path = :path";
                $checkMediaStmt = $conn->prepare($checkMediaSql);
                $checkMediaStmt->execute([
                    ':file_name' => $fileName,
                    ':file_size' => $fileSize,
                    ':path' => $path,
                ]);

                $existingMediaId = $checkMediaStmt->fetchColumn();

                if ($existingMediaId) {
                    $mediaId = $existingMediaId;

                    // Update media table
                    $updateMediaSql = "UPDATE media SET 
    updated_at = :updated_at,
    meta_data = :meta_data,
    media_folder_id = :media_folder_id
WHERE id = :id";

                    $updateMediaStmt = $conn->prepare($updateMediaSql);
                    $updateMediaStmt->execute([
                        ':id' => $existingMediaId,
                        ':updated_at' => $updatedAt,
                        ':meta_data' => $metaData, // Provide appropriate meta_data value
                        ':media_folder_id' => $mediaFolderId // Provide appropriate media_folder_id value
                    ]);

                    echo "Updated media: '$fileName' with ID: " . binToHex($existingMediaId) . "<br>";
                    // Update media_translation table
                    $updateMediaTranslationSql = "UPDATE media_translation SET 
        updated_at = :updated_at,
        title = :title
    WHERE media_id = :media_id AND language_id = :language_id";

                    $updateMediaTranslationStmt = $conn->prepare($updateMediaTranslationSql);
                    $updateMediaTranslationStmt->execute([
                        ':media_id' => $mediaId,
                        ':language_id' => $languageId,
                        ':title' => $productName,
                        ':updated_at' => $updatedAt,
                    ]);

                    echo "Updated media_translation for media ID: " . binToHex($mediaId) . " and title: $productName<br>";
                } else {
                    // Insert new media record
                    $mediaId = generateUniqueUuidV4Binary();
                    $insertMediaSql = "INSERT INTO media (
        id, user_id, media_folder_id, mime_type, file_extension, file_size, 
        meta_data, file_name, media_type, thumbnails_ro, private, 
        uploaded_at, created_at, updated_at, path, config
    ) VALUES (
        :id, :user_id, :media_folder_id, :mime_type, :file_extension, :file_size, 
        :meta_data, :file_name, :media_type, :thumbnails_ro, 1, 
        :uploaded_at, :created_at, :updated_at, :path, null
    )";

                    $insertMediaStmt = $conn->prepare($insertMediaSql);
                    $insertMediaStmt->execute([
                        ':id' => $mediaId,
                        ':user_id' => $userId,
                        ':media_folder_id' => $mediaFolderId,
                        ':mime_type' => $mimeType,
                        ':file_extension' => $fileExtension,
                        ':file_size' => $fileSize,
                        ':meta_data' => $metaData,
                        ':file_name' => $fileName,
                        ':media_type' => $mediaType,
                        ':thumbnails_ro' => $thumbnailsRo,
                        ':uploaded_at' => $uploadedAt,
                        ':created_at' => $createdAt,
                        ':updated_at' => $updatedAt,
                        ':path' => $path,
                    ]);

                    echo "Inserted media: '$fileName' with ID: " . binToHex($mediaId) . " on Date :" . $createdAt . " " . "<br>";

                    // Insert into media_translation
                    $insertMediaTranslationSql = "INSERT INTO media_translation (
        media_id, language_id, alt, title, custom_fields, created_at, updated_at
    ) VALUES (
        :media_id, :language_id, NULL, :title, NULL, :created_at, :updated_at
    )";

                    $insertMediaTranslationStmt = $conn->prepare($insertMediaTranslationSql);
                    $insertMediaTranslationStmt->execute([
                        ':media_id' => $mediaId,
                        ':language_id' => $languageId,
                        ':title' => $productName,
                        ':created_at' => $createdAt,
                        ':updated_at' => $updatedAt,
                    ]);

                    echo "Inserted media_translation for media ID: " . binToHex($mediaId) . " and title: $productName<br>";
                }


                // Check for product_download
                $checkDownloadSql = "SELECT id FROM product_download WHERE product_id = :product_id AND media_id = :media_id";
                $checkDownloadStmt = $conn->prepare($checkDownloadSql);
                $checkDownloadStmt->execute([
                    ':product_id' => $productId,
                    ':media_id' => $mediaId,
                ]);

                $existingDownloadId = $checkDownloadStmt->fetchColumn();

                if ($existingDownloadId) {
                    // Update product_download
                    $updateDownloadSql = "UPDATE product_download SET 
                        position = :position,
                        updated_at = :updated_at
                    WHERE id = :id";

                    $updateDownloadStmt = $conn->prepare($updateDownloadSql);
                    $updateDownloadStmt->execute([
                        ':id' => $existingDownloadId,
                        ':position' => $position,
                        ':updated_at' => $updatedAt,
                    ]);

                    echo "Updated product download with ID: " . binToHex($existingDownloadId) . "and media ID"  . binToHex($mediaId) . "<br>";
                } else {
                    // Insert into product_download
                    $downloadId = generateUniqueUuidV4Binary();
                    $insertDownloadSql = "INSERT INTO product_download (
                        id, version_id, position, product_id, product_version_id, media_id, 
                        custom_fields, created_at, updated_at
                    ) VALUES (
                        :id, :version_id, :position, :product_id, :product_version_id, :media_id, 
                        NULL, :created_at, :updated_at
                    )";

                    $insertDownloadStmt = $conn->prepare($insertDownloadSql);
                    $insertDownloadStmt->execute([
                        ':id' => $downloadId,
                        ':version_id' => $versionId,
                        ':position' => $position,
                        ':product_id' => $productId,
                        ':product_version_id' => $productVersionId,
                        ':media_id' => $mediaId,
                        ':created_at' => $createdAt,
                        ':updated_at' => $updatedAt,
                    ]);

                    echo "Inserted product download with ID: " . binToHex($downloadId) . "and media ID"  . binToHex($mediaId) . "<br>";
                }

                $position++;
            }

            // Update product_media
            $updateProductMediaSql = "UPDATE product_media SET updated_at = :updated_at WHERE product_id = :product_id";
            $updateProductMediaStmt = $conn->prepare($updateProductMediaSql);
            $updateProductMediaStmt->execute([
                ':updated_at' => $updatedAt,
                ':product_id' => $productId,
            ]);

            echo "Updated product_media updated_at for product ID: " . binToHex($productId) . "<br>";

            // Update product
            $updateProductSql = "UPDATE product SET updated_at = :updated_at WHERE id = :product_id";
            $updateProductStmt = $conn->prepare($updateProductSql);
            $updateProductStmt->execute([
                ':updated_at' => $updatedAt,
                ':product_id' => $productId,
            ]);

            echo "Updated product updated_at for product ID: " . binToHex($productId) . "<br>";
        }
    } else {
        echo "<p style='color:red;'>Error: The 'ESDRep' folder does not exist.</p>";
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
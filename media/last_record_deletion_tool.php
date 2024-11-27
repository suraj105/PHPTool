<?php
// Handle the AJAX request for deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Database credentials and connection
    $host = 'localhost';
    $dbname = 'database';
    $username = 'user';
    $password = 'pass';

    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $createdAt = $_POST['created_at'] ?? null;
        $tillCreatedAt = $_POST['till_created_at'] ?? null;

        if (empty($createdAt)) {
            echo json_encode(["error" => "Error: Please provide a valid `created_at` timestamp."]);
            exit;
        }

        $logs = [];
        $logs[] = "Deleting records from: $createdAt";
        if (!empty($tillCreatedAt)) {
            $logs[] = "Till timestamp: $tillCreatedAt";
        } else {
            $logs[] = "Till timestamp not provided.";
        }

        // Prepare the WHERE clause
        $whereClause = "created_at = :created_at";
        $params = [':created_at' => $createdAt];
        if (!empty($tillCreatedAt)) {
            $whereClause = "created_at BETWEEN :created_at AND :till_created_at";
            $params[':till_created_at'] = $tillCreatedAt;
        }

        // Delete records from `product_download`
        $deleteProductDownloadSql = "DELETE FROM product_download WHERE $whereClause";
        $deleteProductDownloadStmt = $conn->prepare($deleteProductDownloadSql);
        $deleteProductDownloadStmt->execute($params);
        $logs[] = "Deleted " . $deleteProductDownloadStmt->rowCount() . " records from `product_download`";

        // Delete records from `media_translation`
        $deleteMediaTranslationSql = "DELETE FROM media_translation WHERE $whereClause";
        $deleteMediaTranslationStmt = $conn->prepare($deleteMediaTranslationSql);
        $deleteMediaTranslationStmt->execute($params);
        $logs[] = "Deleted " . $deleteMediaTranslationStmt->rowCount() . " records from `media_translation`";

        // Delete records from `media`
        $deleteMediaSql = "DELETE FROM media WHERE $whereClause";
        $deleteMediaStmt = $conn->prepare($deleteMediaSql);
        $deleteMediaStmt->execute($params);
        $logs[] = "Deleted " . $deleteMediaStmt->rowCount() . " records from `media`";

        $logs[] = "Deletion completed successfully.";

        echo json_encode(["success" => true, "logs" => $logs]);
    } catch (PDOException $e) {
        echo json_encode(["error" => "Connection failed: " . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Delete Records</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            height: 100vh;
            padding: 20px;
            box-sizing: border-box;
        }
        .container {
            display: flex;
            width: 100%;
            max-width: 1200px;
            margin: auto;
        }
        .form-container {
            flex: 1;
            max-width: 40%;
            background: #ffffff;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            color: #333;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="text"] {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }
        button {
            width: 48%;
            padding: 10px;
            font-size: 14px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button[type="submit"] {
            background-color: #4CAF50;
            color: white;
            margin-right: 4%;
        }
        button:last-child {
            background-color: #f44336;
            color: white;
        }
        button:hover {
            opacity: 0.9;
        }
        p {
            text-align: center;
            font-size: 12px;
            color: #777;
        }
        code {
            background-color: #f1f1f1;
            padding: 2px 4px;
            border-radius: 4px;
            font-family: monospace;
        }
        .messages-container {
            flex: 2;
            margin-left: 20px;
            padding: 20px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            max-height: 80vh;
            font-size: 14px;
        }
        .log-entry {
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 5px;
            background: #f4f4f4;
            color: #333;
            line-height: 1.6;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="form-container">
        <h2>Delete Records</h2>
        <form id="delete-form">
            <label for="created_at">Enter `from` Timestamp:</label>
            <input type="text" id="created_at" name="created_at" placeholder="YYYY-MM-DD HH:MM:SS.sss" required>

            <label for="till_created_at">Enter `till` Timestamp (optional):</label>
            <input type="text" id="till_created_at" name="till_created_at" placeholder="YYYY-MM-DD HH:MM:SS.sss">

            <div style="display: flex; justify-content: space-between;">
                <button type="submit">Delete Records</button>
                <button type="reset">Reset</button>
            </div>
            <p>Example format: <code>2024-11-26 11:23:09.000</code></p>
        </form>
    </div>

    <div class="messages-container" id="messages-container">
        <h2>Messages</h2>
    </div>
</div>

<script>
    document.getElementById("delete-form").addEventListener("submit", function(event) {
        event.preventDefault();

        const fromTimestamp = document.getElementById("created_at").value;
        const tillTimestamp = document.getElementById("till_created_at").value;

        const messagesContainer = document.getElementById("messages-container");
        messagesContainer.innerHTML = "<h2>Messages</h2>"; // Clear old messages

        const formData = new FormData();
        formData.append("created_at", fromTimestamp);
        formData.append("till_created_at", tillTimestamp);

        fetch("", {
            method: "POST",
            body: formData,
        })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    const errorLog = document.createElement("div");
                    errorLog.classList.add("log-entry");
                    errorLog.style.color = "red";
                    errorLog.textContent = data.error;
                    messagesContainer.appendChild(errorLog);
                } else if (data.success) {
                    data.logs.forEach(log => {
                        const logEntry = document.createElement("div");
                        logEntry.classList.add("log-entry");
                        logEntry.textContent = log;
                        messagesContainer.appendChild(logEntry);
                    });
                    messagesContainer.scrollTop = messagesContainer.scrollHeight; // Auto-scroll
                }
            })
            .catch(error => {
                const errorLog = document.createElement("div");
                errorLog.classList.add("log-entry");
                errorLog.style.color = "red";
                errorLog.textContent = "An error occurred: " + error.message;
                messagesContainer.appendChild(errorLog);
            });
    });
</script>
</body>
</html>

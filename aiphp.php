<?php
// Database connection
$host = 'localhost'; // Database host
$dbname = 'chatbot_db'; // Database name
$username = 'root'; // Database username
$password = ''; // Database password

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Save conversation to database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_message']) && isset($_POST['bot_response'])) {
    $userMessage = $_POST['user_message'];
    $botResponse = $_POST['bot_response'];

    $stmt = $conn->prepare("INSERT INTO conversation_history (user_message, bot_response) VALUES (:user_message, :bot_response)");
    $stmt->bindParam(':user_message', $userMessage);
    $stmt->bindParam(':bot_response', $botResponse);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save conversation']);
    }
    exit;
}

// Retrieve conversation history
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    $stmt = $conn->query("SELECT * FROM conversation_history ORDER BY timestamp DESC");
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($conversations);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chatbot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Add your CSS styles here */
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .chat-container {
            width: 90%;
            max-width: 400px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        #chat-box {
            height: 400px;
            overflow-y: auto;
            padding: 15px;
            display: flex;
            flex-direction: column;
            background: #f9f9f9;
        }

        .chat-message {
            max-width: 80%;
            padding: 10px 15px;
            margin: 8px;
            border-radius: 12px;
            display: inline-block;
            word-wrap: break-word;
            animation: slideIn 0.3s ease-in-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .user-message {
            background: #0078ff;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 5px;
        }

        .bot-message {
            background: #e1e1e1;
            color: black;
            align-self: flex-start;
            border-bottom-left-radius: 5px;
        }

        .input-area {
            display: flex;
            padding: 10px;
            border-top: 1px solid #ddd;
            background: #fff;
            align-items: center;
        }

        input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 20px;
            outline: none;
            font-size: 16px;
            margin: 0 10px;
            transition: border-color 0.3s ease;
        }

        input:focus {
            border-color: #0078ff;
        }

        button {
            background: #0078ff;
            border: none;
            color: white;
            padding: 10px 15px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            transition: background 0.3s ease;
        }

        button:hover {
            background: #005bb5;
        }

        .loading-indicator {
            display: flex;
            align-items: center;
            padding: 5px;
        }

        .loading-indicator div {
            width: 8px;
            height: 8px;
            margin: 2px;
            background: #0078ff;
            border-radius: 50%;
            animation: bounce 1.4s infinite;
        }

        .loading-indicator div:nth-child(1) { animation-delay: 0s; }
        .loading-indicator div:nth-child(2) { animation-delay: 0.2s; }
        .loading-indicator div:nth-child(3) { animation-delay: 0.4s; }

        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }

        .emoji-picker {
            display: none;
            position: absolute;
            bottom: 70px;
            right: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 10px;
            z-index: 100;
        }

        .emoji-picker span {
            cursor: pointer;
            font-size: 20px;
            margin: 5px;
            transition: transform 0.2s ease;
        }

        .emoji-picker span:hover {
            transform: scale(1.2);
        }

        .attach-btn {
            cursor: pointer;
            font-size: 20px;
            color: #0078ff;
            margin-right: 10px;
        }

        .attach-btn:hover {
            color: #005bb5;
        }

        @media (max-width: 480px) {
            .chat-container {
                width: 100%;
                height: 100vh;
                border-radius: 0;
            }

            #chat-box {
                height: calc(100vh - 80px);
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div id="chat-box"></div>

        <div class="input-area">
            <label for="file-input" class="attach-btn">📎</label>
            <input type="file" id="file-input" hidden onchange="previewImage(event)">
            <button class="emoji-btn" onclick="toggleEmojiPicker()">😊</button>
            <input type="text" id="user-input" placeholder="Type a message...">
            <button class="send-btn" onclick="sendMessage()">⬆️</button>
        </div>

        <div id="emoji-picker" class="emoji-picker">
            <span onclick="addEmoji('😀')">😀</span>
            <span onclick="addEmoji('😂')">😂</span>
            <span onclick="addEmoji('😍')">😍</span>
            <span onclick="addEmoji('🤖')">🤖</span>
            <span onclick="addEmoji('👍')">👍</span>
            <!-- Add more emojis as needed -->
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
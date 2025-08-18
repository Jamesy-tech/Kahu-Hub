<?php
session_start();

// Create data directory if it doesn't exist
if (!file_exists('data')) {
    mkdir('data', 0777, true);
}

// File paths
$usersFile = 'data/users.json';
$articlesFile = 'data/articles.json';
$notificationsFile = 'data/notifications.json';

// Initialize files if they don't exist
if (!file_exists($usersFile)) {
    file_put_contents($usersFile, json_encode([]));
}
if (!file_exists($articlesFile)) {
    file_put_contents($articlesFile, json_encode([]));
}
if (!file_exists($notificationsFile)) {
    file_put_contents($notificationsFile, json_encode([]));
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'login':
            $username = trim($input['username']);
            if (!empty($username)) {
                $users = json_decode(file_get_contents($usersFile), true);
                
                // Check if username already exists and session doesn't match
                if (in_array($username, $users) && (!isset($_SESSION['username']) || $_SESSION['username'] !== $username)) {
                    echo json_encode(['success' => false, 'error' => 'Username is already taken']);
                    break;
                }
                
                if (!in_array($username, $users)) {
                    $users[] = $username;
                    file_put_contents($usersFile, json_encode($users));
                }
                $_SESSION['username'] = $username;
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Username required']);
            }
            break;

        case 'logout':
            session_destroy();
            echo json_encode(['success' => true]);
            break;

        case 'getArticles':
            $articles = json_decode(file_get_contents($articlesFile), true);
            echo json_encode($articles);
            break;

        case 'publishArticle':
            if (!isset($_SESSION['username'])) {
                echo json_encode(['success' => false, 'error' => 'Not logged in']);
                break;
            }
            
            $title = trim($input['title']);
            $content = trim($input['content']);
            
            if (!empty($title) && !empty($content)) {
                $articles = json_decode(file_get_contents($articlesFile), true);
                $newArticle = [
                    'id' => uniqid(),
                    'title' => $title,
                    'content' => $content,
                    'author' => $_SESSION['username'],
                    'timestamp' => date('Y-m-d H:i:s'),
                    'date' => date('M j, Y')
                ];
                array_unshift($articles, $newArticle);
                file_put_contents($articlesFile, json_encode($articles));
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Title and content required']);
            }
            break;

        case 'deleteArticle':
            if (!isset($_SESSION['username'])) {
                echo json_encode(['success' => false, 'error' => 'Not logged in']);
                break;
            }
            
            $articleId = $input['articleId'];
            $articles = json_decode(file_get_contents($articlesFile), true);
            
            foreach ($articles as $key => $article) {
                if ($article['id'] === $articleId && $article['author'] === $_SESSION['username']) {
                    unset($articles[$key]);
                    $articles = array_values($articles); // Reindex array
                    file_put_contents($articlesFile, json_encode($articles));
                    echo json_encode(['success' => true]);
                    exit;
                }
            }
            echo json_encode(['success' => false, 'error' => 'Article not found or not authorized']);
            break;

        case 'shareArticle':
            if (!isset($_SESSION['username'])) {
                echo json_encode(['success' => false, 'error' => 'Not logged in']);
                break;
            }
            
            $title = trim($input['title']);
            $recipient = trim($input['recipient']);
            
            if (!empty($title) && !empty($recipient)) {
                $notifications = json_decode(file_get_contents($notificationsFile), true);
                $newNotification = [
                    'id' => uniqid(),
                    'title' => $_SESSION['username'] . ' shared: ' . $title,
                    'meta' => 'Shared ' . date('M j, Y g:i A'),
                    'recipient' => $recipient,
                    'from' => $_SESSION['username'],
                    'articleTitle' => $title,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                array_unshift($notifications, $newNotification);
                file_put_contents($notificationsFile, json_encode($notifications));
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Title and recipient required']);
            }
            break;

        case 'getNotifications':
            if (!isset($_SESSION['username'])) {
                echo json_encode([]);
                break;
            }
            
            $notifications = json_decode(file_get_contents($notificationsFile), true);
            $userNotifications = array_filter($notifications, function($notification) {
                return $notification['recipient'] === $_SESSION['username'];
            });
            echo json_encode(array_values($userNotifications));
            break;

        case 'checkSession':
            if (isset($_SESSION['username'])) {
                echo json_encode(['loggedIn' => true, 'username' => $_SESSION['username']]);
            } else {
                echo json_encode(['loggedIn' => false]);
            }
            break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kahu Hub</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans', Helvetica, Arial, sans-serif;
            background-color: #ffffff;
            color: #24292f;
            line-height: 1.5;
        }

        /* Login Screen */
        .login-screen {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f6f8fa;
        }

        .login-box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            border: 1px solid #d0d7de;
            box-shadow: 0 8px 24px rgba(140, 149, 159, 0.2);
            text-align: center;
            min-width: 300px;
        }

        .login-box h1 {
            margin-bottom: 24px;
            font-size: 32px;
            font-weight: 300;
            color: #24292f;
        }

        .login-box input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 16px;
        }

        .login-box input:focus {
            outline: none;
            border-color: #0969da;
        }

        .login-btn {
            width: 100%;
            background-color: #238636;
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }

        .login-btn:hover {
            background-color: #2da44e;
        }

        /* Main App */
        .main-app {
            display: none;
        }

        /* Navigation */
        .navbar {
            background-color: #24292f;
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #30363d;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .logo {
            color: #f0f6fc;
            font-size: 20px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .search-container {
            position: relative;
        }

        .search-bar {
            background-color: #21262d;
            border: 1px solid #30363d;
            color: #f0f6fc;
            padding: 8px 12px;
            border-radius: 6px;
            width: 300px;
            font-size: 14px;
        }

        .search-bar:focus {
            outline: none;
            border-color: #58a6ff;
        }

        .search-bar::placeholder {
            color: #8b949e;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .notification-btn {
            background: none;
            border: none;
            color: #f0f6fc;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            position: relative;
            font-size: 16px;
        }

        .notification-btn:hover {
            background-color: #30363d;
        }

        .notification-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            background-color: #da3633;
            color: white;
            border-radius: 50%;
            width: 8px;
            height: 8px;
        }

        .user-info {
            color: #8b949e;
            font-size: 14px;
        }

        .logout-btn {
            background: none;
            border: 1px solid #30363d;
            color: #f0f6fc;
            padding: 6px 12px;
            display:none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }

        .logout-btn:hover {
            background-color: #30363d;
        }

        /* Notifications Panel */
        .notifications-panel {
            display: none;
            position: fixed;
            top: 70px;
            right: 32px;
            width: 300px;
            background: white;
            border: 1px solid #d0d7de;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(140, 149, 159, 0.2);
            z-index: 1500;
            max-height: 400px;
            overflow-y: auto;
        }

        .notifications-header {
            padding: 16px;
            border-bottom: 1px solid #d0d7de;
            font-weight: 600;
            background-color: #f6f8fa;
        }

        .notification-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f6f8fa;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .notification-item:hover {
            background-color: #f6f8fa;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-title {
            font-weight: 500;
            margin-bottom: 4px;
        }

        .notification-meta {
            font-size: 12px;
            color: #8b949e;
        }

        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #8b949e;
        }

        .empty-state h2 {
            margin-bottom: 16px;
            font-size: 24px;
            font-weight: 400;
        }

        .articles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 120px;
        }

        .article-card {
            background: white;
            border: 1px solid #d0d7de;
            border-radius: 8px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .article-card:hover {
            border-color: #8b949e;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(140, 149, 159, 0.15);
        }

        .article-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #0969da;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .article-preview {
            color: #656d76;
            font-size: 14px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .article-meta {
            margin-top: 12px;
            font-size: 12px;
            color: #8b949e;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .delete-btn {
            background-color: #da3633;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
        }

        .delete-btn:hover {
            background-color: #b91c1c;
        }

        /* Create Article Section */
        .create-section {
            position: fixed;
            bottom: 32px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 12px;
            align-items: center;
            background: white;
            padding: 16px 24px;
            border-radius: 8px;
            border: 1px solid #d0d7de;
            box-shadow: 0 8px 24px rgba(140, 149, 159, 0.2);
            z-index: 100;
        }

        .create-input {
            border: 1px solid #d0d7de;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 14px;
            width: 400px;
            outline: none;
        }

        .create-input:focus {
            border-color: #0969da;
        }

        .create-btn {
            background-color: #238636;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }

        .create-btn:hover {
            background-color: #2da44e;
        }

        /* Article Editor */
        .editor {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: white;
            z-index: 1000;
            flex-direction: column;
        }

        .editor-header {
            background: #f6f8fa;
            padding: 16px 32px;
            border-bottom: 1px solid #d0d7de;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .editor-title {
            font-size: 32px;
            font-weight: 300;
            border: none;
            outline: none;
            background: transparent;
            flex-grow: 1;
            margin-right: 20px;
        }

        .editor-actions {
            display: flex;
            gap: 12px;
        }

        .editor-btn {
            padding: 8px 16px;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            font-size: 14px;
        }

        .editor-btn:hover {
            background-color: #f6f8fa;
        }

        .editor-btn.primary {
            background-color: #238636;
            color: white;
            border-color: #238636;
        }

        .editor-btn.primary:hover {
            background-color: #2da44e;
        }

        .editor-toolbar {
            padding: 12px 32px;
            border-bottom: 1px solid #d0d7de;
            display: flex;
            gap: 8px;
            background-color: #f6f8fa;
        }

        .toolbar-btn {
            padding: 6px 12px;
            border: 1px solid #d0d7de;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }

        .toolbar-btn:hover {
            background-color: #e1e4e8;
        }

        .editor-content {
            flex-grow: 1;
            padding: 32px;
            overflow-y: auto;
        }

        .editor-textarea {
            width: 100%;
            height: 100%;
            min-height: 400px;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            padding: 16px;
            font-size: 14px;
            line-height: 1.6;
            font-family: inherit;
            resize: none;
            outline: none;
        }

        .editor-textarea:focus {
            border-color: #0969da;
        }

        /* Article View */
        .article-view {
            display: none;
            max-width: 800px;
            margin: 0 auto;
            padding: 32px;
        }

        .article-view-header {
            margin-bottom: 32px;
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #d0d7de;
        }

        .article-view-title {
            font-size: 36px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .article-view-meta {
            color: #8b949e;
            font-size: 14px;
        }

        .article-view-content {
            font-size: 16px;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .article-view-actions {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #d0d7de;
            text-align: center;
        }

        .share-article-btn {
            background-color: #0969da;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }

        /* Search Results Page */
        .search-results {
            display: none;
        }

        .search-header {
            margin-bottom: 32px;
            padding-bottom: 16px;
            border-bottom: 1px solid #d0d7de;
        }

        .search-title {
            font-size: 24px;
            font-weight: 600;
            color: #24292f;
        }

        .search-count {
            font-size: 14px;
            color: #8b949e;
            margin-top: 8px;
        }

        .back-btn {
            margin-bottom: 20px;
            padding: 8px 16px;
            background-color: #f6f8fa;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            cursor: pointer;
            color: #24292f;
            text-decoration: none;
            display: inline-block;
        }

        .back-btn:hover {
            background-color: #e1e4e8;
        }

        /* Share Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 32px;
            width: 400px;
            text-align: center;
        }

        .modal h3 {
            margin-bottom: 20px;
            font-size: 20px;
        }

        .modal input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .modal input:focus {
            outline: none;
            border-color: #0969da;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        /* Loading state */
        .loading {
            text-align: center;
            padding: 40px;
            color: #8b949e;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar {
                padding: 16px;
                flex-direction: column;
                gap: 16px;
            }

            .nav-left, .nav-right {
                flex-direction: row;
                align-items: center;
            }

            .search-bar {
                width: 200px;
            }

            .create-input {
                width: 250px;
            }

            .articles-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 16px;
            }

            .create-section {
                flex-direction: column;
                width: 90%;
            }

            .create-input {
                width: 100%;
            }

            .notifications-panel {
                right: 16px;
                width: calc(100% - 32px);
            }
        }
    </style>
</head>
<body>

    <div class="login-screen" id="loginScreen">
        <div class="login-box">
            <h1>Kahu Hub</h1>
            <input type="text" id="usernameInput" placeholder="Enter your username" maxlength="20">
            <button class="login-btn" onclick="login()">Enter</button>
        </div>
    </div>

    <div class="main-app" id="mainApp">
        <nav class="navbar">
            <div class="nav-left">
                <a class="logo" onclick="showHome()">Kahu Hub</a>
            </div>
            <div class="search-container">
                <input type="text" class="search-bar" placeholder="search articles..." id="searchBar">
            </div>
            <div class="nav-right">
                <button class="notification-btn" onclick="toggleNotifications()">
                    🔔
                    <div class="notification-badge" id="notificationBadge" style="display: none;"></div>
                </button>
                <span class="user-info" id="userInfo">Logged in as: </span>
                <button class="logout-btn" onclick="logout()">Logout</button> 
            </div>
        </nav>

        <div class="notifications-panel" id="notificationsPanel">
            <div class="notifications-header">Notifications</div>
            <div id="notificationsList">
                <div class="loading">Loading...</div>
            </div>
        </div>

        <div class="container" id="homeView">
            <div id="articlesContainer">
                <div class="loading">Loading articles...</div>
            </div>
        </div>

        <div class="container search-results" id="searchResults">
            <div class="search-header">
                <button class="back-btn" onclick="showHome()">← Home</button>
                <h1 class="search-title" id="searchTitle">Search Results</h1>
                <p class="search-count" id="searchCount"></p>
            </div>
            <div id="searchContainer">

            </div>
        </div>

        <div class="article-view" id="articleView">
            <button class="back-btn" onclick="showHome()">← Back to articles</button>
            <div class="article-view-header">
                <h1 class="article-view-title" id="articleViewTitle"></h1>
                <div class="article-view-meta" id="articleViewMeta"></div>
            </div>
            <div class="article-view-content" id="articleViewContent"></div>
            <div class="article-view-actions">
                <button class="share-article-btn" onclick="shareCurrentArticle()">Share this article</button>
            </div>
        </div>

        <div class="create-section">
            <input type="text" class="create-input" id="createInput" placeholder="What's your article about?" maxlength="100">
            <button class="create-btn" onclick="openEditor()">Create Article</button>
        </div>

        <div class="editor" id="editor">
            <div class="editor-header">
                <input type="text" class="editor-title" id="editorTitle" placeholder="Article Title">
                <div class="editor-actions">
                    <button class="editor-btn" onclick="closeEditor()">Cancel</button>
                    <button class="editor-btn" onclick="shareArticle()">Share</button>
                    <button class="editor-btn primary" onclick="publishArticle()">Publish</button>
                </div>
            </div>
            <div class="editor-toolbar">
                <button class="toolbar-btn" onclick="formatText('bold')" id="boldBtn"><b>B</b></button>
                <button class="toolbar-btn" onclick="formatText('italic')" id="italicBtn"><i>I</i></button>
                <button class="toolbar-btn" onclick="formatText('underline')" id="underlineBtn"><u>U</u></button>
            </div>
            <div class="editor-content">
                <textarea class="editor-textarea" id="editorTextarea" placeholder="Start writing your article..."></textarea>
            </div>
        </div>

        <div class="modal" id="shareModal">
            <div class="modal-content">
                <h3>Share Article</h3>
                <input type="text" id="shareUsername" placeholder="Enter username to share with" maxlength="20">
                <div class="modal-actions">
                    <button class="editor-btn" onclick="closeShareModal()">Cancel</button>
                    <button class="editor-btn primary" onclick="sendShare()">Share</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentUser = '';
        let articles = [];
        let notifications = [];
        let currentArticle = null;

        async function apiCall(action, data = {}) {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({action, ...data})
                });
                return await response.json();
            } catch (error) {
                console.error('API call failed:', error);
                return {success: false, error: 'Network error'};
            }
        }

        async function init() {
            const session = await apiCall('checkSession');
            if (session.loggedIn) {
                currentUser = session.username;
                showMainApp();
            } else {
                showLoginScreen();
            }
        }

        async function login() {
            const username = document.getElementById('usernameInput').value.trim();
            if (username) {
                const result = await apiCall('login', {username});
                if (result.success) {
                    currentUser = username;
                    showMainApp();
                } else {
                    alert(result.error || 'Login failed');
                }
            } else {
                alert('Please enter a username');
            }
        }

        async function logout() {
            await apiCall('logout');
            currentUser = '';
            showLoginScreen();
        }

        function showLoginScreen() {
            document.getElementById('loginScreen').style.display = 'flex';
            document.getElementById('mainApp').style.display = 'none';
        }

        async function showMainApp() {
            document.getElementById('loginScreen').style.display = 'none';
            document.getElementById('mainApp').style.display = 'block';
            document.getElementById('userInfo').textContent = `Logged in as: ${currentUser}`;
            await loadArticles();
            await loadNotifications();
        }

        async function loadArticles() {
            const result = await apiCall('getArticles');
            articles = result || [];
            renderArticles();
        }

        async function loadNotifications() {
            const result = await apiCall('getNotifications');
            notifications = result || [];
            updateNotificationBadge();
        }

        function renderArticles() {
            const container = document.getElementById('articlesContainer');
            
            if (articles.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <h2>No articles yet</h2>
                        <p>Be the first to create an article!</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = '<div class="articles-grid" id="articlesGrid"></div>';
            const grid = document.getElementById('articlesGrid');

            articles.forEach(article => {
                const card = document.createElement('div');
                card.className = 'article-card';
                card.onclick = (e) => {
                    if (e.target.classList.contains('delete-btn')) {
                        return;
                    }
                    viewArticle(article);
                };

                const title = article.title.length > 60 ? article.title.substring(0, 60) + '...' : article.title;
                const preview = article.content.length > 150 ? article.content.substring(0, 150) + '...' : article.content;
                
                const deleteButton = article.author === currentUser ? 
                    `<button class="delete-btn" onclick="deleteArticle('${article.id}', event)">Delete</button>` : '';

                card.innerHTML = `
                    <div class="article-title">${title}</div>
                    <div class="article-preview">${preview}</div>
                    <div class="article-meta">
                        <span>By ${article.author} • ${article.date}</span>
                        ${deleteButton}
                    </div>
                `;

                grid.appendChild(card);
            });
        }

        async function deleteArticle(articleId, event) {
            event.stopPropagation();
            if (confirm('Are you sure you want to delete this article?')) {
                const result = await apiCall('deleteArticle', {articleId});
                if (result.success) {
                    await loadArticles();
                } else {
                    alert(result.error || 'Failed to delete article');
                }
            }
        }

        function viewArticle(article) {
            currentArticle = article;
            document.getElementById('articleViewTitle').textContent = article.title;
            document.getElementById('articleViewMeta').textContent = `By ${article.author} • ${article.date}`;
            document.getElementById('articleViewContent').textContent = article.content;
            
            document.getElementById('homeView').style.display = 'none';
            document.getElementById('searchResults').style.display = 'none';
            document.getElementById('editor').style.display = 'none';
            document.getElementById('articleView').style.display = 'block';
        }

        function showHome() {
            document.getElementById('homeView').style.display = 'block';
            document.getElementById('articleView').style.display = 'none';
            document.getElementById('editor').style.display = 'none';
            document.getElementById('searchResults').style.display = 'none';
            document.getElementById('searchBar').value = '';
        }

        function openEditor() {
            const topic = document.getElementById('createInput').value.trim();
            if (topic) {
                document.getElementById('editorTitle').value = topic;
                document.getElementById('createInput').value = '';
            }
            document.getElementById('editor').style.display = 'flex';
            document.getElementById('editorTextarea').focus();
        }

        function closeEditor() {
            document.getElementById('editor').style.display = 'none';
            document.getElementById('editorTitle').value = '';
            document.getElementById('editorTextarea').value = '';
        }

        function formatText(format) {
            const textarea = document.getElementById('editorTextarea');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            
            if (selectedText) {
                let formattedText = selectedText;
                let formatLength = 0;
                
                switch(format) {
                    case 'bold':
                        formattedText = `**${selectedText}**`;
                        formatLength = 4; // ** at start and end
                        break;
                    case 'italic':
                        formattedText = `*${selectedText}*`;
                        formatLength = 2; // * at start and end
                        break;
                    case 'underline':
                        formattedText = `<u>${selectedText}</u>`;
                        formatLength = 7; // <u> and </u>
                        break;
                }
                
                const beforeText = textarea.value.substring(0, start);
                const afterText = textarea.value.substring(end);
                textarea.value = beforeText + formattedText + afterText;
                
                textarea.focus();
                const newCursorPos = start + formattedText.length;
                textarea.setSelectionRange(newCursorPos, newCursorPos);
            } else {
                let formatText = '';
                let cursorOffset = 0;
                
                switch(format) {
                    case 'bold':
                        formatText = '****';
                        cursorOffset = 2;
                        break;
                    case 'italic':
                        formatText = '**';
                        cursorOffset = 1;
                        break;
                    case 'underline':
                        formatText = '<u></u>';
                        cursorOffset = 3;
                        break;
                }
                
                const cursorPos = textarea.selectionStart;
                const beforeText = textarea.value.substring(0, cursorPos);
                const afterText = textarea.value.substring(cursorPos);
                textarea.value = beforeText + formatText + afterText;
                
                textarea.focus();
                textarea.setSelectionRange(cursorPos + cursorOffset, cursorPos + cursorOffset);
            }
        }

        async function publishArticle() {
            const title = document.getElementById('editorTitle').value.trim();
            const content = document.getElementById('editorTextarea').value.trim();
            
            if (title && content) {
                const result = await apiCall('publishArticle', {title, content});
                if (result.success) {
                    await loadArticles();
                    closeEditor();
                    showHome();
                    alert('Article published successfully!');
                } else {
                    alert(result.error || 'Failed to publish article');
                }
            } else {
                alert('Please fill in both title and content');
            }
        }

        function closeShareModal() {
            document.getElementById('shareModal').style.display = 'none';
            document.getElementById('shareUsername').value = '';
            document.getElementById('shareModal').dataset.articleTitle = '';
            document.getElementById('shareModal').dataset.shareMode = '';
        }

        function shareArticle() {
            const title = document.getElementById('editorTitle').value.trim();
            const content = document.getElementById('editorTextarea').value.trim();
            
            if (title && content) {
                document.getElementById('shareModal').style.display = 'flex';
                document.getElementById('shareModal').dataset.shareMode = 'editor';
            } else {
                alert('Please fill in both title and content before sharing');
            }
        }

        async function shareCurrentArticle() {
            if (currentArticle) {
                document.getElementById('shareModal').style.display = 'flex';
                document.getElementById('shareModal').dataset.articleTitle = currentArticle.title;
                document.getElementById('shareModal').dataset.shareMode = 'current';
            }
        }

        async function sendShare() {
            const recipient = document.getElementById('shareUsername').value.trim();
            const shareMode = document.getElementById('shareModal').dataset.shareMode;
            let title = '';
            
            if (shareMode === 'current') {
                title = document.getElementById('shareModal').dataset.articleTitle;
            } else {
                title = document.getElementById('editorTitle').value.trim();
            }
            
            if (recipient && title) {
                const result = await apiCall('shareArticle', {title, recipient});
                if (result.success) {
                    closeShareModal();
                    alert(`Article shared with ${recipient}!`);
                } else {
                    alert(result.error || 'Failed to share article');
                }
            } else {
                alert('Please enter a username');
            }
        }

        async function toggleNotifications() {
            const panel = document.getElementById('notificationsPanel');
            panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
            
            if (panel.style.display === 'block') {
                await loadNotifications();
                renderNotifications();
                document.getElementById('notificationBadge').style.display = 'none';
            }
        }

        function renderNotifications() {
            const list = document.getElementById('notificationsList');
            
            if (notifications.length === 0) {
                list.innerHTML = `
                    <div class="notification-item">
                        <div class="notification-title">No new notifications</div>
                        <div class="notification-meta">Check back later!</div>
                    </div>
                `;
            } else {
                list.innerHTML = notifications.map(notification => `
                    <div class="notification-item" onclick="openNotificationArticle('${notification.articleTitle}')">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-meta">${notification.meta}</div>
                    </div>
                `).join('');
            }
        }

        function openNotificationArticle(articleTitle) {
            const article = articles.find(art => art.title === articleTitle);
            
            if (article) {
                document.getElementById('notificationsPanel').style.display = 'none';
                viewArticle(article);
            } else {
                alert('Article not found. It may have been deleted.');
            }
        }

        function updateNotificationBadge() {
            const badge = document.getElementById('notificationBadge');
            if (notifications.length > 0) {
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }

        function searchArticles() {

        }

        function performSearch() {
            const query = document.getElementById('searchBar').value.trim().toLowerCase();
            
            if (!query) {
                showHome();
                return;
            }

            const filteredArticles = articles.filter(article => 
                article.title.toLowerCase().includes(query) || 
                article.content.toLowerCase().includes(query) ||
                article.author.toLowerCase().includes(query)
            );
            
            document.getElementById('homeView').style.display = 'none';
            document.getElementById('articleView').style.display = 'none';
            document.getElementById('editor').style.display = 'none';
            document.getElementById('searchResults').style.display = 'block';
            
            document.getElementById('searchTitle').textContent = `Search results for "${query}"`;
            document.getElementById('searchCount').textContent = `${filteredArticles.length} article${filteredArticles.length !== 1 ? 's' : ''} found`;
            
            const container = document.getElementById('searchContainer');
            
            if (filteredArticles.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <h2>No articles found</h2>
                        <p>Try searching for something else</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = '<div class="articles-grid" id="searchGrid"></div>';
            const grid = document.getElementById('searchGrid');

            filteredArticles.forEach(article => {
                const card = document.createElement('div');
                card.className = 'article-card';
                card.onclick = (e) => {
                    if (e.target.classList.contains('delete-btn')) {
                        return;
                    }
                    viewArticle(article);
                };

                const title = article.title.length > 60 ? article.title.substring(0, 60) + '...' : article.title;
                const preview = article.content.length > 150 ? article.content.substring(0, 150) + '...' : article.content;
                
                const deleteButton = article.author === currentUser ? 
                    `<button class="delete-btn" onclick="deleteArticle('${article.id}', event)">Delete</button>` : '';

                card.innerHTML = `
                    <div class="article-title">${title}</div>
                    <div class="article-preview">${preview}</div>
                    <div class="article-meta">
                        <span>By ${article.author} • ${article.date}</span>
                        ${deleteButton}
                    </div>
                `;

                grid.appendChild(card);
            });
        }

        // Event listeners
        document.addEventListener('click', function(event) {
            const panel = document.getElementById('notificationsPanel');
            const button = document.querySelector('.notification-btn');
            
            if (!panel.contains(event.target) && !button.contains(event.target)) {
                panel.style.display = 'none';
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('shareModal').style.display = 'none';
                document.getElementById('notificationsPanel').style.display = 'none';
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.getElementById('usernameInput');
            if (usernameInput) {
                usernameInput.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter') {
                        login();
                    }
                });
            }

            const searchBar = document.getElementById('searchBar');
            if (searchBar) {
                searchBar.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter') {
                        performSearch();
                    }
                });
            }

            const createInput = document.getElementById('createInput');
            if (createInput) {
                createInput.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter') {
                        openEditor();
                    }
                });
            }
        });

        init();
    </script>
</body>
</html>
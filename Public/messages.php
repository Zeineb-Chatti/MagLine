<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit;
}

// Get current user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, name, email, photo, role, manager_photo FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verify we got user data
if (!$current_user) {
    // Handle case where user doesn't exist
    session_destroy();
    header("Location: Login.php");
    exit;
}

// Set role based on database value (with fallback to session if not available)
if (!empty($current_user['role'])) {
    $_SESSION['role'] = $current_user['role'];
} elseif (!isset($_SESSION['role'])) {
    $_SESSION['role'] = 'candidate'; // Default fallback
}

// Function to determine profile photo using same logic as view_profile.php
function getProfilePhoto($user) {
    $profilePhoto = null;
    
    // Check if recruiter with manager photo
    if ($user['role'] === 'recruiter' && !empty($user['manager_photo'])) {
        $profilePhoto = '/Public/Uploads/Manager_Photos/' . $user['manager_photo'];
    } 
    // Check for regular profile photo
    elseif (!empty($user['photo'])) {
        $profilePhoto = '/Public/Uploads/profile_photos/' . $user['photo'];
    }
    
    // Use default if no photo found
    if (!$profilePhoto) {
        $profilePhoto = '/Public/Assets/default-user.png';
    }
    
    return $profilePhoto;
}

// Get current user's photo URL
$current_user_photo = getProfilePhoto($current_user);

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get the final determined role
$user_role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | <?= htmlspecialchars($current_user['name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="../Public/Assets/CSS/main.css" rel="stylesheet">
    <link href="../Public/Assets/CSS/candidate.css" rel="stylesheet">
    <link href="../Public/Assets/CSS/sidebar.css" rel="stylesheet">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-tertiary: #1a1a28;
            --bg-card: rgba(26, 26, 40, 0.8);
            --bg-overlay: rgba(0, 0, 0, 0.4);
            
            --text-primary: #ffffff;
            --text-secondary: #b4b4c7;
            --text-muted: #6b6b8a;
            --text-accent: #e6e6ff;
            
            --accent-primary: #6c5ce7;
            --accent-secondary: #a29bfe;
            --accent-gradient: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 50%, #fd79a8 100%);
            --accent-glow: rgba(108, 92, 231, 0.4);
            
            --success: #00b894;
            --warning: #fdcb6e;
            --danger: #e17055;
            
            --border-color: rgba(255, 255, 255, 0.08);
            --border-hover: rgba(255, 255, 255, 0.15);
            
            --sidebar-width: 340px;
            --header-height: 80px;
            
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.2);
            --shadow-glow: 0 0 30px var(--accent-glow);
            
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --radius-full: 9999px;
            
            --transition-fast: 0.15s ease;
            --transition-normal: 0.3s ease;
            --transition-slow: 0.5s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Enhanced scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(45deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 3px;
            transition: var(--transition-normal);
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(45deg, var(--accent-secondary), #fd79a8);
        }
        
        /* Animated background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 30%, rgba(108, 92, 231, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(162, 155, 254, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 70%, rgba(253, 121, 168, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(0, 184, 148, 0.05) 0%, transparent 50%);
            z-index: -2;
            animation: backgroundFloat 20s ease-in-out infinite alternate;
        }
        
        @keyframes backgroundFloat {
            0% { 
                transform: translateX(0) translateY(0) scale(1);
                opacity: 0.8;
            }
            33% { 
                transform: translateX(-20px) translateY(-10px) scale(1.02);
                opacity: 0.9;
            }
            66% { 
                transform: translateX(10px) translateY(20px) scale(0.98);
                opacity: 0.85;
            }
            100% { 
                transform: translateX(0) translateY(0) scale(1);
                opacity: 0.8;
            }
        }
        
        /* Header Enhancement */
        header {
            height: var(--header-height);
            background: rgba(18, 18, 26, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-md);
        }
        
        .logo {
            font-size: 1.75rem;
            font-weight: 800;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
        }
        
        .logo::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 120%;
            height: 120%;
            background: var(--accent-gradient);
            border-radius: var(--radius-full);
            filter: blur(20px);
            opacity: 0.3;
            z-index: -1;
            transform: translate(-50%, -50%);
            animation: logoGlow 3s ease-in-out infinite alternate;
        }
        
        @keyframes logoGlow {
            0% { opacity: 0.2; transform: translate(-50%, -50%) scale(0.8); }
            100% { opacity: 0.4; transform: translate(-50%, -50%) scale(1.1); }
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-full);
            object-fit: cover;
            border: 2px solid transparent;
            background: var(--accent-gradient);
            padding: 2px;
            transition: var(--transition-normal);
            cursor: pointer;
            position: relative;
        }
        
        .user-avatar::before {
            content: '';
            position: absolute;
            inset: -4px;
            background: var(--accent-gradient);
            border-radius: var(--radius-full);
            z-index: -1;
            opacity: 0;
            transition: var(--transition-normal);
        }
        
        .user-avatar:hover::before {
            opacity: 0.6;
        }
        
        .user-avatar:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: var(--shadow-glow);
        }
        
        /* Main Container */
        .main-container {
            display: flex;
            flex: 1;
            overflow: hidden;
            position: relative;
        }
        
        /* Enhanced Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(18, 18, 26, 0.98);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: relative;
            box-shadow: var(--shadow-lg);
        }
        
        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 1px;
            height: 100%;
            background: linear-gradient(to bottom, 
                transparent,
                var(--accent-primary),
                var(--accent-secondary),
                transparent
            );
            opacity: 0.6;
            animation: borderFlow 4s ease-in-out infinite alternate;
        }
        
        @keyframes borderFlow {
            0% { opacity: 0.3; }
            100% { opacity: 0.8; }
        }
        
        .sidebar-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
            background: rgba(26, 26, 40, 0.5);
        }
        
        .sidebar-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            letter-spacing: 0.02em;
        }
        
        .search-bar {
            width: 100%;
            padding: 14px 20px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-full);
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: var(--transition-normal);
            font-family: inherit;
        }
        
        .search-bar::placeholder {
            color: var(--text-muted);
        }
        
        .search-bar:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.15);
            transform: translateY(-1px);
        }
        
        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem 0;
        }
        
        .conversation-item {
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            transition: var(--transition-normal);
            border-bottom: 1px solid rgba(255, 255, 255, 0.02);
            position: relative;
            margin: 0 0.5rem;
            border-radius: var(--radius-md);
        }
        
        .conversation-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 3px;
            height: 0;
            background: var(--accent-gradient);
            border-radius: 0 3px 3px 0;
            transform: translateY(-50%);
            transition: var(--transition-normal);
        }
        
        .conversation-item:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(8px);
        }
        
        .conversation-item:hover::before {
            height: 70%;
        }
        
        .conversation-item.active {
            background: rgba(108, 92, 231, 0.15);
            border-color: var(--accent-primary);
        }
        
        .conversation-item.active::before {
            height: 100%;
        }
        
        .conversation-item.unread {
            background: rgba(108, 92, 231, 0.08);
        }
        
        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-full);
            object-fit: cover;
            border: 2px solid var(--accent-primary);
            transition: var(--transition-normal);
            position: relative;
        }
        
        .conversation-avatar::after {
            content: '';
            position: absolute;
            inset: -3px;
            background: var(--accent-gradient);
            border-radius: var(--radius-full);
            z-index: -1;
            opacity: 0;
            transition: var(--transition-normal);
        }
        
        .conversation-item:hover .conversation-avatar::after {
            opacity: 0.3;
        }
        
        .conversation-info {
            flex: 1;
            overflow: hidden;
        }
        
        .conversation-name {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-preview {
            font-size: 0.875rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-time {
            font-size: 0.75rem;
            color: var(--text-muted);
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            font-family: 'JetBrains Mono', monospace;
        }
        
        .unread-badge {
            background: var(--accent-gradient);
            color: white;
            border-radius: var(--radius-full);
            min-width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            position: absolute;
            bottom: 1rem;
            right: 1.5rem;
            padding: 0 8px;
            box-shadow: var(--shadow-glow);
            animation: pulseGlow 2s ease-in-out infinite;
        }
        
        @keyframes pulseGlow {
            0%, 100% { 
                transform: scale(1);
                box-shadow: var(--shadow-glow);
            }
            50% { 
                transform: scale(1.1);
                box-shadow: 0 0 20px var(--accent-glow);
            }
        }
        
        /* Enhanced Chat Container */
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: rgba(10, 10, 15, 0.98);
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
            height: calc(100vh - var(--header-height));
        }
        
        .chat-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            background: rgba(18, 18, 26, 0.95);
            backdrop-filter: blur(20px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            min-height: 90px;
            box-shadow: var(--shadow-sm);
        }
        
        .no-chat-selected {
            color: var(--text-muted);
            font-style: italic;
            font-size: 1.1rem;
            text-align: center;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .no-chat-selected::before,
        .no-chat-selected::after {
            content: '';
            height: 1px;
            background: linear-gradient(to right, transparent, var(--border-color), transparent);
            flex: 1;
        }
        
        .active-chat-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
        }
        
        .chat-user-avatar {
            width: 52px;
            height: 52px;
            border-radius: var(--radius-full);
            object-fit: cover;
            border: 2px solid var(--accent-primary);
            box-shadow: var(--shadow-glow);
        }
        
        .chat-user-info {
            flex: 1;
        }
        
        .chat-user-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .chat-user-status {
            font-size: 0.875rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* ✨ STUNNING VIEW PROFILE BUTTON ✨ */
        .view-profile-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #667eea 50%, #f093fb 75%, #f5576c 100%);
            background-size: 400% 400%;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: var(--radius-full);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 
                0 6px 24px rgba(102, 126, 234, 0.4),
                0 3px 12px rgba(245, 87, 108, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            letter-spacing: 0.05em;
            text-transform: uppercase;
            font-family: 'Inter', sans-serif;
            white-space: nowrap;
            animation: profileButtonGradient 8s ease-in-out infinite;
        }
        
        @keyframes profileButtonGradient {
            0%, 100% { background-position: 0% 50%; }
            25% { background-position: 100% 50%; }
            50% { background-position: 200% 50%; }
            75% { background-position: 300% 50%; }
        }
        
        .view-profile-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.4), 
                rgba(255, 255, 255, 0.6), 
                rgba(255, 255, 255, 0.4), 
                transparent
            );
            transition: left 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            z-index: 1;
        }
        
        .view-profile-btn::after {
            content: '';
            position: absolute;
            inset: -3px;
            background: linear-gradient(135deg, #667eea, #764ba2, #f093fb, #f5576c);
            border-radius: var(--radius-full);
            z-index: -1;
            opacity: 0;
            transition: all 0.4s ease;
            filter: blur(15px);
        }
        
        .view-profile-btn:hover {
            transform: translateY(-4px) scale(1.05);
            box-shadow: 
                0 16px 48px rgba(102, 126, 234, 0.6),
                0 8px 24px rgba(245, 87, 108, 0.5),
                0 4px 16px rgba(118, 75, 162, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.5);
            color: white;
            text-decoration: none;
            animation-duration: 4s;
        }
        
        .view-profile-btn:hover::before {
            left: 100%;
        }
        
        .view-profile-btn:hover::after {
            opacity: 0.8;
            transform: scale(1.1);
        }
        
        .view-profile-btn i {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            font-size: 1em;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
            z-index: 2;
            position: relative;
        }
        
        .view-profile-btn:hover i {
            transform: rotate(15deg) scale(1.2);
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.4));
        }
        
        .view-profile-btn:active {
            transform: translateY(-2px) scale(1.02);
            transition-duration: 0.1s;
        }
        
        .view-profile-btn span {
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        /* Magical sparkle effect */
        .view-profile-btn:hover::after {
            background: linear-gradient(135deg, 
                #667eea 0%, 
                #764ba2 25%, 
                #f093fb 50%, 
                #f5576c 75%, 
                #ffeaa7 100%
            );
        }
        
        .chat-messages {
            flex: 1;
            padding: 1rem 2rem;
            overflow-y: auto;
            background: linear-gradient(135deg, 
                rgba(10, 10, 15, 0.98) 0%, 
                rgba(18, 18, 26, 0.95) 100%
            );
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            min-height: 0;
        }
        
        #messagesList {
            display: flex;
            flex-direction: column;
           
            min-height: 0;
            gap: 0.5rem;
        }
        
        /* Load More Messages Button */
        .load-more-messages {
            align-self: center;
            background: rgba(26, 26, 40, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 12px 24px;
            border-radius: var(--radius-full);
            cursor: pointer;
            transition: var(--transition-normal);
            font-size: 0.9rem;
            font-weight: 500;
            margin: 1rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: sticky;
            top: 1rem;
            z-index: 10;
        }
        
        .load-more-messages:hover {
            background: rgba(108, 92, 231, 0.15);
            border-color: var(--accent-primary);
            color: var(--text-primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .load-more-messages:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .load-more-messages.loading {
            pointer-events: none;
        }
        
        .load-more-messages.loading i {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .no-messages {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-muted);
            font-style: italic;
            font-size: 1.1rem;
            flex-direction: column;
            gap: 1rem;
        }
        
        .no-messages::before {
            content: '💬';
            font-size: 3rem;
            opacity: 0.3;
        }
        
        .message {
            max-width: 75%;
            margin-bottom: 0.5rem;
            display: flex;
            flex-direction: column;
            animation: messageSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        @keyframes messageSlideIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .message.sent {
            align-self: flex-end;
            align-items: flex-end;
        }
        
        .message.received {
            align-self: flex-start;
            align-items: flex-start;
        }
        
        .message-header {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            font-family: 'JetBrains Mono', monospace;
        }
        
        .message-content {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-lg);
            line-height: 1.5;
            position: relative;
            word-break: break-word;
            box-shadow: var(--shadow-md);
            transition: var(--transition-normal);
            backdrop-filter: blur(10px);
        }
        
        .message-content:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }
        
        .message.sent .message-content {
            background: var(--accent-gradient);
            color: white;
            border-bottom-right-radius: 6px;
        }
        
        .message.received .message-content {
            background: rgba(26, 26, 40, 0.9);
            color: var(--text-primary);
            border-bottom-left-radius: 6px;
            border: 1px solid var(--border-color);
        }
        
        .message-time {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
            font-family: 'JetBrains Mono', monospace;
        }
        
        /* Enhanced Message Input */
        .message-input-container {
            padding: 1.5rem 2rem;
            background: rgba(18, 18, 26, 0.95);
            backdrop-filter: blur(20px);
            border-top: 1px solid var(--border-color);
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
            position: sticky;
            bottom: 0;
            z-index: 10;
        }
        
        .message-form {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            position: relative;
        }
        
        .message-input {
            flex: 1;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-full);
            border: 1px solid var(--border-color);
            background: rgba(255, 255, 255, 0.04);
            color: var(--text-primary);
            font-size: 1rem;
            font-family: inherit;
            outline: none;
            resize: none;
            max-height: 120px;
            min-height: 56px;
            transition: var(--transition-normal);
            line-height: 1.5;
        }
        
        .message-input::placeholder {
            color: var(--text-muted);
        }
        
        .message-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.15);
            transform: translateY(-2px);
        }
        
        .send-button {
            background: var(--accent-gradient);
            color: white;
            border: none;
            border-radius: var(--radius-full);
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition-normal);
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .send-button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: var(--radius-full);
            transition: var(--transition-normal);
            transform: translate(-50%, -50%);
        }
        
        .send-button:hover:not(:disabled) {
            transform: translateY(-3px) scale(1.05);
            box-shadow: var(--shadow-glow), var(--shadow-lg);
        }
        
        .send-button:hover:not(:disabled)::before {
            width: 100%;
            height: 100%;
        }
        
        .send-button:active:not(:disabled) {
            transform: translateY(-1px) scale(1.02);
        }
        
        .send-button:disabled {
            background: rgba(107, 107, 138, 0.3);
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .send-button i {
            font-size: 1.1rem;
            transition: var(--transition-normal);
        }
        
        .send-button:hover:not(:disabled) i {
            transform: translateX(2px);
        }
        
        .loading-conversations {
            padding: 2rem;
            text-align: center;
            color: var(--text-muted);
            font-style: italic;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }
        
        .loading-conversations::before {
            content: '';
            width: 32px;
            height: 32px;
            border: 3px solid var(--border-color);
            border-top: 3px solid var(--accent-primary);
            border-radius: var(--radius-full);
            animation: spin 1s linear infinite;
        }
        
        /* Mobile Enhancements */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 64px;
            height: 64px;
            background: var(--accent-gradient);
            border-radius: var(--radius-full);
            box-shadow: var(--shadow-glow), var(--shadow-lg);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: var(--transition-normal);
            border: none;
        }
        
        .mobile-menu-toggle:hover {
            transform: scale(1.1) translateY(-2px);
            box-shadow: 0 8px 32px var(--accent-glow), var(--shadow-lg);
        }
        
        .mobile-menu-toggle i {
            color: white;
            font-size: 1.5rem;
            transition: var(--transition-normal);
        }
        
        .mobile-menu-toggle:hover i {
            transform: rotate(15deg);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            :root {
                --sidebar-width: 100%;
                --header-height: 70px;
            }
            
            header {
                padding: 0 1rem;
            }
            
            .sidebar {
                position: absolute;
                top: 0;
                left: 0;
                bottom: 0;
                z-index: 50;
                transform: translateX(-100%);
                transition: transform var(--transition-normal);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .sidebar-header {
                padding: 1.5rem;
            }
            
            .chat-messages {
                padding: 1rem;
            }
            
            .message {
                max-width: 90%;
            }
            
            .message-input-container {
                padding: 1rem;
            }
            
            .mobile-menu-toggle {
                display: flex;
            }
            
            .view-profile-btn {
                padding: 8px 16px;
                font-size: 0.8rem;
            }
            
            .chat-header {
                padding: 1rem;
            }
        }
        
        /* Additional utility animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* Focus states for accessibility */
        .conversation-item:focus,
        .send-button:focus,
        .search-bar:focus,
        .message-input:focus,
        .view-profile-btn:focus,
        .load-more-messages:focus {
            outline: 2px solid var(--accent-primary);
            outline-offset: 2px;
        }
        
        /* Enhanced typing indicator */
        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            color: var(--text-muted);
            font-style: italic;
            font-size: 0.9rem;
        }
        
        .typing-dots {
            display: flex;
            gap: 4px;
        }
        
        .typing-dot {
            width: 6px;
            height: 6px;
            background: var(--accent-primary);
            border-radius: var(--radius-full);
            animation: typingBounce 1.4s ease-in-out infinite both;
        }
        
        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }
        
        @keyframes typingBounce {
            0%, 80%, 100% {
                transform: scale(0);
                opacity: 0.5;
            }
            40% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        /* Status indicators */
        .online-status {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 14px;
            height: 14px;
            background: var(--success);
            border: 2px solid var(--bg-secondary);
            border-radius: var(--radius-full);
            box-shadow: 0 0 10px rgba(0, 184, 148, 0.5);
        }
        
        .offline-status {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 14px;
            height: 14px;
            background: var(--text-muted);
            border: 2px solid var(--bg-secondary);
            border-radius: var(--radius-full);
        }
        
        /* Message status indicators */
        .message-status {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .message-status.delivered {
            color: var(--accent-secondary);
        }
        
        .message-status.read {
            color: var(--success);
        }
        
        /* Enhanced hover effects */
        .message-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, transparent, rgba(255, 255, 255, 0.05), transparent);
            border-radius: inherit;
            opacity: 0;
            transition: var(--transition-normal);
            pointer-events: none;
        }
        
        .message-content:hover::before {
            opacity: 1;
        }
        
        /* Smooth page load animation */
        .page-fade-in {
            animation: pageFadeIn 0.8s ease-out;
        }
        
        @keyframes pageFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Enhanced scrollbar for chat messages */
        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }
        
        .chat-messages::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--accent-gradient);
            border-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--accent-secondary), #fd79a8);
        }
        
        /* Loading skeleton for conversations */
        .conversation-skeleton {
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 0 0.5rem;
            border-radius: var(--radius-md);
        }
        
        .skeleton-avatar {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-full);
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.05) 25%, rgba(255, 255, 255, 0.1) 50%, rgba(255, 255, 255, 0.05) 75%);
            background-size: 200% 100%;
            animation: skeletonShimmer 2s ease-in-out infinite;
        }
        
        .skeleton-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .skeleton-name {
            height: 16px;
            width: 60%;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.05) 25%, rgba(255, 255, 255, 0.1) 50%, rgba(255, 255, 255, 0.05) 75%);
            background-size: 200% 100%;
            border-radius: 4px;
            animation: skeletonShimmer 2s ease-in-out infinite;
        }
        
        .skeleton-preview {
            height: 12px;
            width: 80%;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.05) 25%, rgba(255, 255, 255, 0.1) 50%, rgba(255, 255, 255, 0.05) 75%);
            background-size: 200% 100%;
            border-radius: 4px;
            animation: skeletonShimmer 2s ease-in-out infinite;
        }
        
        @keyframes skeletonShimmer {
            0% {
                background-position: -200% 0;
            }
            100% {
                background-position: 200% 0;
            }
        }
        
        /* Message pagination loading */
        .messages-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            color: var(--text-muted);
            font-size: 0.9rem;
            gap: 0.5rem;
        }
        
        .messages-loading::before {
            content: '';
            width: 16px;
            height: 16px;
            border: 2px solid var(--border-color);
            border-top: 2px solid var(--accent-primary);
            border-radius: var(--radius-full);
            animation: spin 1s linear infinite;
        }
        
        /* Elegant Profile Button */
.elegant-btn {
    background: rgba(108, 92, 231, 0.1);
    color: var(--accent-primary);
    border: 1px solid var(--accent-primary);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s ease;
}

.elegant-btn:hover {
    background: rgba(108, 92, 231, 0.2);
    transform: translateY(-1px);
}

.elegant-btn i {
    font-size: 0.9rem;
}

.chat-header-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
/* Add to your styles */
.chat-container {
    background: 
        radial-gradient(circle at 20% 30%, rgba(108, 92, 231, 0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(162, 155, 254, 0.05) 0%, transparent 50%),
        linear-gradient(to bottom, rgba(18, 18, 26, 0.95), rgba(10, 10, 15, 0.98));
    backdrop-filter: blur(5px);
}

.elegant-btn {
    padding: 6px 12px 6px 8px; /* Reduced right padding */
}
.elegant-btn span {
    margin-left: 5px;
}

.load-more-messages {
    background: rgba(255,255,255,0.05);
    border: none;
    color: var(--accent-primary);
    padding: 8px;
    margin: 10px auto;
    display: block;
    width: max-content;
    border-radius: 20px;
    font-size: 0.8rem;
    transition: all 0.2s ease;
}
.load-more-messages:hover {
    background: rgba(108, 92, 231, 0.1);
}

#messagesList {
    transition: opacity 0.3s ease;
}
/* Add this CSS to fix the scrolling issue */
.chat-messages {
    flex: 1;
    padding: 1rem 2rem;
    overflow-y: auto;
    background: linear-gradient(135deg, 
        rgba(10, 10, 15, 0.98) 0%, 
        rgba(18, 18, 26, 0.95) 100%
    );
    display: flex;
    flex-direction: column;
    min-height: 0;
    height: calc(100vh - var(--header-height) - 90px - 80px); /* Fixed height */
}

#messagesList {
    display: flex;
    flex-direction: column;
    flex: 1;
    gap: 0.5rem;
    overflow-y: auto;
    /* Remove justify-content: flex-end - this was causing the problem */
    min-height: 100%;
    padding: 1rem 0;
}

/* Ensure messages fill from top to bottom naturally */
.message {
    max-width: 75%;
    margin-bottom: 0.5rem;
    display: flex;
    flex-direction: column;
    animation: messageSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    flex-shrink: 0; /* Prevent messages from shrinking */
}

/* Add a spacer div to push messages to bottom on initial load */
.messages-spacer {
    flex: 1;
    min-height: 1px;
}

/* No messages state should be centered */
.no-messages {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--text-muted);
    font-style: italic;
    font-size: 1.1rem;
    flex-direction: column;
    gap: 1rem;
    flex: 1;
}

/* Loading messages indicator */
.messages-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    color: var(--text-muted);
    font-size: 0.9rem;
    gap: 0.5rem;
    flex-shrink: 0;
}

/* Ensure the chat container has proper height */
.chat-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: rgba(10, 10, 15, 0.98);
    backdrop-filter: blur(20px);
    position: relative;
    overflow: hidden;
    height: calc(100vh - var(--header-height));
    max-height: calc(100vh - var(--header-height));
}
     </style>
</head>
<body class="dark-theme page-fade-in">

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'recruiter'): ?>
    <?php include __DIR__ . '/Includes/Recruiter_header.php'; ?>
<?php else: ?>
    <?php include __DIR__ . '/Includes/Candidate_header.php'; ?>
<?php endif; ?>
    
<div class="main-wrapper">
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'recruiter'): ?>
        <?php include __DIR__ . '/Includes/Recruiter_sidebar.php'; ?>
    <?php else: ?>
        <?php include __DIR__ . '/Includes/Candidate_sidebar.php'; ?>
    <?php endif; ?>

    <div class="main-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-comments"></i> Messages</h3>
                <input type="text" class="search-bar" placeholder="🔍 Search conversations..." id="searchInput">
            </div>
            
            <div class="conversations-list" id="conversationsUl">
                <div class="loading-conversations">
                    <span>Loading conversations...</span>
                </div>
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="chat-container">
            <div class="chat-header" id="chatWith">
                <div class="no-chat-selected">
                    Select a conversation to start chatting
                </div>
            </div>
            
            <div class="chat-messages">
                <div id="messagesList">
                    <div class="no-messages">
                        <span>Select a conversation to view messages</span>
                    </div>
                </div>
            </div>
            
            <div class="message-input-container" id="messageInputContainer" style="display: none;">
                <form class="message-form" id="sendMessageForm">
                    <textarea class="message-input" id="messageInput" 
                           placeholder="Type your message here..." autocomplete="off" required maxlength="1000" 
                           rows="1"></textarea>
                    <button type="submit" class="send-button" title="Send message">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Mobile menu toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" title="Toggle conversations">
        <i class="fas fa-comments"></i>
    </button>
</div>

<script>
// Ensure page loads at the top and prevent auto-scroll to bottom
document.addEventListener('DOMContentLoaded', function() {
    window.scrollTo(0, 0);
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
    
    const messagesList = document.getElementById('messagesList');
    if (messagesList) {
        messagesList.scrollTop = 0;
    }
    
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (mobileMenuToggle && sidebar) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && 
                !sidebar.contains(e.target) && 
                !mobileMenuToggle.contains(e.target) && 
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });
    }
    
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const form = document.getElementById('sendMessageForm');
                if (form && this.value.trim()) {
                    form.dispatchEvent(new Event('submit'));
                }
            }
        });
    }
    
    if (messagesList) {
        messagesList.style.scrollBehavior = 'smooth';
    }
    
    const loadingElement = document.querySelector('.loading-conversations');
    if (loadingElement) {
        for (let i = 0; i < 5; i++) {
            const skeleton = document.createElement('div');
            skeleton.className = 'conversation-skeleton';
            skeleton.innerHTML = `
                <div class="skeleton-avatar"></div>
                <div class="skeleton-info">
                    <div class="skeleton-name"></div>
                    <div class="skeleton-preview"></div>
                </div>
            `;
            loadingElement.appendChild(skeleton);
        }
    }
});

// Enhanced messenger data with pagination support
window.messengerData = {
    currentUserId: <?= $user_id ?>,
    currentUserPhoto: '<?= $current_user_photo ?>',
    forcedChatData: <?= isset($_SESSION['force_open_chat']) ? 
        json_encode($_SESSION['force_open_chat']) : 'null' ?>,
    messagesPerPage: 15,
    currentPage: 1,
    hasMoreMessages: true,
    isLoadingMessages: false
};
    
<?php 
if (isset($_SESSION['force_open_chat'])) {
    unset($_SESSION['force_open_chat']);
}
?>
</script>
<script src="../Public/Assets/JS/messenger.js"></script>
</body>
</html>
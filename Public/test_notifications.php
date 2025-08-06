<?php
// Create this file as test_notifications.php in your project root
// Run this to debug the notification issue

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../App/Helpers/notification_functions.php';

echo "<h1>Notification Debug Script</h1>";

// 1. Test database connection
echo "<h2>1. Testing Database Connection</h2>";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Database connection working<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    exit;
}

// 2. Check users table structure
echo "<h2>2. Users Table Structure</h2>";
try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        foreach ($column as $value) {
            echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Error checking users table: " . $e->getMessage() . "<br>";
}

// 3. Check all users and their roles
echo "<h2>3. All Users in Database</h2>";
try {
    $stmt = $pdo->query("SELECT id, role, name, email FROM users ORDER BY role, id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "❌ No users found in database!<br>";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Role</th><th>Name</th><th>Email</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . htmlspecialchars($user['name'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($user['email'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<br>Total users: " . count($users) . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Error fetching users: " . $e->getMessage() . "<br>";
}

// 4. Check specifically for candidates
echo "<h2>4. Candidate Users</h2>";
try {
    // Try different possible role values
    $possibleRoles = ['candidate', 'candidat', 'candidates', 'Candidate'];
    
    foreach ($possibleRoles as $role) {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = ?");
        $stmt->execute([$role]);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Role '<strong>{$role}</strong>': " . count($candidates) . " users found<br>";
        if (!empty($candidates)) {
            foreach ($candidates as $candidate) {
                echo "&nbsp;&nbsp;- ID: {$candidate['id']}, Name: " . htmlspecialchars($candidate['name'] ?? 'NULL') . "<br>";
            }
        }
    }
} catch (Exception $e) {
    echo "❌ Error checking candidates: " . $e->getMessage() . "<br>";
}

// 5. Check notifications table structure
echo "<h2>5. Notifications Table Structure</h2>";
try {
    $stmt = $pdo->query("DESCRIBE notifications");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        foreach ($column as $value) {
            echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Notifications table doesn't exist or error: " . $e->getMessage() . "<br>";
    echo "You need to create the notifications table!<br>";
}

// 6. Check existing notifications
echo "<h2>6. Existing Notifications</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notifications)) {
        echo "No notifications found in database<br>";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Message</th><th>Type</th><th>Read</th><th>Created</th><th>Related ID</th></tr>";
        foreach ($notifications as $notif) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($notif['id']) . "</td>";
            echo "<td>" . htmlspecialchars($notif['user_id']) . "</td>";
            echo "<td>" . htmlspecialchars($notif['message']) . "</td>";
            echo "<td>" . htmlspecialchars($notif['notification_type'] ?? 'NULL') . "</td>";
            echo "<td>" . ($notif['is_read'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . htmlspecialchars($notif['created_at']) . "</td>";
            echo "<td>" . htmlspecialchars($notif['related_id'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Error checking notifications: " . $e->getMessage() . "<br>";
}

// 7. Test adding a notification manually
echo "<h2>7. Testing Manual Notification</h2>";
try {
    // Find the first candidate
    $stmt = $pdo->query("SELECT id, name FROM users WHERE role IN ('candidate', 'candidat') LIMIT 1");
    $testCandidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testCandidate) {
        echo "Testing with candidate ID: {$testCandidate['id']} ({$testCandidate['name']})<br>";
        
        $testMessage = "Test notification - " . date('Y-m-d H:i:s');
        $result = addNotification($pdo, $testCandidate['id'], $testMessage, 'test', null);
        
        if ($result) {
            echo "✅ Test notification added successfully!<br>";
            
            // Verify it was inserted
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$testCandidate['id']]);
            $lastNotif = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lastNotif) {
                echo "✅ Notification confirmed in database: " . htmlspecialchars($lastNotif['message']) . "<br>";
            } else {
                echo "❌ Notification not found in database after insertion<br>";
            }
        } else {
            echo "❌ Failed to add test notification<br>";
        }
    } else {
        echo "❌ No candidates found to test with<br>";
    }
} catch (Exception $e) {
    echo "❌ Error testing notification: " . $e->getMessage() . "<br>";
}

// 8. Check recent job offers
echo "<h2>8. Recent Job Offers</h2>";
try {
    $stmt = $pdo->query("SELECT id, title, recruiter_id, created_at FROM offers ORDER BY created_at DESC LIMIT 5");
    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($offers)) {
        echo "No job offers found<br>";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Title</th><th>Recruiter ID</th><th>Created</th></tr>";
        foreach ($offers as $offer) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($offer['id']) . "</td>";
            echo "<td>" . htmlspecialchars($offer['title']) . "</td>";
            echo "<td>" . htmlspecialchars($offer['recruiter_id']) . "</td>";
            echo "<td>" . htmlspecialchars($offer['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Error checking offers: " . $e->getMessage() . "<br>";
}

echo "<h2>Debug Complete</h2>";
echo "Please check the results above and let me know what you see!";
?>
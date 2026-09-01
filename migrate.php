<?php
// migrate.php — Run ONCE, then delete
require_once 'config/db.php';

$file = fopen('users.csv', 'r');
$count = 0;
while (($row = fgetcsv($file)) !== false) {
    // Format: name, login_id, hashed_password, referral, date
    if (count($row) >= 3) {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (fullname, login_id, password, referral_code, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$row[0], $row[1], $row[2], $row[3] ?? '', $row[4] ?? date('Y-m-d H:i:s')]);
            $count++;
        } catch (PDOException $e) { /* skip dupes */ }
    }
}
fclose($file);
echo "✅ Migrated $count users from CSV to MySQL.<br>";
echo "⚠️ IMPORTANT: Passwords were stored as plain bcrypt? Re-hash them or ask users to reset.";
echo "<br><br>👉 Delete this file now for security!";
?>

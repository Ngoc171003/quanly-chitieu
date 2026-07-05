<?php
require_once 'app/config.php';
require_once 'app/Database.php';

$conn = $db->getConnection();

// Step 1: Add savings_goal_id column to transactions if not exists
$col_check = $conn->query("SHOW COLUMNS FROM transactions LIKE 'savings_goal_id'");
if ($col_check && $col_check->num_rows == 0) {
    $sql = "ALTER TABLE transactions ADD COLUMN savings_goal_id INT NULL DEFAULT NULL AFTER wallet_id,
            ADD CONSTRAINT fk_trans_savings_goal FOREIGN KEY (savings_goal_id) REFERENCES savings_goals(id) ON DELETE SET NULL;";
    if ($conn->query($sql)) {
        echo "OK: Added savings_goal_id column to transactions" . PHP_EOL;
    } else {
        echo "ERROR adding column: " . $conn->error . PHP_EOL;
        exit;
    }
} else {
    echo "OK: savings_goal_id column already exists" . PHP_EOL;
}

// Step 2: Try to link existing savings contribution transactions to goals
// Match by note pattern "Đóng góp vào mục tiêu: <name>"
$goals_res = $conn->query("SELECT id, user_id, name FROM savings_goals");
$linked = 0;
while ($goal = $goals_res->fetch_assoc()) {
    $note_pattern = "Đóng góp vào mục tiêu: " . $goal['name'];
    $stmt = $conn->prepare("UPDATE transactions SET savings_goal_id = ? WHERE user_id = ? AND note = ? AND savings_goal_id IS NULL");
    $stmt->bind_param("iis", $goal['id'], $goal['user_id'], $note_pattern);
    $stmt->execute();
    $linked += $stmt->affected_rows;
    $stmt->close();
}
echo "OK: Linked $linked existing transactions to their savings goals" . PHP_EOL;

// Step 3: Recalculate current_amount for all goals based on linked transactions
$recalc = $conn->query("SELECT id, user_id FROM savings_goals");
$updated = 0;
while ($goal = $recalc->fetch_assoc()) {
    $stmt2 = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE savings_goal_id = ? AND user_id = ?");
    $stmt2->bind_param("ii", $goal['id'], $goal['user_id']);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $row2 = $result2->fetch_assoc();
    $total = floatval($row2['total']);
    $stmt2->close();

    // Get target_amount to determine status
    $g = $conn->query("SELECT target_amount FROM savings_goals WHERE id = {$goal['id']}")->fetch_assoc();
    $status = ($total >= floatval($g['target_amount'])) ? 'completed' : 'active';

    $stmt3 = $conn->prepare("UPDATE savings_goals SET current_amount = ?, status = ? WHERE id = ?");
    $stmt3->bind_param("dsi", $total, $status, $goal['id']);
    $stmt3->execute();
    $stmt3->close();
    $updated++;
}
echo "OK: Recalculated current_amount for $updated savings goals" . PHP_EOL;
echo PHP_EOL . "Migration complete!" . PHP_EOL;

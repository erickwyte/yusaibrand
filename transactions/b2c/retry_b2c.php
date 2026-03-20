<?php
require_once 'db.php';
require_once 'mpesa_b2c.php'; // your B2C send function

// Pick pending retries with less than 5 attempts
$stmt = $pdo->query("SELECT * FROM b2c_transactions 
                     WHERE status = 'PENDING_RETRY' AND retries < 5");

while ($tx = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Check retry schedule
    $minutesSince = (time() - strtotime($tx['last_attempt'])) / 60;
    $backoff = [1 => 5, 2 => 15, 3 => 60, 4 => 120]; // retry schedule
    $nextWait = $backoff[$tx['retries']] ?? 240;

    if ($minutesSince >= $nextWait) {
        // Retry payout
       // $result = send_b2c($tx['phone'], $tx['amount']); // returns new ConversationID

        if ($result['success']) {
            $pdo->prepare("UPDATE b2c_transactions 
                           SET conversation_id=?, status='PROCESSING', last_attempt=NOW() 
                           WHERE id=?")
                ->execute([$result['conversation_id'], $tx['id']]);
        } else {
            // if API call fails at network level, leave it as pending_retry
            $pdo->prepare("UPDATE b2c_transactions 
                           SET last_attempt=NOW() WHERE id=?")
                ->execute([$tx['id']]);
        }
    }
}

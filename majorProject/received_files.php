<?php
session_start();
include "db_connection.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

$app_db = openAppDB();
$receiver_anon_id = $_SESSION['anon_id'];

/* ---------- FETCH PENDING ENCRYPTED FILES ---------- */
$stmt = $app_db->prepare(
    "SELECT ef.id, tr.sender_alias_name, ef.created_at
     FROM encrypted_files ef
     JOIN transfer_requests tr
       ON ef.transfer_request_id = tr.id
     WHERE ef.receiver_anon_id = ? AND ef.status = 'pending'"
);
$stmt->bind_param("s", $receiver_anon_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Received Files</title>
    <link rel="stylesheet" href="user_dashboard.css">
</head>
<body>

<div class="container">
    <h3>Received Encrypted Files</h3>

    <?php if ($result->num_rows === 0) { ?>
        <p>No encrypted files available.</p>
    <?php } else { ?>

    <table>
        <tr>
            <th>Sender</th>
            <th>Received At</th>
            <th>Action</th>
        </tr>

        <?php while ($row = $result->fetch_assoc()) { ?>
        <tr>
            <td><?php echo htmlspecialchars($row['sender_alias_name']); ?></td>
            <td><?php echo $row['created_at']; ?></td>
            <td>
                <form action="received_files_list.php" method="POST">
                    <input type="hidden" name="encrypted_file_id" value="<?php echo $row['id']; ?>">
                    <button type="submit">Decrypt & Store</button>
                </form>
            </td>
        </tr>
        <?php } ?>

    </table>

    <?php } ?>

    <br>
    <a href="user_dashboard.php">⬅ Back to Dashboard</a>
</div>

</body>
</html>

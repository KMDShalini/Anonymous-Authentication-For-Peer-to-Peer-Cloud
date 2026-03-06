<?php
session_start();
include "db_connection.php";

/* ---------- SESSION CHECK ---------- */
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

$app_db = openAppDB();

$sender_username = $_SESSION['username'];
$sender_anon_id  = $_SESSION['anon_id'];
$sender_cloud    = $_SESSION['cloud'];

/* ---------- FETCH APPROVED TRANSFER REQUESTS ---------- */
$stmt = $app_db->prepare(
    "SELECT id, receiver_username
     FROM transfer_requests
     WHERE sender_username = ? AND status = 'approved'"
);
$stmt->bind_param("s", $sender_username);
$stmt->execute();
$requests = $stmt->get_result();
$stmt->close();

/* ---------- SELECT CLOUD DB ---------- */
$cloud_db = ($sender_cloud === "A") ? openCloudADB() : openCloudBDB();

/* ---------- FETCH FILES FROM CLOUD STORAGE ---------- */
$stmt2 = $cloud_db->prepare(
    "SELECT id, file_name FROM cloud_files WHERE owner_anon_id = ?"
);
$stmt2->bind_param("s", $sender_anon_id);
$stmt2->execute();
$files = $stmt2->get_result();
$stmt2->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Approved Transfers</title>
    <link rel="stylesheet" href="user_dashboard.css">
</head>
<body>

<div class="container mt-5 pt-5">
    <div class="card">
        <h3>Approved Transfers</h3>

        <?php if ($requests->num_rows === 0) { ?>
            <p>No approved transfers.</p>
        <?php } else { ?>

        <table>
            <tr>
                <th>Receiver</th>
                <th>Select File</th>
                <th>Action</th>
            </tr>

            <?php while ($req = $requests->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($req['receiver_username']); ?></td>

                    <td>
                        <form action="send_file.php" method="POST">
                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">

                            <select name="file_id" required>
                                <option value="">-- Select File --</option>
                                <?php
                                mysqli_data_seek($files, 0);
                                while ($file = $files->fetch_assoc()) {
                                    echo "<option value='{$file['id']}'>{$file['file_name']}</option>";
                                }
                                ?>
                            </select>
                    </td>

                    <td>
                        <button type="submit" onclick="this.disabled=true; this.form.submit();">
                            Encrypt & Migrate
                        </button>
                        </form>
                    </td>
                </tr>
            <?php } ?>

        </table>

        <?php } ?>
    </div>
</div>

</body>
</html>

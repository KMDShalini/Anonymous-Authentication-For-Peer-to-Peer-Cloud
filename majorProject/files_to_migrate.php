<?php
session_start();
include "config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

$query = "SELECT * FROM uploaded_files WHERE user_id = '$user_id'";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Files to Migrate</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>

<h2>Files to Migrate</h2>

<table border="1" cellpadding="10" cellspacing="0">
    <tr>
        <th>File Name</th>
        <th>Uploaded At</th>
        <th>View</th>
        <th>Action</th>
    </tr>

    <?php if (mysqli_num_rows($result) > 0) { ?>
        <?php while ($row = mysqli_fetch_assoc($result)) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['file_name']); ?></td>
                <td><?php echo $row['uploaded_at']; ?></td>

                <!-- ✅ View file -->
                <td>
                    <a 
                        href="uploads/<?php echo urlencode($row['file_path']); ?>" 
                        target="_blank"
                    >
                        View
                    </a>
                </td>

                <!-- Migration button (future) -->
                <td>
                    <button disabled>Migrate</button>
                </td>
            </tr>
        <?php } ?>
    <?php } else { ?>
        <tr>
            <td colspan="4">No files uploaded</td>
        </tr>
    <?php } ?>

</table>

</body>
</html>

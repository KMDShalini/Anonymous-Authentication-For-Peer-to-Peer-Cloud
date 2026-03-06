<?php

function openCloudADB() {
    return new mysqli("3.24.120.120", "clouduser", "cloudpass", "cloudA_db");
}

function openCloudBDB() {
    return new mysqli("51.20.36.131", "clouduser", "cloudpass", "cloudB_db");
}

function openAppDB() {
    return new mysqli("localhost", "appuser", "StrongPassword123", "app_db");
}


?>

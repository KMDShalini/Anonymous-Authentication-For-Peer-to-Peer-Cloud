<?php
$config = [
    "private_key_type" => OPENSSL_KEYTYPE_EC,
    "curve_name" => "prime256v1"
];

$key = openssl_pkey_new($config);

if ($key === false) {
    echo openssl_error_string();
} else {
    echo "ECC works!";
}

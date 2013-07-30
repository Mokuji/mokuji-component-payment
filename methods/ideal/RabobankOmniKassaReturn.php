<?php
session_start();
$_SESSION['mokuji_payment_ideal_rabobank_omnikassa_callback'] = base64_encode(json_encode($_POST));
header('Location: rabobank-omnikassa-return.php');
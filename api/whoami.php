<?php
require __DIR__ . '/_bootstrap.php';
$user = current_user();
json_response(['user' => $user]);

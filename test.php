<?php
// /society_app/test_push.php
require_once 'vendor/autoload.php';

if (class_exists('Minishlink\WebPush\WebPush')) {
    echo "Success: minishlink/web-push is installed and loaded properly!";
} else {
    echo "Error: The library classes were not found.";
}
?>
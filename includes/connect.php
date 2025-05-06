<?php
$connection = new mysqli('localhost', 'root', '', 'chownowdb');

if ($connection->connect_error) {
    die('Connection failed: ' . $connection->connect_error);
}
?>
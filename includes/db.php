<?php
// Database connection configuration
$servername = "localhost";
$username = "root";   
$password = "";  // Put your password here if needed, default is no password
$dbname = "lms";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Can also add a port number to the end if using something other than 3306 i.e. ($servername, $username, $password, $dbname, 3307)

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
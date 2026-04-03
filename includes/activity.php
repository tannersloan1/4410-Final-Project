<?php
// Functions to log activity in the "LOGS" table

function logActivity($conn, $id, $role, $action_type, $action_description = NULL, $table_affected = NULL) {
    $ip_address = $_SERVER["REMOTE_ADDR"] ?? "Unknown";
    
    $sql = $conn->prepare("INSERT INTO LOGS (id, `role`, action_type, action_description, table_affected, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?)");

    $sql->bind_param("isssss",
    $id, $role, $action_type, $action_description, $table_affected, $ip_address);
    
    return $sql->execute();
}
?>

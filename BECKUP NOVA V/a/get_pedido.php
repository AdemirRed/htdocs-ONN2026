<?php
session_start();
require_once "pedidos.php";

if (!isset($_SESSION["usuario"]) || !isset($_GET["id"])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Dados inválidos"]);
    exit();
}

$id = intval($_GET["id"]);
$pedidosManager = new PedidosManager();

try {
    $stmt = $pedidosManager->conn->prepare("SELECT * FROM pedidos WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $pedido = $result->fetch_assoc();
        echo json_encode(["success" => true, "pedido" => $pedido]);
    } else {
        echo json_encode(["success" => false, "error" => "Pedido não encontrado"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "Erro interno"]);
}
?>
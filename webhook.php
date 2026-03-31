<?php
header('Content-Type: application/json; charset=utf-8');

// Arquivo onde vamos armazenar TUDO
$arquivoMensagens = "chat_data.json";

// Se o arquivo ainda não existe, cria vazio
if (!file_exists($arquivoMensagens)) {
    file_put_contents($arquivoMensagens, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = file_get_contents("php://input");
    $json = json_decode($data, true);

    // Adiciona ao histórico
    $historico = json_decode(file_get_contents($arquivoMensagens), true);
    $historico[] = [
        'data_recebimento' => date('Y-m-d H:i:s'),
        'conteudo' => $json
    ];

    file_put_contents($arquivoMensagens, json_encode($historico, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Exibe o JSON recebido imediatamente
    echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} else {

    // Exibe todas as mensagens já recebidas
    $historico = json_decode(file_get_contents($arquivoMensagens), true);
    echo json_encode($historico, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

<?php
/**
 * Verificador de Status da Sessão WhatsApp
 * Retorna o status atual da sessão para exibir avisos na interface
 */

header('Content-Type: application/json');

$whatsapp_api_url = 'http://192.168.0.201:200';
$whatsapp_api_key = 'redblack';
$session_id = 'ademir';

$url = "{$whatsapp_api_url}/session/status/{$session_id}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: application/json',
    'x-api-key: ' . $whatsapp_api_key
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$connected = false;
$state = 'UNKNOWN';

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $state = $data['state'] ?? 'UNKNOWN';
    $connected = ($state === 'CONNECTED');
}

echo json_encode([
    'connected' => $connected,
    'state' => $state,
    'session_id' => $session_id
]);

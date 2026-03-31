<?php
/**
 * Script de Teste - API WhatsApp
 * 
 * Este script testa a conectividade e funcionalidade da API WhatsApp
 * Use: php test_whatsapp_api.php
 * Ou acesse via navegador: http://192.168.0.201/test_whatsapp_api.php
 */

// Configurações
$whatsapp_api_url = 'http://192.168.0.201:200';
$whatsapp_api_key = 'redblack';
$session_id = 'ademir';
$numero_teste = '555197756708'; // Altere para seu número de teste

// Cores para terminal
$colors = [
    'reset' => "\033[0m",
    'red' => "\033[31m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'magenta' => "\033[35m",
    'cyan' => "\033[36m",
];

function printHeader($text) {
    global $colors;
    $line = str_repeat('=', strlen($text) + 4);
    echo "\n{$colors['cyan']}{$line}\n";
    echo "  {$text}\n";
    echo "{$line}{$colors['reset']}\n\n";
}

function printTest($name) {
    global $colors;
    echo "{$colors['blue']}[TESTE]{$colors['reset']} {$name}... ";
}

function printSuccess($message = "OK") {
    global $colors;
    echo "{$colors['green']}✓ {$message}{$colors['reset']}\n";
}

function printError($message) {
    global $colors;
    echo "{$colors['red']}✗ ERRO: {$message}{$colors['reset']}\n";
}

function printInfo($message) {
    global $colors;
    echo "{$colors['yellow']}ℹ {$message}{$colors['reset']}\n";
}

function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error,
        'data' => json_decode($response, true)
    ];
}

// ===========================================
// INÍCIO DOS TESTES
// ===========================================

printHeader("TESTE DE INTEGRAÇÃO - API WHATSAPP");

// Teste 1: Verificar se a API está online
printTest("Verificando conectividade da API");
$result = makeRequest("{$whatsapp_api_url}/session/status/{$session_id}", 'GET', null, [
    'accept: application/json',
    'x-api-key: ' . $whatsapp_api_key
]);

if ($result['success'] || $result['http_code'] === 404) {
    printSuccess("API acessível");
    printInfo("URL: {$whatsapp_api_url}");
} else {
    printError("API não acessível - HTTP {$result['http_code']}");
    printInfo("Erro: " . ($result['error'] ?: 'Sem resposta'));
    exit(1);
}

// Teste 2: Verificar status da sessão
printTest("Verificando status da sessão '{$session_id}'");
if ($result['success']) {
    $status = $result['data'];
    $state = $status['state'] ?? 'UNKNOWN';
    
    if ($state === 'CONNECTED') {
        printSuccess("Sessão CONECTADA");
        printInfo("Estado: {$state}");
    } else {
        printError("Sessão não conectada");
        printInfo("Estado: {$state}");
        printInfo("Execute: config_whatsapp_retalhos.php para conectar");
    }
} else {
    printError("Sessão não encontrada");
    printInfo("A sessão será criada no primeiro uso");
}

// Teste 3: Tentar iniciar sessão (se não conectada)
if (!isset($state) || $state !== 'CONNECTED') {
    printTest("Tentando iniciar sessão");
    $result = makeRequest("{$whatsapp_api_url}/session/start/{$session_id}", 'GET', null, [
        'accept: application/json',
        'x-api-key: ' . $whatsapp_api_key
    ]);
    
    if ($result['success']) {
        printSuccess("Sessão iniciada");
        printInfo("Aguarde alguns segundos e acesse config_whatsapp_retalhos.php para escanear o QR Code");
        sleep(2);
    } else {
        printError("Falha ao iniciar sessão");
    }
}

// Teste 4: Verificar endpoint de envio de mensagem
printTest("Verificando endpoint de envio");
$testPayload = [
    'chatId' => $numero_teste . '@c.us',
    'contentType' => 'string',
    'content' => '🧪 Mensagem de teste - Sistema de Retalhos'
];

$result = makeRequest(
    "{$whatsapp_api_url}/message/sendText/{$session_id}",
    'POST',
    $testPayload,
    [
        'Content-Type: application/json',
        'accept: application/json',
        'x-api-key: ' . $whatsapp_api_key
    ]
);

if ($result['http_code'] === 401 || $result['http_code'] === 404) {
    printInfo("Endpoint encontrado (sessão desconectada é esperado)");
} elseif ($result['success']) {
    printSuccess("Mensagem enviada!");
    printInfo("Verifique o WhatsApp: {$numero_teste}");
} else {
    printError("Falha no envio - HTTP {$result['http_code']}");
    printInfo("Response: " . substr($result['response'], 0, 200));
}

// Teste 5: Testar arquivo enviar_whatsapp_retalho.php
printTest("Verificando arquivo enviar_whatsapp_retalho.php");
if (file_exists(__DIR__ . '/enviar_whatsapp_retalho.php')) {
    printSuccess("Arquivo encontrado");
} else {
    printError("Arquivo não encontrado");
    printInfo("Certifique-se de que o arquivo está em: " . __DIR__);
}

// Teste 6: Testar arquivo config_whatsapp_retalhos.php
printTest("Verificando arquivo config_whatsapp_retalhos.php");
if (file_exists(__DIR__ . '/config_whatsapp_retalhos.php')) {
    printSuccess("Arquivo encontrado");
    printInfo("Acesse: http://192.168.0.201/config_whatsapp_retalhos.php");
} else {
    printError("Arquivo não encontrado");
}

// Teste 7: Verificar extensão cURL
printTest("Verificando extensão cURL");
if (function_exists('curl_version')) {
    $curlVersion = curl_version();
    printSuccess("cURL habilitado");
    printInfo("Versão: {$curlVersion['version']}");
} else {
    printError("cURL não está habilitado");
    printInfo("Habilite a extensão php_curl.dll no php.ini");
}

// Teste 8: Verificar permissões de escrita
printTest("Verificando permissões de escrita");
$testFile = __DIR__ . '/.test_write_permission';
if (@file_put_contents($testFile, 'test')) {
    printSuccess("Permissões OK");
    @unlink($testFile);
} else {
    printError("Sem permissão de escrita");
    printInfo("Verifique as permissões do diretório");
}

// ===========================================
// RESUMO
// ===========================================

printHeader("RESUMO DOS TESTES");

echo "📍 Configurações:\n";
echo "   • API URL: {$whatsapp_api_url}\n";
echo "   • Session ID: {$session_id}\n";
echo "   • API Key: " . str_repeat('*', strlen($whatsapp_api_key) - 4) . substr($whatsapp_api_key, -4) . "\n";
echo "   • Número Destino: {$numero_teste}\n\n";

echo "📝 Próximos Passos:\n";
echo "   1. Se a sessão não está conectada, acesse:\n";
echo "      → http://192.168.0.201/config_whatsapp_retalhos.php\n\n";
echo "   2. Escaneie o QR Code com o WhatsApp\n\n";
echo "   3. Teste o envio em:\n";
echo "      → http://192.168.0.201/retalhos_filtro.php\n\n";

echo "🔧 Comandos úteis:\n";
echo "   • Verificar logs PHP: tail -f /xampp/apache/logs/error.log\n";
echo "   • Verificar status: curl -H 'x-api-key: {$whatsapp_api_key}' {$whatsapp_api_url}/session/status/{$session_id}\n\n";

echo "{$colors['green']}Testes concluídos!{$colors['reset']}\n\n";

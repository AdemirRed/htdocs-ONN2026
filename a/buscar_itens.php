<?php
/**
 * Buscar materiais do Corte Certo por código ou nome para autocomplete
 * Base de dados: CC_DATA_BASE/MAT/M{codigo}.INI
 * Seção [DESC] -> CAMPO1 = nome do material
 */
header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isset($_SESSION['usuario']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$basePath = getenv('CC_DATA_BASE_PATH') ?: 'C:\\CC_DATA_BASE';
$matDir = rtrim($basePath, "\\") . '\\MAT';

if (!is_dir($matDir)) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$termo = trim($_GET['q'] ?? '');

if (strlen($termo) < 1) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$termoUpper = mb_strtoupper($termo, 'UTF-8');
$results = [];

$files = glob($matDir . '\\M*.INI');
if ($files === false) {
    $files = [];
}

foreach ($files as $filePath) {
    $code = strtoupper(basename($filePath, '.INI'));
    $code = preg_replace('/^M/i', '', $code);
    if ($code === '') {
        continue;
    }

    // Buscar nome do material na seção [DESC]
    $nome = parseIniNome($filePath);
    if ($nome === '') {
        $nome = basename($filePath, '.INI');
    }

    // Verificar se o código ou nome correspondem ao termo buscado
    $nomeUpper = mb_strtoupper($nome, 'UTF-8');
    $codeMatch = (strpos($code, $termoUpper) !== false);
    $nomeMatch = (strpos($nomeUpper, $termoUpper) !== false);

    if ($codeMatch || $nomeMatch) {
        $results[] = [
            'codigo' => $code,
            'nome' => $nome,
            '_codeMatch' => $codeMatch,
            '_nomeMatch' => $nomeMatch
        ];
    }
}

$isNumeric = ctype_digit($termo);

// Ordenar: código match primeiro quando termo é numérico
usort($results, function($a, $b) use ($termoUpper, $isNumeric) {
    // Se o termo é numérico, priorizar matches no código sobre matches só no nome
    if ($isNumeric) {
        $aCM = $a['_codeMatch'] ? 0 : 1;
        $bCM = $b['_codeMatch'] ? 0 : 1;
        if ($aCM !== $bCM) return $aCM - $bCM;
    }

    // Código exato primeiro
    $aExact = ($a['codigo'] === $termoUpper) ? 0 : 1;
    $bExact = ($b['codigo'] === $termoUpper) ? 0 : 1;
    if ($aExact !== $bExact) return $aExact - $bExact;

    // Código começa com termo
    $aStart = (strpos($a['codigo'], $termoUpper) === 0) ? 0 : 1;
    $bStart = (strpos($b['codigo'], $termoUpper) === 0) ? 0 : 1;
    if ($aStart !== $bStart) return $aStart - $bStart;

    return strcmp($a['nome'], $b['nome']);
});

// Limitar a 20 e remover campos internos
$results = array_slice($results, 0, 20);
$results = array_map(function($r) {
    return ['codigo' => $r['codigo'], 'nome' => $r['nome']];
}, $results);

echo json_encode($results, JSON_UNESCAPED_UNICODE);

/**
 * Parseia o arquivo INI e retorna o nome do material
 */
function parseIniNome(string $filePath): string
{
    $lines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return '';
    }

    $inDesc = false;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === ';' || $line[0] === '#') {
            continue;
        }

        if (preg_match('/^\s*\[(.+?)\]\s*$/u', $line, $matches)) {
            $section = strtoupper(trim($matches[1]));
            $inDesc = ($section === 'DESC');
            if (!$inDesc && $inDesc) {
                // Já passou da seção DESC sem encontrar
                break;
            }
            continue;
        }

        if ($inDesc) {
            $pair = preg_split('/\s*=\s*/', $line, 2);
            if (count($pair) < 2) {
                continue;
            }

            $key = strtoupper(trim($pair[0]));
            $value = trim($pair[1]);
            $value = trim($value, '"');

            // CAMPO1 é a chave principal para o nome
            if (in_array($key, ['CAMPO1', 'DESC', 'NOME', 'NOME_MATERIAL', 'DESCRICAO'])) {
                if ($value !== '') {
                    return $value;
                }
            }
        }
    }

    return '';
}

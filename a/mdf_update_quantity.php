<?php
header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['codigo']) || !isset($_POST['quantidade'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros inválidos'], JSON_UNESCAPED_UNICODE);
    exit;
}

$codigo = trim($_POST['codigo']);
$novaQtd = (int)$_POST['quantidade'];

if ($novaQtd < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Quantidade deve ser positiva'], JSON_UNESCAPED_UNICODE);
    exit;
}

$basePath = getenv('CC_DATA_BASE_PATH') ?: 'C:\\CC_DATA_BASE';
$chpDir = rtrim($basePath, "\\") . '\\CHP';

if (!is_dir($chpDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'Diretório CHP não encontrado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$candidates = buildChpCandidates($codigo);
$chpFile = null;

foreach ($candidates as $fileName) {
    $path = $chpDir . DIRECTORY_SEPARATOR . $fileName;
    if (is_file($path)) {
        $chpFile = $path;
        break;
    }
}

if ($chpFile === null) {
    $glob = glob($chpDir . DIRECTORY_SEPARATOR . 'CHP*' . $codigo . '*.TAB');
    if ($glob !== false && count($glob) > 0) {
        $chpFile = $glob[0];
    }
}

if ($chpFile === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Arquivo CHP não encontrado para o código ' . $codigo], JSON_UNESCAPED_UNICODE);
    exit;
}

$updated = updateChpQuantities($chpFile, $novaQtd);

if ($updated) {
    echo json_encode(['success' => 'Quantidade atualizada com sucesso'], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar arquivo CHP'], JSON_UNESCAPED_UNICODE);
}

function buildChpCandidates(string $code): array
{
    $padded4 = str_pad($code, 4, '0', STR_PAD_LEFT);
    $padded5 = str_pad($code, 5, '0', STR_PAD_LEFT);
    $padded6 = str_pad($code, 6, '0', STR_PAD_LEFT);

    return [
        "CHP00{$code}.TAB",
        "CHP0{$code}.TAB",
        "CHP{$code}.TAB",
        "CHP{$padded4}.TAB",
        "CHP{$padded5}.TAB",
        "CHP{$padded6}.TAB",
    ];
}

function updateChpQuantities(string $filePath, int $novaQtd): bool
{
    $lines = @file($filePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return false;
    }

    $updated = false;

    foreach ($lines as $key => $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === ';' || $line[0] === '#') {
            continue;
        }

        $parts = preg_split('/[\s;|]+/', $line);
        if (count($parts) < 5) {
            continue;
        }

        $width = parseNumber($parts[3]);
        $height = parseNumber($parts[4]);

        if ($width === null || $height === null) {
            continue;
        }

        if (isBaseSheetDimension($width, $height)) {
            $parts[2] = (string)$novaQtd;
            $lines[$key] = implode("\t", $parts);
            $updated = true;
        }
    }

    if ($updated) {
        $content = implode("\n", $lines) . "\n";
        if (@file_put_contents($filePath, $content) !== false) {
            return true;
        }
    }

    return $updated;
}

function parseNumber($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $value = str_replace([' ', 'mm', 'MM'], '', $value);
    $value = preg_replace('/[^0-9,.-]/', '', $value);

    if ($value === '') {
        return null;
    }

    if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } else {
        $value = str_replace(',', '.', $value);
    }

    if (!is_numeric($value)) {
        return null;
    }

    return floatval($value);
}

function isBaseSheetDimension($width, $height): bool
{
    return (
        ($width >= 1830 && $width <= 1850 && $height >= 2730 && $height <= 2750) ||
        ($width >= 2730 && $width <= 2750 && $height >= 1830 && $height <= 1850)
    );
}

<?php
header('Content-Type: application/json; charset=utf-8');

$basePath = getenv('CC_DATA_BASE_PATH') ?: 'C:\\CC_DATA_BASE';
$matDir = rtrim($basePath, "\\") . '\\MAT';
$chpDir = rtrim($basePath, "\\") . '\\CHP';

if (!is_dir($matDir) || !is_dir($chpDir)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Diretório CC_DATA_BASE ou subpastas MAT/CHP não encontrados.',
        'basePath' => $basePath,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

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

    $sections = parseIniSections($filePath);
    $desc = $sections['DESC'] ?? [];
    $prop = $sections['PROP_FISIC'] ?? [];

    $name = getIniValue($desc, ['CAMPO1', 'DESC', 'NOME', 'NOME_MATERIAL', 'DESCRICAO'], basename($filePath));
    $family = getIniValue($desc, ['FAMILIA', 'FAMÍLIA', 'FAM', 'FAMILIA_MATERIAL']) ?: getIniValue($prop, ['FAMILIA', 'FAM', 'FAMÍLIA']);

    $searchSource = strtoupper($name . ' ' . $family);
    $tipoMat = getIniValue($desc, ['TIPO_MAT'], getIniValue($prop, ['TIPO_MAT']));

    if (!isMdfMaterial($searchSource, $tipoMat, $prop)) {
        continue;
    }

    $thickness = getIniValue($prop, ['ESPESSURA', 'ESP', 'GROSSURA', 'THICKNESS', 'TG', 'ESP_MONTA']);
    $thickness = normalizeThickness($thickness);

    $precoChapa = parseNumber(getIniValue($sections['PROP_COMERC'] ?? [], ['PRECO_CHAPA']));

    $quantity = findBaseSheetQuantity($chpDir, $code);
    if ($quantity === null) {
        $quantity = 0;
    }

    $qtdMinChp = parseNumber(getIniValue($sections['ESTOQUE'] ?? [], ['QTD_MIN_CHP', 'QTD_MIN', 'QTD_MINIMO']));
    if ($qtdMinChp === null) {
        $qtdMinChp = 0;
    }

    $results[] = [
        'codigo' => $code,
        'nome' => $name,
        'espessura' => $thickness,
        'preco_chapa' => $precoChapa,
        'quantidade' => $quantity,
        'qtd_min_chp' => (int)$qtdMinChp,
    ];
}

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);


function parseIniSections(string $filePath): array
{
    $lines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $sections = [];
    $current = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === ';' || $line[0] === '#') {
            continue;
        }

        if (preg_match('/^\s*\[(.+?)\]\s*$/u', $line, $matches)) {
            $current = strtoupper(trim($matches[1]));
            $sections[$current] = $sections[$current] ?? [];
            continue;
        }

        if ($current === null) {
            continue;
        }

        $pair = preg_split('/\s*=\s*/', $line, 2);
        if (count($pair) < 2) {
            continue;
        }

        $key = strtoupper(trim($pair[0]));
        $value = trim($pair[1]);
        $value = trim($value, '"');
        $sections[$current][$key] = $value;
    }

    return $sections;
}

function getIniValue(array $section, array $keys, $default = '')
{
    foreach ($keys as $key) {
        $up = strtoupper($key);
        if (isset($section[$up]) && $section[$up] !== '') {
            return $section[$up];
        }
    }
    return $default;
}

function normalizeThickness($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $numeric = parseNumber($value);
    if ($numeric === null) {
        return trim($value);
    }

    if (floor($numeric) === $numeric) {
        return (string)(int)$numeric;
    }

    return rtrim(rtrim(number_format($numeric, 2, '.', ''), '0'), '.');
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

function findBaseSheetQuantity(string $baseDir, string $code)
{
    $candidates = buildChpCandidates($code);
    foreach ($candidates as $fileName) {
        $path = $baseDir . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($path)) {
            $quantity = parseChpBaseQuantity($path);
            if ($quantity !== null) {
                return $quantity;
            }
        }
    }

    $glob = glob($baseDir . DIRECTORY_SEPARATOR . 'CHP*' . $code . '*.TAB');
    if ($glob !== false) {
        foreach ($glob as $path) {
            $quantity = parseChpBaseQuantity($path);
            if ($quantity !== null) {
                return $quantity;
            }
        }
    }

    return null;
}

function isMdfMaterial(string $searchSource, $tipoMat, array $prop): bool
{
    if (stripos($searchSource, 'MDF') !== false) {
        return true;
    }

    $tipoMat = trim((string)$tipoMat);
    if ($tipoMat === '1' || strcasecmp($tipoMat, 'MDF') === 0) {
        return true;
    }

    $espessura = getIniValue($prop, ['ESPESSURA', 'ESP', 'GROSSURA', 'THICKNESS', 'TG', 'ESP_MONTA']);
    return $espessura !== '';
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

function parseChpBaseQuantity(string $path)
{
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return null;
    }

    $minQuantity = null;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === ';' || $line[0] === '#') {
            continue;
        }

        $parts = preg_split('/[\s;|]+/', $line);
        if (count($parts) < 5) {
            continue;
        }

        $quantity = parseNumber($parts[2]);
        $width = parseNumber($parts[3]);
        $height = parseNumber($parts[4]);

        if ($width === null || $height === null || $quantity === null) {
            continue;
        }

        if (isBaseSheetDimension($width, $height)) {
            $quantity = (int)round($quantity);
            if ($quantity <= 0) {
                continue;
            }
            if ($minQuantity === null || $quantity < $minQuantity) {
                $minQuantity = $quantity;
            }
        }
    }

    if ($minQuantity !== null) {
        return $minQuantity;
    }

    return null;
}

function isBaseSheetDimension($width, $height): bool
{
    return (
        ($width >= 1830 && $width <= 1850 && $height >= 2730 && $height <= 2750) ||
        ($width >= 2730 && $width <= 2750 && $height >= 1830 && $height <= 1850)
    );
}

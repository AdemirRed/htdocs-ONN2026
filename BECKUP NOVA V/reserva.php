<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Função para buscar o nome do material
function getMaterialName($directory, $code) {
    $files = glob($directory . '/*.INI');
    foreach ($files as $file) {
        $filename = basename($file, '.INI');
        $material_code = ltrim($filename, 'M');
        if (intval($material_code) === $code) {
            $data = @parse_ini_file($file, true);
            if ($data !== false && isset($data['DESC']['CAMPO1'])) {
                return str_replace(['(', ')'], '', $data['DESC']['CAMPO1']);
            }
        }
    }
    return 'Material não encontrado';
}

function reservarRetalho($retalhos, $codigo, $quantidadeSolicitada, $file_path_retalhos) {
    foreach ($retalhos as &$retalho) {
        if ($retalho['codigo'] == $codigo) {
            if ($retalho['quantidade'] >= $quantidadeSolicitada) {
                $retalho['quantidade'] -= $quantidadeSolicitada;
                $retalho['reservado'] += $quantidadeSolicitada;
                break;
            } else {
                die('Quantidade solicitada excede o disponível.');
            }
        }
    }

    $updated_lines = [];
    foreach ($retalhos as $retalho) {
        $updated_lines[] = implode(',', [
            $retalho['codigo'],
            $retalho['ativo'],
            $retalho['quantidade'],
            $retalho['altura'],
            $retalho['largura'],
            $retalho['descricao'],
            $retalho['reservado']
        ]);
    }

    file_put_contents($file_path_retalhos, implode(PHP_EOL, $updated_lines));
    return "Solicitação processada com sucesso!\nRetalho RESERVADO!";
}

$directory_materials = 'C:/CC_DATA_BASE/MAT';
$directory_retalhos = 'C:/CC_DATA_BASE/CHP';

$code = isset($_GET['code']) ? intval($_GET['code']) : 0;
if ($code <= 0) {
    die('Código inválido.');
}

$material_name = getMaterialName($directory_materials, $code);

$file_path_retalhos = $directory_retalhos . "/RET" . str_pad($code, 5, '0', STR_PAD_LEFT) . ".TAB";
if (!file_exists($file_path_retalhos)) {
    die('Arquivo de retalhos não encontrado.');
}

$retalhos = [];
$lines = file($file_path_retalhos, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $fields = explode(',', $line);
    if (count($fields) < 6) continue;

    $retalhos[] = [
        'codigo' => trim($fields[0]),
        'ativo' => trim($fields[1]),
        'quantidade' => (int)trim($fields[2]),
        'altura' => trim($fields[3]),
        'largura' => trim($fields[4]),
        'descricao' => trim($fields[5]),
        'reservado' => isset($fields[6]) ? (int)trim($fields[6]) : 0,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = intval($_POST['codigo']);
    $quantidadeSolicitada = intval($_POST['quantidade']);
    
    $mensagem = reservarRetalho($retalhos, $codigo, $quantidadeSolicitada, $file_path_retalhos);

    echo $mensagem;
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retalhos do Material</title>
</head>
<body>
    <h1>Retalhos do Material: <?= htmlspecialchars($material_name) ?> (Código: <?= htmlspecialchars($code) ?>)</h1>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Ativo</th>
                    <th>Quantidade</th>
                    <th>Altura</th>
                    <th>Largura</th>
                    <th>Descrição</th>
                    <th>Reservado</th>
                    <th>Solicitar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($retalhos as $retalho): ?>
                    <tr>
                        <td><?= htmlspecialchars($retalho['codigo']) ?></td>
                        <td><?= htmlspecialchars($retalho['ativo']) ?></td>
                        <td><?= htmlspecialchars($retalho['quantidade']) ?></td>
                        <td><?= htmlspecialchars($retalho['altura']) ?></td>
                        <td><?= htmlspecialchars($retalho['largura']) ?></td>
                        <td><?= htmlspecialchars($retalho['descricao']) ?></td>
                        <td><?= htmlspecialchars($retalho['reservado']) ?></td>
                        <td>
                            <button onclick="solicitarRetalho(<?= $retalho['codigo'] ?>, '<?= htmlspecialchars($material_name) ?>', <?= $retalho['quantidade'] ?>, '<?= htmlspecialchars($retalho['altura']) ?>', '<?= htmlspecialchars($retalho['largura']) ?>', '<?= htmlspecialchars($retalho['descricao']) ?>')">
                                Solicitar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        function solicitarRetalho(codigo, material, quantidade, altura, largura, descricao) {
            let quantidadeDesejada = quantidade;

            if (quantidade <= 0) {
                alert('Todos os retalhos estão reservados.');
                return;
            }

            if (quantidade > 1) {
                let input = prompt(`Quantos retalhos você deseja solicitar? (Máximo: ${quantidade})`, 1);
                quantidadeDesejada = parseInt(input);

                if (quantidadeDesejada <= 0 || quantidadeDesejada > quantidade || isNaN(quantidadeDesejada)) {
                    alert('Quantidade inválida.');
                    return;
                }
            }

            const formData = new FormData();
            formData.append('codigo', codigo);
            formData.append('quantidade', quantidadeDesejada);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
                .then(response => response.text())
                .then(result => {
                    alert(result);

                    let mensagem = `Olá, gostaria de saber a disponibilidade do seguinte RETALHO:\n\n`;
                    mensagem += `------------------\n`;
                    mensagem += `*Material:* ${material}\n`;
                    mensagem += `*Quantidade:* ${quantidadeDesejada}\n`;
                    mensagem += `*Tamanho:* ${altura}x${largura}\n`;
                    mensagem += `*Descrição:* ${descricao}\n`;
                    mensagem += `------------------\n\n`;
                    mensagem += `Poderiam confirmar a disponibilidade?`;

                    const numeroWhatsApp = "+5551997756708";
                    window.open(`https://wa.me/${numeroWhatsApp}?text=${encodeURIComponent(mensagem)}`, '_blank');
                    
                    location.reload();
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Ocorreu um erro ao processar a solicitação.');
                });
        }
    </script>
</body>
</html>

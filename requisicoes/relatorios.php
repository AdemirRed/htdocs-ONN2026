<?php
require_once 'config.php';

$conn = getConnection();

// Período padrão (último mês)
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

// Relatório de movimentação de estoque
$sql_movimentacao = "
SELECT 
    DATE(r.data_requisicao) as data,
    COUNT(r.id) as total_requisicoes,
    SUM(r.total) as valor_total,
    SUM((SELECT COUNT(*) FROM itens_requisicao WHERE requisicao_id = r.id)) as total_itens
FROM requisicoes r
WHERE DATE(r.data_requisicao) BETWEEN '$data_inicio' AND '$data_fim'
GROUP BY DATE(r.data_requisicao)
ORDER BY data DESC
";

// Itens mais requisitados
$sql_mais_requisitados = "
SELECT 
    i.Codigo,
    i.Nome,
    SUM(ir.quantidade) as total_requisitado,
    COUNT(DISTINCT ir.requisicao_id) as num_requisicoes,
    AVG(ir.quantidade) as media_por_requisicao
FROM itens_requisicao ir
INNER JOIN itens i ON ir.codigo_item = i.Codigo
INNER JOIN requisicoes r ON ir.requisicao_id = r.id
WHERE DATE(r.data_requisicao) BETWEEN '$data_inicio' AND '$data_fim'
GROUP BY i.Codigo, i.Nome
ORDER BY total_requisitado DESC
LIMIT 15
";

// Análise de valor
$sql_valor_por_cliente = "
SELECT 
    cliente,
    COUNT(*) as num_requisicoes,
    SUM(total) as valor_total,
    AVG(total) as media_por_requisicao,
    MAX(total) as maior_requisicao
FROM requisicoes
WHERE DATE(data_requisicao) BETWEEN '$data_inicio' AND '$data_fim'
GROUP BY cliente
ORDER BY valor_total DESC
LIMIT 10
";

$result_movimentacao = $conn->query($sql_movimentacao);
$result_mais_requisitados = $conn->query($sql_mais_requisitados);
$result_valor_cliente = $conn->query($sql_valor_por_cliente);

// Totalizadores do período
$sql_totais = "
SELECT 
    COUNT(*) as total_requisicoes,
    SUM(total) as valor_total_periodo,
    AVG(total) as media_por_requisicao,
    COUNT(DISTINCT cliente) as clientes_unicos
FROM requisicoes
WHERE DATE(data_requisicao) BETWEEN '$data_inicio' AND '$data_fim'
";
$result_totais = $conn->query($sql_totais);
$totais = $result_totais->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios Avançados - ONN Móveis</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .filter-section {
            background: var(--bg-card);
            backdrop-filter: blur(25px);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }

        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 16px;
            align-items: end;
        }

        .chart-container {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            height: 400px;
        }

        .export-buttons {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }

        .btn-export {
            padding: 12px 24px;
            background: var(--success);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .period-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .summary-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--border);
            text-align: center;
        }

        .summary-value {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .summary-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .chart-container {
                height: 300px;
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="logo-section">
            <div class="logo-icon">
                <span class="logo-text">ONN</span>
                <span class="logo-accent">MÓVEIS</span>
            </div>
            <div class="header-divider"></div>
        </div>
        <div class="header-content">
            <i class="fas fa-file-alt"></i>
            <h1>Relatórios Avançados</h1>
            <p class="header-subtitle">Análises Detalhadas de Performance</p>
        </div>
        
        <div class="dashboard-nav">
            <a href="dashboard.php" class="nav-btn">
                <i class="fas fa-chart-bar"></i>Dashboard
            </a>
            <a href="index.php" class="nav-btn">
                <i class="fas fa-clipboard-list"></i>Nova Requisição
            </a>
            <a href="../a/estoque.php" class="nav-btn">
                <i class="fas fa-boxes"></i>Estoque
            </a>
            <a href="relatorios.php" class="nav-btn active">
                <i class="fas fa-file-alt"></i>Relatórios
            </a>
        </div>
    </div>

    <div class="container">
        <!-- Filtros -->
        <div class="filter-section">
            <form method="GET" class="filter-row">
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Data Início</label>
                    <input type="date" name="data_inicio" value="<?= $data_inicio ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Data Fim</label>
                    <input type="date" name="data_fim" value="<?= $data_fim ?>" required>
                </div>
                <button type="submit" class="btn-primary" style="height: 50px; width: auto; padding: 0 24px;">
                    <i class="fas fa-search"></i> Filtrar
                </button>
            </form>
        </div>

        <!-- Resumo do Período -->
        <div class="period-summary">
            <div class="summary-card">
                <div class="summary-value"><?= $totais['total_requisicoes'] ?: 0 ?></div>
                <div class="summary-label">Total de Requisições</div>
            </div>
            <div class="summary-card">
                <div class="summary-value">R$ <?= number_format($totais['valor_total_periodo'] ?: 0, 2, ',', '.') ?></div>
                <div class="summary-label">Valor Total</div>
            </div>
            <div class="summary-card">
                <div class="summary-value">R$ <?= number_format($totais['media_por_requisicao'] ?: 0, 2, ',', '.') ?></div>
                <div class="summary-label">Média por Requisição</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?= $totais['clientes_unicos'] ?: 0 ?></div>
                <div class="summary-label">Clientes Únicos</div>
            </div>
        </div>

        <!-- Gráfico de Movimentação -->
        <div class="chart-container">
            <canvas id="movimentacaoChart"></canvas>
        </div>

        <!-- Relatórios Detalhados -->
        <div class="reports-grid">
            <!-- Itens Mais Requisitados -->
            <div class="report-card">
                <div class="report-header">
                    <i class="fas fa-trophy"></i>
                    <h3>Itens Mais Requisitados</h3>
                </div>
                <div class="report-list">
                    <?php if ($result_mais_requisitados && $result_mais_requisitados->num_rows > 0): ?>
                        <?php $posicao = 1; ?>
                        <?php while ($item = $result_mais_requisitados->fetch_assoc()): ?>
                            <div class="report-item">
                                <div class="item-info">
                                    <div class="item-name">
                                        <span style="color: var(--warning); font-weight: 900; margin-right: 8px;">#<?= $posicao ?></span>
                                        <?= htmlspecialchars($item['Nome']) ?>
                                    </div>
                                    <div class="item-code">
                                        Código: <?= htmlspecialchars($item['Codigo']) ?> | 
                                        <?= $item['num_requisicoes'] ?> requisições
                                    </div>
                                </div>
                                <div class="item-value">
                                    <div class="item-stock normal"><?= $item['total_requisitado'] ?> unidades</div>
                                    <div class="item-price">Média: <?= number_format($item['media_por_requisicao'], 1) ?></div>
                                </div>
                            </div>
                            <?php $posicao++; ?>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="report-item">
                            <div class="item-info">
                                <div class="item-name">Nenhum item requisitado no período</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Análise por Cliente -->
            <div class="report-card">
                <div class="report-header">
                    <i class="fas fa-users"></i>
                    <h3>Principais Clientes</h3>
                </div>
                <div class="report-list">
                    <?php if ($result_valor_cliente && $result_valor_cliente->num_rows > 0): ?>
                        <?php while ($cliente = $result_valor_cliente->fetch_assoc()): ?>
                            <div class="report-item">
                                <div class="item-info">
                                    <div class="item-name"><?= htmlspecialchars($cliente['cliente']) ?></div>
                                    <div class="item-code">
                                        <?= $cliente['num_requisicoes'] ?> requisições | 
                                        Maior: R$ <?= number_format($cliente['maior_requisicao'], 2, ',', '.') ?>
                                    </div>
                                </div>
                                <div class="item-value">
                                    <div class="requisition-total">R$ <?= number_format($cliente['valor_total'], 2, ',', '.') ?></div>
                                    <div class="item-price">Média: R$ <?= number_format($cliente['media_por_requisicao'], 2, ',', '.') ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="report-item">
                            <div class="item-info">
                                <div class="item-name">Nenhum cliente no período</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Movimentação Diária -->
            <div class="report-card">
                <div class="report-header">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>Movimentação Diária</h3>
                </div>
                <div class="report-list">
                    <?php if ($result_movimentacao && $result_movimentacao->num_rows > 0): ?>
                        <?php while ($dia = $result_movimentacao->fetch_assoc()): ?>
                            <div class="report-item">
                                <div class="item-info">
                                    <div class="item-name"><?= date('d/m/Y - l', strtotime($dia['data'])) ?></div>
                                    <div class="item-code">
                                        <?= $dia['total_requisicoes'] ?> requisições | 
                                        <?= $dia['total_itens'] ?> itens movimentados
                                    </div>
                                </div>
                                <div class="item-value">
                                    <div class="requisition-total">R$ <?= number_format($dia['valor_total'], 2, ',', '.') ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="report-item">
                            <div class="item-info">
                                <div class="item-name">Nenhuma movimentação no período</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Botões de Exportação -->
        <div class="export-buttons">
            <button class="btn-export" onclick="exportarPDF()">
                <i class="fas fa-file-pdf"></i> Exportar PDF
            </button>
            <button class="btn-export" onclick="exportarExcel()">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </button>
            <button class="btn-export" onclick="imprimirRelatorio()">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>

    <script>
        // Dados para o gráfico
        const movimentacaoData = [
            <?php 
            $conn->query($sql_movimentacao);
            $result_chart = $conn->query($sql_movimentacao);
            $dados_grafico = [];
            if ($result_chart && $result_chart->num_rows > 0) {
                while ($row = $result_chart->fetch_assoc()) {
                    $dados_grafico[] = json_encode($row);
                }
            }
            echo implode(',', $dados_grafico);
            ?>
        ];

        // Configurar gráfico
        const ctx = document.getElementById('movimentacaoChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: movimentacaoData.map(item => {
                    const date = new Date(item.data);
                    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                }),
                datasets: [{
                    label: 'Valor Total (R$)',
                    data: movimentacaoData.map(item => parseFloat(item.valor_total)),
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Nº de Requisições',
                    data: movimentacaoData.map(item => parseInt(item.total_requisicoes)),
                    borderColor: 'rgb(79, 172, 254)',
                    backgroundColor: 'rgba(79, 172, 254, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Movimentação por Dia',
                        color: 'white',
                        font: { size: 16, weight: 'bold' }
                    },
                    legend: {
                        labels: { color: 'white' }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { color: 'white' }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { color: 'white' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: { color: 'white' }
                    }
                }
            }
        });

        function exportarPDF() {
            window.print();
        }

        function exportarExcel() {
            alert('Funcionalidade em desenvolvimento');
        }

        function imprimirRelatorio() {
            window.print();
        }
    </script>
</body>
</html>
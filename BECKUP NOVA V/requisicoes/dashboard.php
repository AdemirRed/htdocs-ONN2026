<?php
require_once 'config.php';

$conn = getConnection();

// Buscar estatísticas de estoque
$sql_estatisticas = "
SELECT 
    COUNT(*) as total_itens,
    SUM(EstoqueAtual * (Valor/100)) as valor_total_estoque,
    AVG(EstoqueAtual) as media_estoque,
    COUNT(CASE WHEN EstoqueAtual = 0 THEN 1 END) as itens_zerados,
    COUNT(CASE WHEN EstoqueAtual <= 5 THEN 1 END) as itens_baixos
FROM itens
";

$result_stats = $conn->query($sql_estatisticas);
$stats = $result_stats->fetch_assoc();

// Buscar itens com estoque baixo
$sql_baixo = "SELECT Codigo, Nome, EstoqueAtual, (Valor/100) as Valor FROM itens WHERE EstoqueAtual <= 5 ORDER BY EstoqueAtual ASC LIMIT 10";
$result_baixo = $conn->query($sql_baixo);

// Buscar itens mais valiosos
$sql_valiosos = "SELECT Codigo, Nome, EstoqueAtual, (Valor/100) as Valor, (EstoqueAtual * Valor/100) as valor_total FROM itens ORDER BY valor_total DESC LIMIT 10";
$result_valiosos = $conn->query($sql_valiosos);

// Buscar últimas requisições
$sql_requisicoes = "
SELECT r.id, r.usuario, r.data_requisicao, r.total, r.status
FROM requisicoes r
ORDER BY r.data_requisicao DESC
LIMIT 10
";
$result_requisicoes = $conn->query($sql_requisicoes);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - Controle de Estoque | ONN Móveis</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-header {
            background: var(--bg-card);
            backdrop-filter: blur(40px) saturate(200%);
            border-bottom: 1px solid var(--border);
            padding: 24px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-xl);
            animation: headerSlide 1s var(--ease-bounce);
        }

        .dashboard-nav {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .nav-btn {
            padding: 12px 24px;
            background: var(--bg-elevated);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 14px;
            font-weight: 600;
            border: 2px solid var(--border);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .nav-btn.active {
            background: var(--primary);
            border-color: var(--primary-solid);
            color: white;
        }

        .nav-btn:hover {
            transform: translateY(-2px);
            border-color: var(--primary-solid);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-card);
            backdrop-filter: blur(25px);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            animation: cardFloat 0.8s var(--ease-bounce) calc(var(--index, 0) * 0.1s) both;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 1.8rem;
            color: white;
        }

        .stat-icon.total { background: var(--primary); }
        .stat-icon.value { background: var(--success); }
        .stat-icon.low { background: var(--warning); }
        .stat-icon.zero { background: var(--danger); }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 8px;
            line-height: 1;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .report-card {
            background: var(--bg-card);
            backdrop-filter: blur(25px);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            animation: cardSlideIn 0.8s var(--ease-bounce) 0.3s both;
        }

        .report-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .report-header h3 {
            color: var(--text-primary);
            font-size: 1.3rem;
            font-weight: 700;
            flex: 1;
        }

        .report-header i {
            background: var(--primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 1.4rem;
        }

        .report-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .report-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            margin-bottom: 8px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid var(--border);
            transition: all 0.3s;
        }

        .report-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }

        .item-code {
            color: var(--text-muted);
            font-size: 0.8rem;
            font-family: monospace;
        }

        .item-value {
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .item-stock {
            font-weight: 700;
            font-size: 1rem;
        }

        .item-stock.low { color: var(--warning); }
        .item-stock.zero { color: var(--danger); }
        .item-stock.normal { color: var(--success); }

        .item-price {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .requisition-date {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        .requisition-total {
            color: var(--success);
            font-weight: 700;
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 16px;
            }
            
            .reports-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .dashboard-nav {
                gap: 8px;
            }

            .nav-btn {
                padding: 10px 16px;
                font-size: 0.85rem;
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
            <i class="fas fa-chart-line"></i>
            <h1>Dashboard de Controle</h1>
            <p class="header-subtitle">Gestão Completa de Estoque</p>
        </div>
        
        <div class="dashboard-nav">
            <a href="dashboard.php" class="nav-btn active">
                <i class="fas fa-chart-bar"></i>Dashboard
            </a>
            <a href="index.php" class="nav-btn">
                <i class="fas fa-clipboard-list"></i>Nova Requisição
            </a>
            <a href="../a/estoque.php" class="nav-btn">
                <i class="fas fa-boxes"></i>Estoque
            </a>
            <a href="relatorios.php" class="nav-btn">
                <i class="fas fa-file-alt"></i>Relatórios
            </a>
        </div>
    </div>

    <div class="container">
        <!-- Estatísticas Principais -->
        <div class="stats-grid">
            <div class="stat-card" style="--index: 1">
                <div class="stat-icon total">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="stat-number"><?= number_format($stats['total_itens']) ?></div>
                <div class="stat-label">Total de Itens</div>
            </div>

            <div class="stat-card" style="--index: 2">
                <div class="stat-icon value">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-number">R$ <?= number_format($stats['valor_total_estoque'], 2, ',', '.') ?></div>
                <div class="stat-label">Valor Total em Estoque</div>
            </div>

            <div class="stat-card" style="--index: 3">
                <div class="stat-icon low">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-number"><?= $stats['itens_baixos'] ?></div>
                <div class="stat-label">Estoque Baixo (≤5)</div>
            </div>

            <div class="stat-card" style="--index: 4">
                <div class="stat-icon zero">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-number"><?= $stats['itens_zerados'] ?></div>
                <div class="stat-label">Itens Zerados</div>
            </div>
        </div>

        <!-- Relatórios -->
        <div class="reports-grid">
            <!-- Itens com Estoque Baixo -->
            <div class="report-card">
                <div class="report-header">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Estoque Baixo</h3>
                </div>
                <div class="report-list">
                    <?php if ($result_baixo && $result_baixo->num_rows > 0): ?>
                        <?php while ($item = $result_baixo->fetch_assoc()): ?>
                            <div class="report-item">
                                <div class="item-info">
                                    <div class="item-name"><?= htmlspecialchars($item['Nome']) ?></div>
                                    <div class="item-code">Código: <?= htmlspecialchars($item['Codigo']) ?></div>
                                </div>
                                <div class="item-value">
                                    <div class="item-stock <?= $item['EstoqueAtual'] == 0 ? 'zero' : 'low' ?>">
                                        <?= $item['EstoqueAtual'] ?> unidades
                                    </div>
                                    <div class="item-price">R$ <?= number_format($item['Valor'], 2, ',', '.') ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="report-item">
                            <div class="item-info">
                                <div class="item-name" style="color: var(--success);">
                                    <i class="fas fa-check-circle"></i> Todos os itens com estoque adequado!
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Itens Mais Valiosos -->
            <div class="report-card">
                <div class="report-header">
                    <i class="fas fa-gem"></i>
                    <h3>Itens Mais Valiosos</h3>
                </div>
                <div class="report-list">
                    <?php if ($result_valiosos && $result_valiosos->num_rows > 0): ?>
                        <?php while ($item = $result_valiosos->fetch_assoc()): ?>
                            <div class="report-item">
                                <div class="item-info">
                                    <div class="item-name"><?= htmlspecialchars($item['Nome']) ?></div>
                                    <div class="item-code">Código: <?= htmlspecialchars($item['Codigo']) ?></div>
                                </div>
                                <div class="item-value">
                                    <div class="item-stock normal"><?= $item['EstoqueAtual'] ?> unidades</div>
                                    <div class="item-price">R$ <?= number_format($item['valor_total'], 2, ',', '.') ?> total</div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Últimas Requisições -->
            <div class="report-card">
                <div class="report-header">
                    <i class="fas fa-history"></i>
                    <h3>Últimas Requisições</h3>
                </div>
                <div class="report-list">
                    <?php if ($result_requisicoes && $result_requisicoes->num_rows > 0): ?>
                        <?php while ($req = $result_requisicoes->fetch_assoc()): ?>
                            <div class="report-item">
                                <div class="item-info">
                                    <div class="item-name"><?= htmlspecialchars($req['usuario']) ?></div>
                                    <div class="requisition-date">
                                        <?= date('d/m/Y H:i', strtotime($req['data_requisicao'])) ?> 
                                        - <?= ucfirst($req['status']) ?>
                                    </div>
                                </div>
                                <div class="item-value">
                                    <div class="requisition-total">R$ <?= number_format($req['total'], 2, ',', '.') ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="report-item">
                            <div class="item-info">
                                <div class="item-name">Nenhuma requisição encontrada</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Atualizar dados a cada 30 segundos
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
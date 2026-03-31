<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>▲ NEXUS - Sistema de Materiais</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>▲</text></svg>">
    <link href="https://fonts.googleapis.com/css?family=Inter:400,700|JetBrains+Mono:400,700&display=swap" rel="stylesheet">
    
    <style>
        /* Tema Nexus com ícone ▲ */
        :root {
            --nexus-bg-primary: #0d1117;
            --nexus-bg-secondary: #161b22;
            --nexus-bg-tertiary: #21262d;
            --nexus-bg-glass: rgba(22, 27, 34, 0.95);
            --nexus-border: #30363d;
            --nexus-text-primary: #f0f6fc;
            --nexus-text-secondary: #8b949e;
            --nexus-accent-blue: #58a6ff;
            --nexus-accent-green: #3fb950;
            --nexus-accent-purple: #a5a5ff;
            --nexus-accent-orange: #ffa657;
            --nexus-accent-cyan: #39d0d8;
            --nexus-hover-bg: #30363d;
            --nexus-focus-border: #1f6feb;
            --nexus-error: #f85149;
            --nexus-warning: #d29922;
            --nexus-shadow: rgba(0, 0, 0, 0.7);
            --nexus-glow: rgba(88, 166, 255, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, var(--nexus-bg-primary) 0%, var(--nexus-bg-secondary) 50%, var(--nexus-bg-tertiary) 100%);
            color: var(--nexus-text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background-attachment: fixed;
            position: relative;
            overflow-x: hidden;
        }

        /* Efeito de partículas de fundo */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 25% 25%, rgba(88, 166, 255, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 75% 75%, rgba(165, 165, 255, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 50% 50%, rgba(63, 185, 80, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        /* Header principal */
        .nexus-header {
            background: linear-gradient(135deg, var(--nexus-accent-blue), var(--nexus-accent-purple), var(--nexus-accent-cyan));
            background-size: 300% 300%;
            animation: gradientShift 4s ease-in-out infinite;
            color: #0d1117;
            font-weight: 800;
            letter-spacing: 3px;
            border-radius: 20px;
            box-shadow: 0 8px 40px var(--nexus-shadow), 0 0 40px var(--nexus-glow);
            padding: 25px 45px;
            margin-bottom: 40px;
            font-size: 2.5em;
            text-align: center;
            text-shadow: 0 2px 10px rgba(13, 17, 23, 0.5);
            position: relative;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nexus-header::before {
            content: "▲";
            font-size: 0.8em;
            color: #0d1117;
            margin-right: 12px;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 900;
            text-shadow: 0 0 10px rgba(88, 166, 255, 0.8);
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Card principal */
        .nexus-card {
            background: var(--nexus-bg-glass);
            border-radius: 24px;
            box-shadow: 0 16px 60px var(--nexus-shadow), 
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
            padding: 45px 35px 40px 35px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 380px;
            max-width: 95vw;
            backdrop-filter: blur(20px);
            border: 1px solid var(--nexus-border);
            position: relative;
        }

        .nexus-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--nexus-accent-blue), var(--nexus-accent-purple), var(--nexus-accent-cyan));
            border-radius: 24px 24px 0 0;
        }

        /* Grade de botões */
        .nexus-btn-grid {
            display: flex;
            flex-direction: column;
            gap: 18px;
            width: 100%;
            margin-top: 20px;
        }

        /* Botões principais */
        .nexus-btn {
            background: linear-gradient(135deg, var(--nexus-accent-blue), var(--nexus-accent-purple));
            color: var(--nexus-text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            padding: 18px 24px;
            width: 100%;
            font-size: 1.1rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(88, 166, 255, 0.3), 
                        inset 0 1px 0 rgba(255, 255, 255, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            outline: none;
            text-decoration: none;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nexus-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s;
        }

        .nexus-btn:hover::before {
            left: 100%;
        }

        .nexus-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(88, 166, 255, 0.4), 
                        inset 0 1px 0 rgba(255, 255, 255, 0.3);
            background: linear-gradient(135deg, var(--nexus-accent-purple), var(--nexus-accent-cyan));
        }

        .nexus-btn:active {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(88, 166, 255, 0.5);
        }

        /* Botões especiais com cores diferentes */
        .nexus-btn:nth-child(2) {
            background: linear-gradient(135deg, var(--nexus-accent-green), #2ea043);
            box-shadow: 0 4px 20px rgba(63, 185, 80, 0.3);
        }

        .nexus-btn:nth-child(2):hover {
            background: linear-gradient(135deg, #2ea043, var(--nexus-accent-green));
            box-shadow: 0 8px 30px rgba(63, 185, 80, 0.4);
        }

        .nexus-btn:nth-child(3) {
            background: linear-gradient(135deg, var(--nexus-accent-orange), var(--nexus-warning));
            box-shadow: 0 4px 20px rgba(255, 166, 87, 0.3);
        }

        .nexus-btn:nth-child(3):hover {
            background: linear-gradient(135deg, var(--nexus-warning), var(--nexus-accent-orange));
            box-shadow: 0 8px 30px rgba(255, 166, 87, 0.4);
        }

        .nexus-btn:nth-child(4) {
            background: linear-gradient(135deg, var(--nexus-accent-purple), #8b5cf6);
            box-shadow: 0 4px 20px rgba(165, 165, 255, 0.3);
        }

        .nexus-btn:nth-child(4):hover {
            background: linear-gradient(135deg, #8b5cf6, var(--nexus-accent-purple));
            box-shadow: 0 8px 30px rgba(165, 165, 255, 0.4);
        }

        .nexus-btn:nth-child(5) {
            background: linear-gradient(135deg, var(--nexus-accent-cyan), #06b6d4);
            box-shadow: 0 4px 20px rgba(57, 208, 216, 0.3);
        }

        .nexus-btn:nth-child(5):hover {
            background: linear-gradient(135deg, #06b6d4, var(--nexus-accent-cyan));
            box-shadow: 0 8px 30px rgba(57, 208, 216, 0.4);
        }

        .nexus-btn:nth-child(6) {
            background: linear-gradient(135deg, var(--nexus-error), #dc2626);
            box-shadow: 0 4px 20px rgba(248, 81, 73, 0.3);
        }

        .nexus-btn:nth-child(6):hover {
            background: linear-gradient(135deg, #dc2626, var(--nexus-error));
            box-shadow: 0 8px 30px rgba(248, 81, 73, 0.4);
        }

        .nexus-btn:nth-child(7) {
            background: linear-gradient(135deg, #10b981, #059669);
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
        }

        .nexus-btn:nth-child(7):hover {
            background: linear-gradient(135deg, #059669, #10b981);
            box-shadow: 0 8px 30px rgba(16, 185, 129, 0.4);
        }

        /* Estado desativado */
        .nexus-btn[disabled], 
        .nexus-btn.desativado {
            background: linear-gradient(135deg, var(--nexus-bg-tertiary), var(--nexus-hover-bg));
            color: var(--nexus-text-secondary) !important;
            cursor: not-allowed;
            box-shadow: none;
            pointer-events: none;
            opacity: 0.6;
            transform: none;
        }

        /* Ícones nos botões */
        .nexus-btn::after {
            content: attr(data-icon);
            margin-left: 8px;
            font-size: 1.2em;
        }

        /* Footer */
        .nexus-footer {
            color: var(--nexus-text-secondary);
            margin-top: 45px;
            font-size: 1.1em;
            text-align: center;
            letter-spacing: 1.5px;
            font-weight: 500;
            opacity: 0.8;
        }

        .nexus-footer p {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .nexus-footer::before {
            content: "▲";
            color: var(--nexus-accent-blue);
            font-size: 0.8em;
            margin-right: 5px;
        }

        /* Info do usuário */
        .user-info {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--nexus-bg-glass);
            padding: 10px 16px;
            border-radius: 20px;
            border: 1px solid var(--nexus-border);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--nexus-text-secondary);
        }

        .user-avatar {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, var(--nexus-accent-blue), var(--nexus-accent-purple));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            color: white;
        }

        .datetime-info {
            position: absolute;
            top: 20px;
            left: 20px;
            background: var(--nexus-bg-glass);
            padding: 8px 14px;
            border-radius: 15px;
            border: 1px solid var(--nexus-border);
            backdrop-filter: blur(10px);
            font-size: 12px;
            color: var(--nexus-text-secondary);
            font-family: 'JetBrains Mono', monospace;
        }

        /* Animações de entrada */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .nexus-header {
            animation: fadeInUp 0.8s ease-out;
        }

        .nexus-card {
            animation: fadeInUp 1s ease-out 0.2s both;
        }

        .nexus-footer {
            animation: fadeInUp 1.2s ease-out 0.4s both;
        }
        .nexus-btn:nth-child(5) { animation: fadeInUp 0.6s ease-out 1.0s both; }
        .nexus-btn:nth-child(6) { animation: fadeInUp 0.6s ease-out 1.1s both; }
        .nexus-btn:nth-child(7) { animation: fadeInUp 0.6s ease-out 1.2s both; }
            animation: fadeInUp 0.6s ease-out 0.1s both;
        }

        /* Botões com delay de animação */
        .nexus-btn:nth-child(1) { animation: fadeInUp 0.6s ease-out 0.6s both; }
        .nexus-btn:nth-child(2) { animation: fadeInUp 0.6s ease-out 0.7s both; }
        .nexus-btn:nth-child(3) { animation: fadeInUp 0.6s ease-out 0.8s both; }
        .nexus-btn:nth-child(4) { animation: fadeInUp 0.6s ease-out 0.9s both; }
        .nexus-btn:nth-child(5) { animation: fadeInUp 0.6s ease-out 1.0s both; }
        .nexus-btn:nth-child(6) { animation: fadeInUp 0.6s ease-out 1.1s both; }

        /* Responsivo */
        @media (max-width: 480px) {
            .nexus-card { 
                padding: 30px 20px; 
                min-width: unset;
                margin: 0 15px;
            }
            .nexus-header { 
                font-size: 1.8em; 
                padding: 20px 25px;
                margin: 0 15px 30px 15px;
            }
            .nexus-btn { 
                font-size: 1em; 
                padding: 16px 20px;
            }
            .user-info, .datetime-info {
                position: relative;
                top: auto;
                left: auto;
                right: auto;
                margin-bottom: 20px;
            }
        }

        @media (max-width: 320px) {
            .nexus-header {
                font-size: 1.5em;
                padding: 15px 20px;
            }
            .nexus-btn {
                font-size: 0.95em;
                padding: 14px 16px;
            }
        }

        /* Efeito de pulsação no header */
        @keyframes pulse {
            0%, 100% { box-shadow: 0 8px 40px var(--nexus-shadow), 0 0 40px var(--nexus-glow); }
            50% { box-shadow: 0 12px 50px var(--nexus-shadow), 0 0 60px var(--nexus-glow); }
        }

        .nexus-header {
            animation: fadeInUp 0.8s ease-out, pulse 3s ease-in-out infinite 2s;
        }
    </style>
</head>
<body>
    <!-- Informações de data/hora -->
    <div class="datetime-info">
        📅 20/06/2025 • 🕐 14:37
    </div>

    <!-- Header principal -->
    <div class="nexus-header">Sistema de Materiais</div>
    
    <!-- Card principal -->
    <div class="nexus-card">
        <div class="nexus-btn-grid">
            <a href="http://192.168.0.201/materiais_view.php" class="nexus-btn" data-icon="📋">
                Ver Retalhos e Código das Chapas
            </a>
            <a href="http://192.168.0.201/retalhos_filtro.php" class="nexus-btn" id="meuBotao" data-icon="🔍">
                Procurar Por Tamanho
            </a>
            <a href="http://192.168.0.201/Ripado.html" class="nexus-btn" data-icon="📐">
                Calcular Ripados
            </a>
			<a href="http://192.168.0.201/calculadora_muxarabi.html" class="nexus-btn" data-icon="📐">
                Calcular Muxarabi
            </a>
            <a href="http://192.168.0.201/projetos.php" class="nexus-btn" data-icon="📁">
                Ver Lista de Projetos
            </a>
			<a href="https://192.168.0.201/baixar_retalhos.php" class="nexus-btn" data-icon="⬇️">
                Baixa nos Retalhos
            </a>
            <a href="http://192.168.0.201/a/pedidos.php" class="nexus-btn" data-icon="🛒">
                Pedidos de Compras
            </a>
            <a href="http://192.168.0.201/requisicoes/" class="nexus-btn" data-icon="📦">
                Requisição de Materiais
            </a>
            <a href="http://192.168.0.201/requisicoes/dashboard.php" class="nexus-btn" data-icon="📊">
                Dashboard de Estoque
            </a>
            
        </div>
    </div>
    
    <!-- Footer -->
    <div class="nexus-footer">
        <p>&copy; 2025 RedBlack Ω</p>
    </div>

    <script>
        // Data e hora atualizadas dinamicamente
        function updateDateTime() {
            const now = new Date();
            const dateStr = now.toLocaleDateString('pt-BR');
            const timeStr = now.toLocaleTimeString('pt-BR', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            const datetimeInfo = document.querySelector('.datetime-info');
            if (datetimeInfo) {
                datetimeInfo.innerHTML = `📅 ${dateStr} • 🕐 ${timeStr}`;
            }
        }

        // Atualizar a cada minuto
        setInterval(updateDateTime, 60000);
        updateDateTime(); // Atualizar imediatamente

        // Efeitos sonoros (opcional)
        function playClickSound() {
            const audio = new Audio();
            audio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+PwtmMcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQAA==';
            audio.volume = 0.1;
            audio.play().catch(() => {}); // Ignorar erros
        }

        // Adicionar efeitos sonoros aos botões
        document.querySelectorAll('.nexus-btn').forEach(btn => {
            btn.addEventListener('click', playClickSound);
        });

        // Efeito de partículas flutuantes (opcional)
        function createFloatingParticle() {
            const particle = document.createElement('div');
            particle.style.cssText = `
                position: fixed;
                width: 4px;
                height: 4px;
                background: linear-gradient(45deg, #58a6ff, #a5a5ff);
                border-radius: 50%;
                pointer-events: none;
                z-index: -1;
                left: ${Math.random() * 100}vw;
                top: 100vh;
                opacity: 0.6;
                animation: float-up 8s linear forwards;
            `;
            
            document.body.appendChild(particle);
            
            setTimeout(() => {
                particle.remove();
            }, 8000);
        }

        // Adicionar keyframes para partículas
        const style = document.createElement('style');
        style.textContent = `
            @keyframes float-up {
                to {
                    transform: translateY(-100vh) rotate(360deg);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Criar partículas periodicamente
        setInterval(createFloatingParticle, 2000);

        /*
        // Desativar botão específico (descomente se necessário)
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById("meuBotao").classList.add("desativado");
        });
        */
    </script>
</body>
</html>
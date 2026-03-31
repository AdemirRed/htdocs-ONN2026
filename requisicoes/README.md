# Sistema de Requisição de Materiais - ONN Móveis

## 📋 Descrição

Sistema web otimizado para celular que permite selecionar materiais clicando em cards visuais, gerar PDF automaticamente e dar baixa no estoque.

## ⚙️ Instalação

### 1. Instalar dependências (Dompdf para geração de PDF)

Abra o terminal na pasta `c:\xampp\htdocs\requisicoes` e execute:

```bash
composer install
```

Se não tiver o Composer instalado, baixe em: https://getcomposer.org/download/

### 2. Criar as tabelas no banco de dados

Acesse no navegador:
```
http://localhost/requisicoes/criar_tabelas.php
```

### 3. Pronto! Acesse o sistema

```
http://localhost/requisicoes/
```

## 🎯 Funcionalidades

✅ **Interface Mobile-First**: Totalmente otimizado para celular
✅ **Seleção Visual**: Cards grandes com fotos e botões +/-
✅ **Aviso de Estoque**: Alerta visual quando estoque está zerado (NÃO bloqueia)
✅ **Busca Rápida**: Filtro de materiais em tempo real
✅ **Geração de PDF**: Documento profissional com todos os dados
✅ **Baixa Automática**: Estoque atualizado só APÓS gerar o PDF
✅ **Histórico**: Todas as requisições ficam registradas no banco

## 📱 Como Usar

1. **Preencher Informações**
   - Nome do Cliente
   - Marceneiro Responsável
   - Informações do Serviço

2. **Selecionar Materiais**
   - Clique nos botões + e - para ajustar quantidades
   - Use a busca para filtrar materiais
   - Items sem estoque mostram aviso mas podem ser selecionados

3. **Gerar PDF**
   - Clique em "Gerar PDF"
   - PDF é criado e aberto automaticamente
   - Estoque recebe baixa automática

## 🗂️ Estrutura de Arquivos

```
requisicoes/
├── index.php              # Página principal
├── processar_requisicao.php  # Backend que gera PDF
├── style.css              # Estilos (tema escuro profissional)
├── config.php             # Configurações do banco
├── criar_tabelas.php      # Script de instalação
├── composer.json          # Dependências (Dompdf)
├── vendor/                # Bibliotecas instaladas pelo Composer
└── pdfs/                  # PDFs gerados (criado automaticamente)
```

## 🎨 Características de Design

- **Tema Escuro Profissional**: Cores consistentes com o sistema principal
- **Cards Grandes**: Fácil de clicar no celular
- **Botões Visuais**: + e - grandes e coloridos
- **Feedback Visual**: Animações suaves
- **Resumo Flutuante**: Barra fixada na parte inferior
- **Responsivo**: Adapta-se a qualquer tamanho de tela

## 📊 Banco de Dados

### Tabela: `requisicoes`
- Armazena cabeçalho da requisição
- Cliente, marceneiro, serviço
- Caminho do PDF gerado
- Status (pendente/finalizada)

### Tabela: `itens_requisicao`
- Itens selecionados em cada requisição
- Quantidade, valores
- Relacionado com `requisicoes`

## 🔧 Configuração

Edite `config.php` se necessário alterar dados de conexão:

```php
define('DB_HOST', '192.168.0.201');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'onnmoveis');
```

## ⚠️ Requisitos

- PHP 7.4 ou superior
- MySQL/MariaDB
- Composer
- Apache/XAMPP

## 📄 Geração de PDF

O PDF gerado contém:
- ✅ Cabeçalho profissional com logo
- ✅ Número da requisição
- ✅ Dados do cliente e marceneiro
- ✅ Data e hora
- ✅ Tabela completa de materiais
- ✅ Valores unitários e totais
- ✅ Total geral
- ✅ Rodapé com informações do sistema

## 🚀 Otimizações

- Carregamento rápido
- Sem dependências externas de CDN (exceto Font Awesome)
- Animações suaves
- Código limpo e organizado
- Transações no banco para garantir integridade

---

**Desenvolvido para ONN Móveis** 🏢
Sistema de gestão de requisições de materiais

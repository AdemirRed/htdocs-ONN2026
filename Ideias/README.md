# Editor de Plano de Corte - Titanium

Sistema modular para visualização e edição de arquivos `.cutplanning` com interface gráfica moderna.

## 📁 Estrutura do Projeto

```
Ideias/
├── editor-plano-corte.py       # Aplicação principal
├── data_parser.py               # Parser de arquivos XML .cutplanning
├── tree_view_manager.py         # Gerenciador da árvore de materiais/chapas
├── canvas_renderer.py            # Renderização visual no Canvas (zoom/pan)
├── edit_controls.py             # Painel de controles de edição
└── *.cutplanning               # Arquivos de plano de corte
```

## 🎯 Funcionalidades

### ✅ Implementadas

- **Visualização Hierárquica**: Árvore de materiais agrupados por cor com chapas e peças
- **Edição de Dimensões**: Painel lateral para editar largura e altura das peças
  - Atualiza coordenadas reais dos cortes (P e Y)
  - Atualiza campos de etiqueta (187, 189, 190, 191)
- **Múltiplos Materiais**: Suporte completo para arquivos com várias cores e materiais
- **Múltiplas Chapas**: Visualização de todas as chapas de cada material
- **Salvamento XML**: Preserva formatação original do arquivo
- **Interface Simplificada**: Layout em 2 colunas (TreeView + Editor)

### 📊 Interface

A interface é dividida em 3 colunas:

1. **Esquerda (25%)**: TreeView com materiais, chapas e peças
  - Agrupamento por cor
  - Contador de chapas e peças
  - Seleção de chapa para editar

2. **Centro (50%)**: Canvas de visualização
  - Desenho da chapa e disposição simples das peças
  - Zoom e pan

3. **Direita (25%)**: Painel de edição
  - Informações da chapa selecionada
  - Edição de dimensões das peças (largura x altura)
  - Atualização em tempo real dos cortes P e Y
  - Informações de quantidade e ambiente

## 🚀 Como Usar

### 1. Executar o Aplicativo

```powershell
cd C:\xampp\htdocs\Ideias
python editor-plano-corte.py
```

### 2. Abrir Arquivo

- Clique em **"📂 ABRIR ARQUIVO"** ou pressione `Ctrl+O`
- Selecione um arquivo `.cutplanning`
- O arquivo será carregado e a árvore de materiais será populada

### 3. Navegar

- **Expandir materiais**: Clique no ícone ▶ ao lado do material
- **Selecionar chapa**: Clique em uma chapa na árvore
- **Visualizar**: O plano de corte aparecerá no canvas central

### 4. Editar

- **Selecionar peça**: Clique em uma chapa na árvore para carregar suas peças
- **Editar dimensões**: Modifique largura ou altura nos campos de entrada
- **Atualização automática**: Ao digitar, os valores são atualizados no XML
  - Coordenadas dos cortes P (largura) e Y (altura)
  - Campos de etiqueta (187, 189, 190, 191)
- **Salvar**: Clique em **"💾 SALVAR"** ou pressione `Ctrl+S`

### 5. Atalhos de Teclado

- `Ctrl+O`: Abrir arquivo
- `Ctrl+S`: Salvar alterações
- `F5`: Recarregar arquivo

## 📦 Módulos

### `data_parser.py`
Responsável por:
- Carregar e parsear arquivos XML
- Extrair materiais, programas (chapas) e peças
- Organizar dados em estrutura hierárquica
- Fornecer resumos e estatísticas

### `tree_view_manager.py`
Responsável por:
- Criar e gerenciar a TreeView
- Agrupar materiais por cor
- Exibir hierarquia: Cor → Material → Chapa → Peças
- Notificar seleções via callback

### `canvas_renderer.py`
Responsável por:
- Renderizar a chapa e uma visualização das peças no Canvas
- Zoom / pan / reset de visualização
- Realçar peça selecionada no canvas (clique)

### `edit_controls.py`
Responsável por:
- Criar painel de edição lateral
- Gerar controles de entrada para dimensões
- Sincronizar alterações com XML (cortes P/Y + fields)
- Organizar peças em cards editáveis
- Atualizar coordenadas reais dos cortes em tempo real

### `editor-plano-corte.py`
Aplicação principal que:
- Inicializa a interface gráfica
- Coordena todos os módulos
- Gerencia estado do aplicativo
- Processa comandos de arquivo (abrir, salvar, recarregar)

## 🎨 Tema Visual

- **Fundo escuro**: `#1a1a1a`, `#2d2d2d`, `#3d3d3d`
- **Accent**: `#00bcd4` (cyan)
- **Sucesso**: `#4caf50` (verde)
- **Erro**: `#f44336` (vermelho)
- **Texto**: `#ffffff`, `#b0b0b0`

## 🔧 Requisitos

- Python 3.7+
- tkinter (incluído no Python padrão)
- Sem dependências externas!

## 📝 Formato de Arquivo

Os arquivos `.cutplanning` são XMLs com a seguinte estrutura:

```xml
<com.geeksystem.cutplanning.cutplan>
  <com.geeksystem.cutplanning.cutplan.material>
    <com.geeksystem.cutplanning.cutplan.material.program>
      <...program.cut>
        <...cut.data>
          <...data.field name="185" value="DESCRIÇÃO"/>
          <...data.field name="187" value="LARGURA"/>
          <...data.field name="189" value="ALTURA"/>
        </...cut.data>
      </...program.cut>
    </...program>
  </...material>
</com.geeksystem.cutplanning.cutplan>
```

## 🐛 Troubleshooting

### Erro ao importar módulos
Certifique-se de que todos os arquivos `.py` estão na mesma pasta:
- `editor-plano-corte.py`
- `data_parser.py`
- `tree_view_manager.py`
- `edit_controls.py`

### Arquivo não carrega
Verifique se o arquivo é um XML válido com a estrutura esperada.

### Edições não salvam
Verifique no terminal se aparecem as mensagens:
- `✅ Largura do corte P atualizada: X → Y`
- `✅ Altura do corte Y atualizada: X → Y`

Se não aparecerem, pode haver problema na estrutura do XML.

### Interface não aparece
Certifique-se de que o Python tem suporte a tkinter:
```powershell
python -m tkinter
```

## 📈 Melhorias Futuras

- [ ] Adicionar/remover peças
- [ ] Adicionar/remover chapas
- [ ] Drag-and-drop de peças no canvas
- [ ] Otimização automática de corte
- [ ] Exportar para PDF/Imagem
- [ ] Histórico de alterações (Undo/Redo)
- [ ] Temas personalizáveis

## 👨‍💻 Desenvolvimento

O código foi estruturado de forma modular para facilitar manutenção e expansão:

1. **Separação de responsabilidades**: Cada módulo tem uma função específica
2. **Baixo acoplamento**: Módulos comunicam via callbacks
3. **Alta coesão**: Funcionalidades relacionadas agrupadas
4. **Código limpo**: Comentários e documentação

## 📄 Licença

Uso interno - Titanium

---

**Desenvolvido para otimização de processos de corte industrial**

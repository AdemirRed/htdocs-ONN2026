# Estrutura Modular do Projeto
# Editor de Plano de Corte - Titanium

## 📊 Estatísticas dos Arquivos

| Arquivo | Linhas | Tamanho | Responsabilidade |
|---------|--------|---------|------------------|
| **editor-plano-corte.py** | 403 | 15 KB | Aplicação principal, coordenação |
| **data_parser.py** | 177 | 7 KB | Parser XML, extração de dados |
| **tree_view_manager.py** | 209 | 8 KB | Árvore hierárquica de materiais |
| **canvas_renderer.py** | 320 | 11 KB | Renderização visual Canvas |
| **edit_controls.py** | 349 | 12 KB | Painel de edição lateral |
| **TOTAL** | **1,458 linhas** | **53 KB** | Sistema completo modular |

## 🏗️ Arquitetura do Sistema

```
┌─────────────────────────────────────────────────────────────────┐
│                   EDITOR-PLANO-CORTE.PY                         │
│                   (Aplicação Principal)                          │
│                                                                   │
│  • Gerencia estado global (xml_tree, xml_root)                  │
│  • Coordena módulos via callbacks                               │
│  • Processa comandos (abrir, salvar, recarregar)                │
│  • Configura interface principal                                │
└─────────────────────────────────────────────────────────────────┘
                          ▼  ▼  ▼  ▼
        ┌─────────────────┬────┬────┬─────────────────┐
        │                 │    │    │                 │
        ▼                 ▼    ▼    ▼                 ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│ DATA_PARSER  │  │ TREE_VIEW_   │  │ CANVAS_      │  │ EDIT_        │
│              │  │ MANAGER      │  │ RENDERER     │  │ CONTROLS     │
│ • Parse XML  │  │              │  │              │  │              │
│ • Extrair    │→│• TreeView    │→│• Visual      │  │• Formulário  │
│   materiais  │  │• Hierarquia  │  │  Canvas      │←│  edição      │
│ • Organizar  │  │• Agrupamento │  │• Zoom/Pan    │  │• Validação   │
│   dados      │  │• Seleção     │  │• Desenho     │  │• Sync XML    │
└──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘
```

## 🔄 Fluxo de Dados

### 1. Carregamento de Arquivo

```
Usuário clica "Abrir"
    ↓
editor-plano-corte.py::selecionar_arquivo()
    ↓
data_parser.py::load_file(file_path)
    ↓
data_parser.py::parse_all_materials()
    ↓ [materials_data]
tree_view_manager.py::populate_tree(materials_data)
    ↓
Interface atualizada com todos os materiais/chapas
```

### 2. Seleção de Chapa

```
Usuário clica em chapa na árvore
    ↓
tree_view_manager.py::_on_tree_select()
    ↓ [material_data, program_data]
editor-plano-corte.py::on_sheet_selected()
    ↓ ┌────────────────────────────────────┐
    ├─→ canvas_renderer.py::render_cutting_plan()
    │    └─> Desenha chapa e peças
    │
    └─→ edit_controls.py::load_sheet_for_edit()
         └─> Cria formulário de edição
```

### 3. Edição de Dados

```
Usuário edita campo no painel de edição
    ↓
edit_controls.py::update_value() (via KeyRelease)
    ↓
Atualiza xml_root (element.set())
    ↓
Usuário clica "Salvar"
    ↓
editor-plano-corte.py::salvar_arquivo()
    ↓
XML gravado no disco com formatação preservada
```

## 📐 Estrutura de Dados

### Retorno de `parse_all_materials()`:

```python
[
    {
        'material': <Element>,          # Referência XML
        'description': 'BRANCO LISO',
        'color': 'BRANCO',
        'code': '001',
        'width': '1840',
        'length': '2740',
        'thickness': '18',
        'programs': [
            {
                'program': <Element>,   # Referência XML
                'number': '1',
                'quantity': '5',
                'width': '1840',
                'length': '2740',
                'cuts': [...],
                'pieces': [
                    {
                        'cut_id': '2',
                        'description': 'Porta Armário',
                        'width': '440',
                        'height': '1500',
                        'environment': 'Cozinha',
                        'quantity': '2',
                        'cut': <Element>,
                        'fields': {
                            '185': {'element': <Element>, 'value': 'Porta Armário'},
                            '187': {'element': <Element>, 'value': '440'},
                            '189': {'element': <Element>, 'value': '1500'}
                        }
                    }
                ]
            }
        ]
    }
]
```

## 🎯 Callbacks e Comunicação

### TreeView → App Principal:
```python
# tree_view_manager.py
self.on_selection = on_selection_callback

# Chamado quando chapa selecionada
self.on_selection(material_data, program_data)
```

### App Principal → Canvas:
```python
self.canvas_renderer.render_cutting_plan(material_data, program_data)
```

### App Principal → Edit Panel:
```python
self.edit_panel.load_sheet_for_edit(material_data, program_data)
```

### Edit Panel → App Principal:
```python
# Callback para salvar
on_save_callback()  # → editor-plano-corte.py::salvar_arquivo()
```

## 🎨 Componentes Visuais

### Layout Principal (Grid 3 colunas):
```
┌────────────────────────────────────────────────────────────────┐
│ TOOLBAR: [Abrir] [Salvar] [Relógio]                           │
├────────────────────────────────────────────────────────────────┤
│ INFO: 📄 Arquivo atual                                         │
├────────────┬──────────────────────────────┬────────────────────┤
│ COLUNA 1   │ COLUNA 2                     │ COLUNA 3           │
│ (25%)      │ (50%)                        │ (25%)              │
│            │                              │                    │
│ TreeView   │ Canvas                       │ Edit Panel         │
│ ├─ 🎨 Cor  │ ┌──────────────────────────┐ │ ┌────────────────┐│
│ │  ├─ 📦   │ │                          │ │ │ DIMENSÕES      ││
│ │  │  ├─📄 │ │   [Plano de Corte        │ │ │ Largura: ____  ││
│ │  │  ├─📄 │ │    Renderizado]          │ │ │ Altura: _____  ││
│ │  │  └─📄 │ │                          │ │ │                ││
│ │  └─ 📦   │ │   🔍+ 🔍- ⟲ Reset      │ │ │ PEÇAS          ││
│ └─ 🎨 Cor  │ │                          │ │ │ ✦ Peça 1       ││
│            │ └──────────────────────────┘ │ │   Largura: ___ ││
│            │                              │ │   Altura: ____  ││
│            │                              │ └────────────────┘│
├────────────┴──────────────────────────────┴────────────────────┤
│ STATUS BAR: ✅ Mensagens do sistema                           │
└────────────────────────────────────────────────────────────────┘
```

## 🔑 Principais Classes e Métodos

### **EditorPlanoCorte** (editor-plano-corte.py)
- `__init__()`: Inicializa aplicação
- `criar_interface()`: Monta layout 3 colunas
- `carregar_arquivo()`: Carrega e parseia arquivo
- `salvar_arquivo()`: Grava alterações em XML
- `on_sheet_selected()`: Callback de seleção

### **CutPlanningParser** (data_parser.py)
- `load_file()`: Carrega XML
- `parse_all_materials()`: Extrai todos os dados
- `_parse_program_cuts()`: Extrai peças de um programa

### **TreeViewManager** (tree_view_manager.py)
- `create_tree_view()`: Cria widget TreeView
- `populate_tree()`: Popula com materiais
- `_on_tree_select()`: Callback de seleção

### **CanvasRenderer** (canvas_renderer.py)
- `create_canvas()`: Cria Canvas e controles
- `render_cutting_plan()`: Desenha plano de corte
- `_render_pieces()`: Desenha peças
- `zoom_in()`, `zoom_out()`, `reset_view()`: Controles

### **EditControlsPanel** (edit_controls.py)
- `create_panel()`: Cria painel com scroll
- `load_sheet_for_edit()`: Carrega dados para edição
- `_create_sheet_section()`: Seção de chapa
- `_create_pieces_section()`: Seção de peças

## ✨ Vantagens da Arquitetura Modular

1. **Manutenibilidade**: Fácil localizar e corrigir bugs
2. **Escalabilidade**: Adicionar features sem quebrar código existente
3. **Testabilidade**: Cada módulo pode ser testado isoladamente
4. **Legibilidade**: Código organizado e documentado
5. **Reutilização**: Módulos podem ser usados em outros projetos
6. **Colaboração**: Múltiplos desenvolvedores podem trabalhar em módulos diferentes

## 🚀 Próximos Passos

1. Adicionar testes unitários para cada módulo
2. Implementar sistema de plugins
3. Criar módulo de otimização de corte
4. Adicionar exportação para PDF/SVG
5. Implementar histórico de alterações (Undo/Redo)

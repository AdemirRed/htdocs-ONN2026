"""
Gerenciador da TreeView para exibição hierárquica de materiais e chapas
"""
import tkinter as tk
from tkinter import ttk


class TreeViewManager:
    """Gerencia a árvore de materiais, chapas e peças"""
    
    def __init__(self, parent, theme_colors, on_selection_callback):
        """
        Args:
            parent: Widget pai
            theme_colors: Dict com cores do tema
            on_selection_callback: Função chamada quando um item é selecionado
        """
        self.parent = parent
        self.colors = theme_colors
        self.on_selection = on_selection_callback
        self.materials_data = []
        
        # Mapear IDs de nós da árvore para dados
        self.node_data = {}
        
        self.create_tree_view()
    
    def create_tree_view(self):
        """Cria o componente TreeView"""
        # Frame container
        self.tree_frame = tk.Frame(self.parent, bg=self.colors['bg_medium'])
        self.tree_frame.pack(fill=tk.BOTH, expand=True)
        
        # Header
        header = tk.Frame(self.tree_frame, bg=self.colors['accent'])
        header.pack(fill=tk.X)
        
        tk.Label(
            header,
            text="📋 MATERIAIS E CHAPAS",
            font=("Segoe UI", 11, "bold"),
            bg=self.colors['accent'],
            fg=self.colors['text_white'],
            pady=10,
            padx=10
        ).pack(side=tk.LEFT)
        
        # TreeView com scrollbar
        tree_container = tk.Frame(self.tree_frame, bg=self.colors['bg_dark'])
        tree_container.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        # Scrollbar
        scrollbar = ttk.Scrollbar(tree_container, orient="vertical")
        scrollbar.pack(side=tk.RIGHT, fill=tk.Y)
        
        # TreeView
        self.tree = ttk.Treeview(
            tree_container,
            yscrollcommand=scrollbar.set,
            selectmode='browse'
        )
        self.tree.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scrollbar.config(command=self.tree.yview)
        
        # Configurar colunas
        self.tree['columns'] = ('quantidade', 'cortados')
        self.tree.column('#0', width=300, minwidth=200)
        self.tree.column('quantidade', width=100, minwidth=80, anchor='center')
        self.tree.column('cortados', width=100, minwidth=80, anchor='center')
        
        self.tree.heading('#0', text='Nome', anchor='w')
        self.tree.heading('quantidade', text='Quantidade', anchor='center')
        self.tree.heading('cortados', text='Cortados', anchor='center')
        
        # Estilo do TreeView
        style = ttk.Style()
        style.configure(
            "Treeview",
            background=self.colors['bg_light'],
            foreground=self.colors['text_white'],
            fieldbackground=self.colors['bg_light'],
            borderwidth=0,
            font=('Segoe UI', 9)
        )
        style.configure(
            "Treeview.Heading",
            background=self.colors['bg_medium'],
            foreground=self.colors['text_white'],
            borderwidth=1,
            font=('Segoe UI', 10, 'bold')
        )
        style.map('Treeview', background=[('selected', self.colors['accent'])])
        
        # Bind de seleção
        self.tree.bind('<<TreeviewSelect>>', self._on_tree_select)
        
        return self.tree_frame
    
    def populate_tree(self, materials_data):
        """
        Popula a árvore com dados de materiais
        Args:
            materials_data: Lista retornada por CutPlanningParser.parse_all_materials()
        """
        self.materials_data = materials_data
        
        # Limpar árvore e dados
        for item in self.tree.get_children():
            self.tree.delete(item)
        self.node_data = {}
        
        # Agrupar materiais por cor
        materials_by_color = {}
        for mat_data in materials_data:
            color = mat_data['color'] or 'SEM COR'
            if color not in materials_by_color:
                materials_by_color[color] = []
            materials_by_color[color].append(mat_data)
        
        # Inserir na árvore
        for color, materials in sorted(materials_by_color.items()):
            # Nó da cor (se houver múltiplos materiais da mesma cor)
            if len(materials) > 1:
                color_node = self.tree.insert(
                    '',
                    'end',
                    text=f"🎨 {color}",
                    values=('', ''),
                    tags=('color',)
                )
            else:
                color_node = ''
            
            for mat_data in materials:
                # Calcular totais
                total_sheets = len(mat_data['programs'])
                total_pieces = sum(len(p['pieces']) for p in mat_data['programs'])
                
                material_text = f"📦 {mat_data['description']} ({mat_data['width']}x{mat_data['length']}mm)"
                
                # Nó do material
                if color_node:
                    material_node = self.tree.insert(
                        color_node,
                        'end',
                        text=material_text,
                        values=(f"{total_sheets} chapas", f"{total_pieces} peças"),
                        tags=('material',)
                    )
                else:
                    material_node = self.tree.insert(
                        '',
                        'end',
                        text=material_text,
                        values=(f"{total_sheets} chapas", f"{total_pieces} peças"),
                        tags=('material',)
                    )
                
                # Armazenar dados do material no dicionário
                self.node_data[material_node] = {'type': 'material', 'data': mat_data}
                
                # Adicionar programas (chapas)
                for prog_data in mat_data['programs']:
                    sheet_number = prog_data['number']
                    quantity = prog_data['quantity']
                    pieces_count = len(prog_data['pieces'])
                    
                    sheet_text = f"📄 Chapa {sheet_number}"
                    
                    sheet_node = self.tree.insert(
                        material_node,
                        'end',
                        text=sheet_text,
                        values=(quantity, f"{pieces_count} peças"),
                        tags=('sheet',)
                    )
                    
                    # Armazenar dados da chapa no dicionário
                    self.node_data[sheet_node] = {
                        'type': 'sheet',
                        'material': mat_data,
                        'program': prog_data
                    }
                    
                    # Adicionar peças como filhos
                    for piece in prog_data['pieces']:
                        piece_text = f"  ✦ {piece['description']} ({piece['width']}x{piece['height']}mm)"
                        self.tree.insert(
                            sheet_node,
                            'end',
                            text=piece_text,
                            values=(piece['quantity'], ''),
                            tags=('piece',)
                        )
        
        # Expandir primeiro nível
        for item in self.tree.get_children():
            self.tree.item(item, open=True)
    
    def _on_tree_select(self, event):
        """Callback quando um item é selecionado na árvore"""
        selection = self.tree.selection()
        if not selection:
            return
        
        item = selection[0]
        tags = self.tree.item(item, 'tags')
        
        # Só processa seleção de chapas
        if 'sheet' in tags:
            # Recuperar dados do dicionário interno
            if item in self.node_data:
                node_info = self.node_data[item]
                if node_info['type'] == 'sheet':
                    self.on_selection(node_info['material'], node_info['program'])
    
    def get_frame(self):
        """Retorna o frame da TreeView"""
        return self.tree_frame

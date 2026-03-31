"""
Painel de controles de edição para chapas e peças
"""
import tkinter as tk
from tkinter import ttk, messagebox
import xml.etree.ElementTree as ET


class EditControlsPanel:
    """Painel lateral com controles para edição de dimensões"""
    
    def __init__(self, parent, theme_colors, xml_tree, on_save_callback, editor_app=None):
        """
        Args:
            parent: Widget pai
            theme_colors: Dict com cores do tema
            xml_tree: Árvore XML do arquivo
            on_save_callback: Função chamada ao salvar alterações
            editor_app: Referência ao aplicativo principal para refresh
        """
        self.parent = parent
        self.colors = theme_colors
        self.xml_tree = xml_tree
        self.on_save = on_save_callback
        self.editor_app = editor_app
        
        self.current_material = None
        self.current_program = None
        self.entry_widgets = {}

        # Debounce de refresh do canvas
        self._canvas_refresh_after_id = None
        
        self.create_panel()
    
    def create_panel(self):
        """Cria o painel de edição"""
        # Frame principal com scroll
        self.panel_frame = tk.Frame(self.parent, bg=self.colors['bg_medium'])
        self.panel_frame.pack(fill=tk.BOTH, expand=True)
        
        # Header
        header = tk.Frame(self.panel_frame, bg=self.colors['accent'])
        header.pack(fill=tk.X)
        
        tk.Label(
            header,
            text="✏️ EDIÇÃO",
            font=("Segoe UI", 11, "bold"),
            bg=self.colors['accent'],
            fg=self.colors['text_white'],
            pady=10,
            padx=10
        ).pack()
        
        # Canvas com scrollbar para conteúdo
        canvas = tk.Canvas(self.panel_frame, bg=self.colors['bg_medium'], highlightthickness=0)
        scrollbar = ttk.Scrollbar(self.panel_frame, orient="vertical", command=canvas.yview)
        
        self.scrollable_content = tk.Frame(canvas, bg=self.colors['bg_medium'])
        self.scrollable_content.bind(
            "<Configure>",
            lambda e: canvas.configure(scrollregion=canvas.bbox("all"))
        )
        
        canvas.create_window((0, 0), window=self.scrollable_content, anchor="nw")
        canvas.configure(yscrollcommand=scrollbar.set)
        
        canvas.pack(side="left", fill="both", expand=True, padx=5, pady=5)
        scrollbar.pack(side="right", fill="y")
        
        # Mensagem inicial
        self.empty_message = tk.Label(
            self.scrollable_content,
            text="Selecione uma chapa\npara editar",
            font=("Segoe UI", 10),
            bg=self.colors['bg_medium'],
            fg=self.colors['text_gray'],
            pady=30
        )
        self.empty_message.pack()
        
        # Botão salvar (inicialmente oculto)
        self.save_button = tk.Button(
            self.panel_frame,
            text="💾 SALVAR ALTERAÇÕES",
            font=("Segoe UI", 11, "bold"),
            bg=self.colors['success'],
            fg=self.colors['text_white'],
            command=self.on_save,
            relief=tk.FLAT,
            padx=20,
            pady=10,
            cursor="hand2"
        )
        
        return self.panel_frame

    def _schedule_canvas_refresh(self, delay_ms: int = 150):
        """Agenda um refresh do canvas (debounced) quando houver editor_app."""
        if not self.editor_app:
            return
        if not hasattr(self.editor_app, 'canvas_renderer'):
            return
        if getattr(self.editor_app, 'canvas_renderer', None) is None:
            return

        if self._canvas_refresh_after_id is not None:
            try:
                self.editor_app.root.after_cancel(self._canvas_refresh_after_id)
            except Exception:
                pass
            self._canvas_refresh_after_id = None

        def _do_refresh():
            self._canvas_refresh_after_id = None
            material = getattr(self.editor_app, 'current_material', None)
            program = getattr(self.editor_app, 'current_program', None)
            if material is None or program is None:
                return
            try:
                self.editor_app.canvas_renderer.render_cutting_plan(material, program)
            except Exception:
                # Refresh é best-effort; não quebra edição
                pass

        try:
            self._canvas_refresh_after_id = self.editor_app.root.after(delay_ms, _do_refresh)
        except Exception:
            self._canvas_refresh_after_id = None
    
    def load_sheet_for_edit(self, material_data, program_data):
        """
        Carrega uma chapa para edição
        Args:
            material_data: Dados do material
            program_data: Dados do programa/chapa
        """
        self.current_material = material_data
        self.current_program = program_data
        
        # Limpar conteúdo
        for widget in self.scrollable_content.winfo_children():
            widget.destroy()
        
        self.entry_widgets = {}
        
        # Seção: Informações da Chapa
        self._create_sheet_section()
        
        # Seção: Peças
        self._create_pieces_section()
        
        # Mostrar botão salvar
        self.save_button.pack(fill=tk.X, padx=10, pady=10)
    
    def _create_sheet_section(self):
        """Cria seção de edição da chapa"""
        section = tk.Frame(self.scrollable_content, bg=self.colors['bg_light'], relief=tk.FLAT)
        section.pack(fill=tk.X, padx=10, pady=10)
        
        # Header
        tk.Label(
            section,
            text="📐 DIMENSÕES DA CHAPA",
            font=("Segoe UI", 10, "bold"),
            bg=self.colors['accent'],
            fg=self.colors['text_white'],
            pady=8,
            padx=10
        ).pack(fill=tk.X)
        
        content = tk.Frame(section, bg=self.colors['bg_light'], padx=10, pady=10)
        content.pack(fill=tk.X)
        
        # Largura
        self._add_field(content, "Largura (mm):", "width", 0)
        
        # Comprimento
        self._add_field(content, "Comprimento (mm):", "length", 1)
        
        # Espessura (read-only, do material)
        tk.Label(
            content,
            text="Espessura (mm):",
            font=("Segoe UI", 9, "bold"),
            bg=self.colors['bg_light'],
            fg=self.colors['text_gray']
        ).grid(row=2, column=0, sticky="w", pady=5)
        
        tk.Label(
            content,
            text=self.current_material.get('thickness', '0'),
            font=("Consolas", 9),
            bg=self.colors['bg_medium'],
            fg=self.colors['text_white'],
            padx=5,
            pady=3
        ).grid(row=2, column=1, sticky="ew", pady=5)
        
        content.columnconfigure(1, weight=1)
    
    def _create_pieces_section(self):
        """Cria seção de edição das peças"""
        if not self.current_program['pieces']:
            return
        
        section = tk.Frame(self.scrollable_content, bg=self.colors['bg_medium'])
        section.pack(fill=tk.BOTH, expand=True, padx=10, pady=5)
        
        # Header
        tk.Label(
            section,
            text=f"🔨 PEÇAS ({len(self.current_program['pieces'])})",
            font=("Segoe UI", 10, "bold"),
            bg=self.colors['accent'],
            fg=self.colors['text_white'],
            pady=8,
            padx=10
        ).pack(fill=tk.X)
        
        # Lista de peças
        for idx, piece in enumerate(self.current_program['pieces']):
            self._create_piece_card(section, piece, idx)
    
    def _create_piece_card(self, parent, piece, index):
        """Cria card de uma peça"""
        card = tk.Frame(parent, bg=self.colors['bg_light'], relief=tk.RAISED, bd=1)
        card.pack(fill=tk.X, padx=5, pady=5)
        
        # Header
        header = tk.Frame(card, bg="#00838f")
        header.pack(fill=tk.X)
        
        tk.Label(
            header,
            text=f"✦ Peça #{index + 1}",
            font=("Segoe UI", 9, "bold"),
            bg="#00838f",
            fg=self.colors['text_white'],
            padx=10,
            pady=5
        ).pack(side=tk.LEFT)

        tk.Button(
            header,
            text="🧾 Etiqueta...",
            font=("Segoe UI", 8, "bold"),
            bg=self.colors['bg_medium'],
            fg=self.colors['text_white'],
            activebackground=self.colors['accent_hover'],
            activeforeground=self.colors['text_white'],
            relief=tk.FLAT,
            padx=10,
            pady=3,
            cursor="hand2",
            command=lambda p=piece: self._open_label_editor(p),
        ).pack(side=tk.RIGHT, padx=6, pady=2)
        
        qty_label = tk.Label(
            header,
            text=f"Qtd: {piece['quantity']}",
            font=("Segoe UI", 8),
            bg="#006064",
            fg=self.colors['text_white'],
            padx=8,
            pady=5
        )
        qty_label.pack(side=tk.RIGHT)
        
        # Conteúdo
        content = tk.Frame(card, bg=self.colors['bg_light'], padx=8, pady=8)
        content.pack(fill=tk.X)
        
        # Descrição
        tk.Label(
            content,
            text=piece['description'],
            font=("Segoe UI", 9, "italic"),
            bg=self.colors['bg_light'],
            fg=self.colors['text_white'],
            wraplength=200
        ).pack(anchor="w", pady=(0, 5))
        
        # Dimensões
        dims_frame = tk.Frame(content, bg=self.colors['bg_light'])
        dims_frame.pack(fill=tk.X)
        
        # Largura
        tk.Label(
            dims_frame,
            text="Largura:",
            font=("Segoe UI", 8, "bold"),
            bg=self.colors['bg_light'],
            fg=self.colors['text_gray']
        ).grid(row=0, column=0, sticky="w", pady=2)
        
        width_entry = tk.Entry(
            dims_frame,
            font=("Consolas", 8),
            width=12,
            bg=self.colors['bg_medium'],
            fg=self.colors['text_white'],
            insertbackground=self.colors['accent'],
            relief=tk.FLAT
        )
        width_entry.insert(0, piece['width'])
        width_entry.grid(row=0, column=1, sticky="ew", pady=2, padx=5)
        
        # Altura
        tk.Label(
            dims_frame,
            text="Altura:",
            font=("Segoe UI", 8, "bold"),
            bg=self.colors['bg_light'],
            fg=self.colors['text_gray']
        ).grid(row=1, column=0, sticky="w", pady=2)
        
        height_entry = tk.Entry(
            dims_frame,
            font=("Consolas", 8),
            width=12,
            bg=self.colors['bg_medium'],
            fg=self.colors['text_white'],
            insertbackground=self.colors['accent'],
            relief=tk.FLAT
        )
        height_entry.insert(0, piece['height'])
        height_entry.grid(row=1, column=1, sticky="ew", pady=2, padx=5)

        # Quantidade
        tk.Label(
            dims_frame,
            text="Quantidade:",
            font=("Segoe UI", 8, "bold"),
            bg=self.colors['bg_light'],
            fg=self.colors['text_gray']
        ).grid(row=2, column=0, sticky="w", pady=2)

        qty_entry = tk.Entry(
            dims_frame,
            font=("Consolas", 8),
            width=12,
            bg=self.colors['bg_medium'],
            fg=self.colors['text_white'],
            insertbackground=self.colors['accent'],
            relief=tk.FLAT
        )
        qty_entry.insert(0, piece.get('quantity', '1'))
        qty_entry.grid(row=2, column=1, sticky="ew", pady=2, padx=5)
        
        dims_frame.columnconfigure(1, weight=1)
        
        # Bind para atualizar XML
        def update_width(event, p=piece, w=width_entry):
            value = w.get()
            if not value:
                return
            try:
                # Validar que é um número
                float(value)

                def _cut_has_data(elem):
                    if elem is None:
                        return False
                    for child in list(elem):
                        tag = getattr(child, 'tag', '')
                        local = str(tag).split('}')[-1].split('.')[-1].lower()
                        if local == 'data':
                            return True
                    return False
                
                # Atualizar field 187 e 190 (labels de largura)
                if '187' in p['fields']:
                    p['fields']['187']['element'].set('value', value)
                    p['fields']['187']['value'] = value
                if '190' in p['fields']:
                    p['fields']['190']['element'].set('value', value)
                    p['fields']['190']['value'] = value

                p['width'] = value
                
                # Atualizar corte correspondente à largura real (pode ser X ou P dependendo do programa)
                if 'cut_element' in p and p['cut_element'] is not None:
                    program_elem = self.current_program['program']
                    cuts = list(program_elem)

                    try:
                        current_cut = p['cut_element']
                        current_type = current_cut.get('type')

                        # Caso comum: a peça está no cut X e o próprio X carrega a largura
                        if current_type == 'X':
                            old_val = current_cut.get('value')
                            current_cut.set('value', value)
                            print(f"✅ Largura do corte X atualizada: {old_val} → {value}")
                        else:
                            cut_index = cuts.index(current_cut)
                            updated = False

                            # Tenta P antes do cut atual (sem atravessar para a peça anterior)
                            for i in range(cut_index - 1, -1, -1):
                                if _cut_has_data(cuts[i]):
                                    break
                                cut_type = cuts[i].get('type')
                                if cut_type == 'P':
                                    old_val = cuts[i].get('value')
                                    cuts[i].set('value', value)
                                    print(f"✅ Largura do corte P atualizada: {old_val} → {value}")
                                    updated = True
                                    break

                            # Fallback: alguns layouts usam X como largura mesmo quando o data não está em X
                            if not updated:
                                for i in range(cut_index - 1, -1, -1):
                                    if _cut_has_data(cuts[i]):
                                        break
                                    cut_type = cuts[i].get('type')
                                    if cut_type == 'X':
                                        old_val = cuts[i].get('value')
                                        cuts[i].set('value', value)
                                        print(f"✅ Largura do corte X atualizada (fallback): {old_val} → {value}")
                                        updated = True
                                        break

                    except (ValueError, AttributeError) as e:
                        print(f"⚠️ Erro ao atualizar largura no cut: {e}")

                self._schedule_canvas_refresh()
            except ValueError:
                pass
        
        def update_height(event, p=piece, h=height_entry):
            value = h.get()
            if not value:
                return
            try:
                # Validar que é um número
                float(value)

                def _cut_has_data(elem):
                    if elem is None:
                        return False
                    for child in list(elem):
                        tag = getattr(child, 'tag', '')
                        local = str(tag).split('}')[-1].split('.')[-1].lower()
                        if local == 'data':
                            return True
                    return False
                
                # Atualizar field 189 e 191 (labels de altura)
                if '189' in p['fields']:
                    p['fields']['189']['element'].set('value', value)
                    p['fields']['189']['value'] = value
                if '191' in p['fields']:
                    p['fields']['191']['element'].set('value', value)
                    p['fields']['191']['value'] = value

                p['height'] = value
                
                # Atualizar corte correspondente à altura real (pode ser Y no próprio cut atual)
                if 'cut_element' in p and p['cut_element'] is not None:
                    program_elem = self.current_program['program']
                    cuts = list(program_elem)

                    try:
                        current_cut = p['cut_element']
                        current_type = current_cut.get('type')

                        # Caso comum: a peça está no cut Y e o próprio Y carrega a altura
                        if current_type == 'Y':
                            old_val = current_cut.get('value')
                            current_cut.set('value', value)
                            print(f"✅ Altura do corte Y atualizada: {old_val} → {value}")
                        else:
                            cut_index = cuts.index(current_cut)
                            # Procurar Y antes do cut atual (sem atravessar para a peça anterior)
                            for i in range(cut_index - 1, -1, -1):
                                if _cut_has_data(cuts[i]):
                                    break
                                cut_type = cuts[i].get('type')
                                if cut_type == 'Y':
                                    old_val = cuts[i].get('value')
                                    cuts[i].set('value', value)
                                    print(f"✅ Altura do corte Y atualizada: {old_val} → {value}")
                                    break
                    except (ValueError, AttributeError) as e:
                        print(f"⚠️ Erro ao atualizar altura no cut: {e}")

                self._schedule_canvas_refresh()
            except ValueError:
                pass

        def update_quantity(event, p=piece, q=qty_entry, qlabel=qty_label):
            value = q.get()
            if not value:
                return
            try:
                qty = int(value)
                if qty < 1:
                    return

                if p.get('data_element') is not None:
                    p['data_element'].set('quantity', str(qty))
                p['quantity'] = str(qty)
                qlabel.config(text=f"Qtd: {qty}")

                self._schedule_canvas_refresh()
            except ValueError:
                pass
        
        width_entry.bind("<KeyRelease>", update_width)
        height_entry.bind("<KeyRelease>", update_height)
        qty_entry.bind("<KeyRelease>", update_quantity)

    def _get_label_template_fields(self, piece):
        """Retorna dict de fields do template associado à peça (se existir)."""
        if not self.editor_app or not hasattr(self.editor_app, 'parser'):
            return {}
        template_id = piece.get('template')
        if not template_id:
            return {}
        templates = getattr(self.editor_app.parser, 'label_templates', {})
        template = templates.get(str(template_id))
        if not template:
            return {}
        return template.get('fields', {})

    def _iter_piece_field_names(self, piece):
        """Lista ordenada de field names relevantes (template + existentes)."""
        names = set()
        for n in (piece.get('fields') or {}).keys():
            if n:
                names.add(str(n))
        for n in self._get_label_template_fields(piece).keys():
            if n:
                names.add(str(n))

        def sort_key(x):
            try:
                return (0, int(x))
            except ValueError:
                return (1, x)

        return sorted(names, key=sort_key)

    def _infer_field_tag(self, piece):
        """Infere a tag XML correta para criar um novo <...field/>."""
        for info in (piece.get('fields') or {}).values():
            elem = info.get('element')
            if elem is not None and getattr(elem, 'tag', None):
                return elem.tag

        data_elem = piece.get('data_element')
        if data_elem is not None and isinstance(getattr(data_elem, 'tag', None), str):
            tag = data_elem.tag
            if '.' in tag and not tag.endswith('.field'):
                return f"{tag}.field"

        return 'field'

    def _set_piece_field_value(self, piece, field_name, value):
        """Seta/cria um cut.data.field (name/value) na peça."""
        field_name = str(field_name)
        if 'fields' not in piece or piece['fields'] is None:
            piece['fields'] = {}

        # Atualiza se já existe
        if field_name in piece['fields'] and piece['fields'][field_name].get('element') is not None:
            elem = piece['fields'][field_name]['element']
            elem.set('value', value)
            piece['fields'][field_name]['value'] = value
        else:
            data_elem = piece.get('data_element')
            if data_elem is None:
                raise RuntimeError('Peça não possui data_element para criar fields')

            field_tag = self._infer_field_tag(piece)
            new_field_elem = ET.SubElement(data_elem, field_tag)
            new_field_elem.set('name', field_name)
            new_field_elem.set('value', value)
            piece['fields'][field_name] = {'element': new_field_elem, 'value': value}

        # Atualizar atalhos comuns em memória
        if field_name == '185':
            piece['description'] = value
        elif field_name == '193':
            piece['environment'] = value
        elif field_name == '187':
            piece['width'] = value
        elif field_name == '189':
            piece['height'] = value

    def _open_label_editor(self, piece):
        """Abre modal para editar campos da etiqueta por peça."""
        top = tk.Toplevel(self.parent.winfo_toplevel())
        top.title('Editar etiqueta da peça')
        top.configure(bg=self.colors['bg_dark'])
        top.geometry('760x720')

        header = tk.Frame(top, bg=self.colors['accent'])
        header.pack(fill=tk.X)
        tk.Label(
            header,
            text=f"🧾 Etiqueta - {piece.get('description', '')}",
            font=("Segoe UI", 11, "bold"),
            bg=self.colors['accent'],
            fg=self.colors['text_white'],
            padx=12,
            pady=10,
            anchor='w'
        ).pack(fill=tk.X)

        opts = tk.Frame(top, bg=self.colors['bg_medium'])
        opts.pack(fill=tk.X)

        show_fixed_var = tk.BooleanVar(value=False)
        show_non_text_var = tk.BooleanVar(value=False)

        tk.Checkbutton(
            opts,
            text='Mostrar campos fixos do template',
            variable=show_fixed_var,
            bg=self.colors['bg_medium'],
            fg=self.colors['text_white'],
            activebackground=self.colors['bg_medium'],
            activeforeground=self.colors['text_white'],
            selectcolor=self.colors['bg_light'],
            padx=12,
            pady=6,
        ).pack(side=tk.LEFT)
        tk.Checkbutton(
            opts,
            text='Mostrar campos não-texto',
            variable=show_non_text_var,
            bg=self.colors['bg_medium'],
            fg=self.colors['text_white'],
            activebackground=self.colors['bg_medium'],
            activeforeground=self.colors['text_white'],
            selectcolor=self.colors['bg_light'],
            padx=12,
            pady=6,
        ).pack(side=tk.LEFT)

        body = tk.Frame(top, bg=self.colors['bg_dark'])
        body.pack(fill=tk.BOTH, expand=True)

        canvas = tk.Canvas(body, bg=self.colors['bg_dark'], highlightthickness=0)
        scrollbar = ttk.Scrollbar(body, orient='vertical', command=canvas.yview)
        canvas.configure(yscrollcommand=scrollbar.set)

        scroll_frame = tk.Frame(canvas, bg=self.colors['bg_dark'])
        scroll_frame.bind('<Configure>', lambda e: canvas.configure(scrollregion=canvas.bbox('all')))
        canvas.create_window((0, 0), window=scroll_frame, anchor='nw')

        canvas.pack(side='left', fill='both', expand=True)
        scrollbar.pack(side='right', fill='y')

        entries = {}

        def is_fixed(meta):
            expr = (meta or {}).get('expression', '')
            return bool(expr) and not expr.startswith('$')

        def is_text(meta):
            t = (meta or {}).get('type', '')
            return (t or '').lower() == 'text'

        def friendly_label(name, meta):
            expr = (meta or {}).get('expression', '')
            if expr:
                return f"{name} ({expr})"
            return str(name)

        def rebuild_fields(*_):
            for w in scroll_frame.winfo_children():
                w.destroy()
            entries.clear()

            template_fields = self._get_label_template_fields(piece)
            shown = 0

            for name in self._iter_piece_field_names(piece):
                meta = template_fields.get(str(name))

                # Se tem meta, respeitar filtros
                if meta:
                    if (not show_non_text_var.get()) and (not is_text(meta)):
                        continue
                    if (not show_fixed_var.get()) and is_fixed(meta):
                        continue
                else:
                    # Sem meta: só mostrar se já existe na peça
                    if name not in (piece.get('fields') or {}):
                        continue

                current_value = ''
                if name in (piece.get('fields') or {}):
                    current_value = piece['fields'][name].get('value', '')

                line = tk.Frame(scroll_frame, bg=self.colors['bg_dark'])
                line.pack(fill=tk.X, padx=12, pady=6)

                tk.Label(
                    line,
                    text=friendly_label(name, meta),
                    font=("Segoe UI", 9, "bold"),
                    bg=self.colors['bg_dark'],
                    fg=self.colors['text_gray'],
                    width=28,
                    anchor='w'
                ).pack(side=tk.LEFT)

                ent = tk.Entry(
                    line,
                    font=("Consolas", 9),
                    bg=self.colors['bg_medium'],
                    fg=self.colors['text_white'],
                    insertbackground=self.colors['accent'],
                    relief=tk.FLAT,
                )
                ent.insert(0, current_value)
                ent.pack(side=tk.LEFT, fill=tk.X, expand=True)
                entries[name] = ent
                shown += 1

            if shown == 0:
                tk.Label(
                    scroll_frame,
                    text='Nenhum campo disponível para edição com os filtros atuais.',
                    font=("Segoe UI", 10),
                    bg=self.colors['bg_dark'],
                    fg=self.colors['text_gray'],
                    pady=20
                ).pack()

        show_fixed_var.trace_add('write', rebuild_fields)
        show_non_text_var.trace_add('write', rebuild_fields)
        rebuild_fields()

        footer = tk.Frame(top, bg=self.colors['bg_medium'])
        footer.pack(fill=tk.X)

        def apply_and_close():
            try:
                for name, widget in entries.items():
                    self._set_piece_field_value(piece, name, widget.get())
                top.destroy()
            except Exception as e:
                messagebox.showerror('Erro', f'Erro ao aplicar campos da etiqueta:\n{e}')

        tk.Button(
            footer,
            text='Cancelar',
            font=("Segoe UI", 10, "bold"),
            bg=self.colors['bg_light'],
            fg=self.colors['text_white'],
            relief=tk.FLAT,
            padx=16,
            pady=8,
            command=top.destroy,
            cursor='hand2'
        ).pack(side=tk.RIGHT, padx=10, pady=10)

        tk.Button(
            footer,
            text='Aplicar',
            font=("Segoe UI", 10, "bold"),
            bg=self.colors['success'],
            fg=self.colors['text_white'],
            relief=tk.FLAT,
            padx=16,
            pady=8,
            command=apply_and_close,
            cursor='hand2'
        ).pack(side=tk.RIGHT, padx=10, pady=10)
    
    def _add_field(self, parent, label, attr, row):
        """Adiciona um campo de edição"""
        tk.Label(
            parent,
            text=label,
            font=("Segoe UI", 9, "bold"),
            bg=self.colors['bg_light'],
            fg=self.colors['text_gray']
        ).grid(row=row, column=0, sticky="w", pady=5)
        
        entry = tk.Entry(
            parent,
            font=("Consolas", 9),
            width=20,
            bg=self.colors['bg_medium'],
            fg=self.colors['text_white'],
            insertbackground=self.colors['accent'],
            relief=tk.FLAT,
            borderwidth=2
        )
        
        # Obter valor atual
        if attr == "width":
            value = self.current_program.get('width', '0')
        elif attr == "length":
            value = self.current_program.get('length', '0')
        else:
            value = '0'
        
        entry.insert(0, value)
        entry.grid(row=row, column=1, sticky="ew", pady=5)
        
        # Bind para atualizar XML
        def update_value(event, attribute=attr, widget=entry):
            new_value = widget.get()
            if new_value:
                # Atualizar program
                if attribute == "width":
                    self.current_program['program'].set('width', new_value)
                    self.current_material['material'].set('width', new_value)
                elif attribute == "length":
                    self.current_program['program'].set('lenght', new_value)  # Note: typo in XML
                    self.current_material['material'].set('lenght', new_value)
        
        entry.bind("<KeyRelease>", update_value)
        self.entry_widgets[attr] = entry
    
    def get_frame(self):
        """Retorna o frame do painel"""
        return self.panel_frame

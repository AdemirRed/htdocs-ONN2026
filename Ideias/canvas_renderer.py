"""\
Renderizador de Canvas para visualização do plano de corte.

Observação:
- Os arquivos .cutplanning não trazem (neste projeto) coordenadas explícitas por peça.
- Para dar feedback visual imediato, este renderer faz um layout simples ("shelf packing")
  das peças dentro da chapa, respeitando largura/altura, e oferece zoom/pan.

Sem dependências externas.
"""

from __future__ import annotations

import tkinter as tk
from tkinter import ttk


class CanvasRenderer:
    def __init__(self, parent, theme_colors: dict):
        self.parent = parent
        self.colors = theme_colors

        self.canvas: tk.Canvas | None = None
        self._status_label: tk.Label | None = None

        # Transformação de viewport
        self._scale = 1.0
        self._min_scale = 0.2
        self._max_scale = 5.0
        self._pan_start = None  # type: tuple[int, int] | None

        # Mapeamento de itens -> dados
        self._piece_item_ids: list[int] = []
        self._piece_data_by_item: dict[int, dict] = {}
        self._selected_item: int | None = None

        self.create_canvas()

    def create_canvas(self):
        container = tk.Frame(self.parent, bg=self.colors["bg_medium"])
        container.pack(fill=tk.BOTH, expand=True)

        header = tk.Frame(container, bg=self.colors["accent"])
        header.pack(fill=tk.X)

        tk.Label(
            header,
            text="🧩 VISUALIZAÇÃO",
            font=("Segoe UI", 11, "bold"),
            bg=self.colors["accent"],
            fg=self.colors["text_white"],
            pady=10,
            padx=10,
        ).pack(side=tk.LEFT)

        controls = tk.Frame(header, bg=self.colors["accent"])
        controls.pack(side=tk.RIGHT, padx=8)

        def btn(text, cmd):
            return tk.Button(
                controls,
                text=text,
                font=("Segoe UI", 9, "bold"),
                bg=self.colors["bg_medium"],
                fg=self.colors["text_white"],
                activebackground=self.colors["accent_hover"],
                activeforeground=self.colors["text_white"],
                relief=tk.FLAT,
                padx=10,
                pady=4,
                cursor="hand2",
                command=cmd,
                borderwidth=0,
            )

        btn("🔍+", self.zoom_in).pack(side=tk.LEFT, padx=3)
        btn("🔍-", self.zoom_out).pack(side=tk.LEFT, padx=3)
        btn("⟲ Reset", self.reset_view).pack(side=tk.LEFT, padx=3)

        # Canvas
        self.canvas = tk.Canvas(
            container,
            bg=self.colors["bg_dark"],
            highlightthickness=0,
        )
        self.canvas.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)

        # Status
        self._status_label = tk.Label(
            container,
            text="Selecione uma chapa para visualizar",
            font=("Segoe UI", 9),
            bg=self.colors["bg_medium"],
            fg=self.colors["text_gray"],
            anchor="w",
            padx=10,
            pady=6,
        )
        self._status_label.pack(fill=tk.X)

        # Bindings: zoom/pan
        self.canvas.bind("<ButtonPress-1>", self._on_mouse_down)
        self.canvas.bind("<B1-Motion>", self._on_mouse_drag)
        self.canvas.bind("<ButtonRelease-1>", self._on_mouse_up)

        # Mouse wheel (Windows)
        self.canvas.bind("<MouseWheel>", self._on_mouse_wheel)

    def _set_status(self, text: str):
        if self._status_label is not None:
            self._status_label.config(text=text)

    def reset_view(self):
        if not self.canvas:
            return
        self._scale = 1.0
        self.canvas.delete("all")
        self._piece_item_ids.clear()
        self._piece_data_by_item.clear()
        self._selected_item = None
        self._set_status("Selecione uma chapa para visualizar")

    def zoom_in(self):
        self._apply_zoom(1.15)

    def zoom_out(self):
        self._apply_zoom(1 / 1.15)

    def _apply_zoom(self, factor: float):
        if not self.canvas:
            return
        new_scale = self._scale * factor
        if new_scale < self._min_scale:
            factor = self._min_scale / self._scale
            new_scale = self._min_scale
        if new_scale > self._max_scale:
            factor = self._max_scale / self._scale
            new_scale = self._max_scale

        self._scale = new_scale
        # Zoom ao redor do centro do canvas
        cx = self.canvas.winfo_width() / 2
        cy = self.canvas.winfo_height() / 2
        self.canvas.scale("all", cx, cy, factor, factor)

    def _on_mouse_wheel(self, event):
        # event.delta: 120/-120 no Windows
        if event.delta > 0:
            self.zoom_in()
        else:
            self.zoom_out()

    def _on_mouse_down(self, event):
        if not self.canvas:
            return
        self._pan_start = (event.x, event.y)

        # Seleção de peça ao clicar
        item = self.canvas.find_closest(event.x, event.y)
        if item:
            item_id = item[0]
            if item_id in self._piece_data_by_item:
                self._select_piece_item(item_id)

    def _on_mouse_drag(self, event):
        if not self.canvas or not self._pan_start:
            return
        x0, y0 = self._pan_start
        dx = event.x - x0
        dy = event.y - y0
        self.canvas.move("all", dx, dy)
        self._pan_start = (event.x, event.y)

    def _on_mouse_up(self, _event):
        self._pan_start = None

    def _select_piece_item(self, item_id: int):
        if not self.canvas:
            return
        # Unselect anterior
        if self._selected_item is not None and self._selected_item in self._piece_item_ids:
            self.canvas.itemconfig(self._selected_item, outline="#00acc1", width=1)

        self._selected_item = item_id
        self.canvas.itemconfig(item_id, outline=self.colors["warning"], width=2)

        data = self._piece_data_by_item.get(item_id, {})
        desc = data.get("description", "")
        w = data.get("width", "")
        h = data.get("height", "")
        self._set_status(f"Selecionado: {desc} ({w}x{h} mm)")

    @staticmethod
    def _to_float(value, default: float = 0.0) -> float:
        try:
            return float(str(value).replace(",", "."))
        except Exception:
            return default

    def _build_rectangles_from_cuts(self, program_data: dict, sheet_w: float, sheet_h: float) -> list[dict]:
        """Gera retângulos de peças a partir da sequência de cortes do XML."""
        cuts = program_data.get("cuts") or []
        if not cuts:
            return []

        strip_types = {"P"}
        column_types = {"X", "V"}
        horizontal_types = {"Y", "U", "S"}
        vertical_refiles = {"RP", "RX", "RV"}
        horizontal_refiles = {"RY", "RU", "RS"}

        rects: list[dict] = []

        x_cursor = 0.0
        y_cursor = 0.0
        strip_width: float | None = None
        strip_had_rows = False

        row_height: float | None = None
        row_x_cursor = 0.0
        row_has_x_cut = False
        row_label = ""

        def _get_label_from_cut(cut_info: dict) -> str:
            fields = cut_info.get("fields") or {}
            return fields.get("185", {}).get("value", "")

        def _add_rect(px: float, py: float, pw: float, ph: float, label: str = ""):
            if pw <= 0 or ph <= 0:
                return
            rects.append({
                "x": px,
                "y": py,
                "w": pw,
                "h": ph,
                "description": label or f"{pw:.1f} x {ph:.1f}",
            })

        def _finalize_row():
            nonlocal row_height, row_x_cursor, row_has_x_cut, y_cursor, row_label
            if row_height is None:
                return
            if not row_has_x_cut:
                width = strip_width if strip_width is not None else (sheet_w - x_cursor)
                _add_rect(x_cursor, y_cursor, width, row_height, row_label)
            y_cursor += row_height
            row_height = None
            row_x_cursor = 0.0
            row_has_x_cut = False
            row_label = ""

        def _finalize_strip():
            nonlocal strip_width, strip_had_rows, x_cursor, y_cursor
            _finalize_row()
            if strip_width is None:
                return
            if not strip_had_rows and y_cursor < sheet_h:
                _add_rect(x_cursor, y_cursor, strip_width, sheet_h - y_cursor, "")
            x_cursor += strip_width
            y_cursor = 0.0
            strip_width = None
            strip_had_rows = False

        for cut in cuts:
            raw_type = str(cut.get("type", "")).strip()
            if not raw_type:
                continue

            # Ignorar placeholders com asterisco (limitadores) nesta versão
            if raw_type.endswith("*"):
                continue

            ctype = raw_type.upper()
            value = self._to_float(cut.get("value", 0), 0.0)
            if value <= 0:
                continue

            # Refilos
            if ctype in vertical_refiles:
                if row_height is not None:
                    row_x_cursor += value
                else:
                    x_cursor += value
                continue
            if ctype in horizontal_refiles:
                y_cursor += value
                continue

            # Cortes verticais (largura)
            if ctype in strip_types:
                # Se já estamos dentro de uma altura (Y/U/S), um novo P começa nova tira
                if row_height is not None or strip_width is not None:
                    _finalize_strip()
                strip_width = value
                strip_had_rows = False
                continue

            if ctype in column_types:
                # Se já há uma altura ativa, X/V corta dentro da tira
                if row_height is not None:
                    _add_rect(x_cursor + row_x_cursor, y_cursor, value, row_height, row_label)
                    row_x_cursor += value
                    row_has_x_cut = True
                else:
                    # Sem altura ativa, trata como nova tira
                    if strip_width is not None:
                        _finalize_strip()
                    strip_width = value
                    strip_had_rows = False
                continue

            # Cortes horizontais (altura)
            if ctype in horizontal_types:
                _finalize_row()
                row_height = value
                row_x_cursor = 0.0
                row_has_x_cut = False
                strip_had_rows = True
                row_label = _get_label_from_cut(cut)
                continue

        # Finalizar resíduos/últimos cortes
        _finalize_strip()

        return rects

    def render_cutting_plan(self, material_data: dict, program_data: dict):
        """Desenha chapa e uma disposição simples das peças."""
        if not self.canvas:
            return

        self.canvas.delete("all")
        self._piece_item_ids.clear()
        self._piece_data_by_item.clear()
        self._selected_item = None

        # No SCM, a largura visual (eixo X) corresponde ao atributo lenght e a altura ao width
        sheet_w = self._to_float(program_data.get("length") or material_data.get("length"), 0.0)
        sheet_h = self._to_float(program_data.get("width") or material_data.get("width"), 0.0)
        if sheet_w <= 0 or sheet_h <= 0:
            self._set_status("Chapa sem dimensões válidas")
            return

        pieces = program_data.get("pieces") or []

        # Expandir peças por quantidade (se quantity > 1)
        expanded_pieces = []
        for piece in pieces:
            qty_raw = piece.get("quantity", 1)
            try:
                qty = int(float(str(qty_raw).replace(",", ".")))
            except Exception:
                qty = 1
            if qty < 1:
                continue
            if qty == 1:
                expanded_pieces.append(piece)
            else:
                for i in range(qty):
                    dup = dict(piece)
                    desc = dup.get("description", "")
                    dup["description"] = f"{desc} ({i + 1}/{qty})" if desc else f"Peça ({i + 1}/{qty})"
                    expanded_pieces.append(dup)

        # Área útil de desenho (com margem)
        canvas_w = max(self.canvas.winfo_width(), 10)
        canvas_h = max(self.canvas.winfo_height(), 10)
        margin = 30

        # Escala para caber no canvas
        scale_x = (canvas_w - 2 * margin) / sheet_w
        scale_y = (canvas_h - 2 * margin) / sheet_h
        base_scale = max(min(scale_x, scale_y), 0.01)

        self._scale = 1.0

        # Retângulo da chapa
        x0 = margin
        y0 = margin
        x1 = x0 + sheet_w * base_scale
        y1 = y0 + sheet_h * base_scale

        self.canvas.create_rectangle(
            x0,
            y0,
            x1,
            y1,
            fill=self.colors["bg_light"],
            outline=self.colors["text_gray"],
            width=2,
        )

        title = f"{material_data.get('description','')} - Chapa {program_data.get('number','')} ({int(sheet_w)}x{int(sheet_h)} mm)"
        self.canvas.create_text(
            x0,
            y0 - 12,
            text=title,
            anchor="sw",
            fill=self.colors["text_gray"],
            font=("Segoe UI", 9, "bold"),
        )

        pieces = expanded_pieces

        # Tentar layout por cortes reais
        cut_rects = self._build_rectangles_from_cuts(program_data, sheet_w, sheet_h)

        # Layout simples (fallback): shelf packing
        cursor_x = 0.0
        cursor_y = 0.0
        row_h = 0.0
        gap = 6.0  # mm

        drawn = 0
        skipped = 0

        if cut_rects:
            for rect in cut_rects:
                pw = rect["w"]
                ph = rect["h"]
                px = rect["x"]
                py = rect["y"]

                # Converter para origem no canto inferior esquerdo
                py_from_bottom = sheet_h - (py + ph)

                px0 = x0 + px * base_scale
                py0 = y0 + py_from_bottom * base_scale
                px1 = px0 + pw * base_scale
                py1 = py0 + ph * base_scale

                item_id = self.canvas.create_rectangle(
                    px0,
                    py0,
                    px1,
                    py1,
                    fill="#c5e1a5",
                    outline="#00acc1",
                    width=1,
                )

                self._piece_item_ids.append(item_id)
                self._piece_data_by_item[item_id] = {
                    "description": rect.get("description", ""),
                    "width": f"{pw:.0f}",
                    "height": f"{ph:.0f}",
                }

                text = f"{int(pw)} x {int(ph)}"
                self.canvas.create_text(
                    (px0 + px1) / 2,
                    (py0 + py1) / 2,
                    text=text,
                    fill="#1a1a1a",
                    font=("Segoe UI", 8, "bold"),
                )
                drawn += 1
        else:
            for piece in pieces:
                pw = self._to_float(piece.get("width"), 0.0)
                ph = self._to_float(piece.get("height"), 0.0)
                if pw <= 0 or ph <= 0:
                    skipped += 1
                    continue

                # Nova linha
                if cursor_x + pw > sheet_w and cursor_x > 0:
                    cursor_x = 0
                    cursor_y += row_h + gap
                    row_h = 0

                # Se não cabe mais verticalmente, para
                if cursor_y + ph > sheet_h:
                    skipped += 1
                    continue

                # Coordenadas em pixels
                px0 = x0 + cursor_x * base_scale
                py0 = y0 + cursor_y * base_scale
                px1 = px0 + pw * base_scale
                py1 = py0 + ph * base_scale

                item_id = self.canvas.create_rectangle(
                    px0,
                    py0,
                    px1,
                    py1,
                    fill="#263238",
                    outline="#00acc1",
                    width=1,
                )
                self._piece_item_ids.append(item_id)
                self._piece_data_by_item[item_id] = {
                    "description": piece.get("description", ""),
                    "width": piece.get("width", ""),
                    "height": piece.get("height", ""),
                    "quantity": piece.get("quantity", ""),
                }

                # Texto curto no centro
                label = piece.get("description", "")
                if label:
                    short = label if len(label) <= 18 else (label[:16] + "…")
                    self.canvas.create_text(
                        (px0 + px1) / 2,
                        (py0 + py1) / 2,
                        text=short,
                        fill=self.colors["text_white"],
                        font=("Segoe UI", 7),
                    )

                drawn += 1

                cursor_x += pw + gap
                row_h = max(row_h, ph)

        self._set_status(
            f"Peças desenhadas: {drawn} | Não desenhadas: {skipped} | Zoom: {self._scale:.2f}x"
        )

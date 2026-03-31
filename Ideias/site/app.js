const cutsList = document.getElementById("cutsList");
const statusEl = document.getElementById("status");
const canvas = document.getElementById("planCanvas");
const ctx = canvas.getContext("2d");

const sheetLengthEl = document.getElementById("sheetLength");
const sheetWidthEl = document.getElementById("sheetWidth");
const sheetThicknessEl = document.getElementById("sheetThickness");
const sheetMaterialEl = document.getElementById("sheetMaterial");

const btnAddCut = document.getElementById("btnAddCut");
const btnRender = document.getElementById("btnRender");
const btnClear = document.getElementById("btnClear");
const btnLoadSample = document.getElementById("btnLoadSample");
const btnZoomIn = document.getElementById("btnZoomIn");
const btnZoomOut = document.getElementById("btnZoomOut");
const btnReset = document.getElementById("btnReset");

let zoom = 1;
const zoomMin = 0.4;
const zoomMax = 3.5;

const cutTypes = ["RP", "P", "RY", "Y", "RX", "X", "RU", "U", "RV", "V", "RS", "S"];

function resizeCanvas() {
  const dpr = window.devicePixelRatio || 1;
  const { clientWidth, clientHeight } = canvas;
  const width = Math.max(clientWidth, 1);
  const height = Math.max(clientHeight, 1);
  canvas.width = Math.floor(width * dpr);
  canvas.height = Math.floor(height * dpr);
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  return { width, height };
}

function createCutRow(data = { type: "P", value: 100, rep: 1 }) {
  const row = document.createElement("div");
  row.className = "cut-row";

  const typeSel = document.createElement("select");
  cutTypes.forEach((t) => {
    const opt = document.createElement("option");
    opt.value = t;
    opt.textContent = t;
    if (t === data.type) opt.selected = true;
    typeSel.appendChild(opt);
  });

  const valueInput = document.createElement("input");
  valueInput.type = "number";
  valueInput.inputMode = "decimal";
  valueInput.value = data.value ?? 0;

  const repInput = document.createElement("input");
  repInput.type = "number";
  repInput.inputMode = "numeric";
  repInput.value = data.rep ?? 1;

  const btnRemove = document.createElement("button");
  btnRemove.textContent = "✕";
  btnRemove.addEventListener("click", () => {
    row.remove();
    renderPlan();
  });

  row.appendChild(typeSel);
  row.appendChild(valueInput);
  row.appendChild(repInput);
  row.appendChild(btnRemove);

  cutsList.appendChild(row);

  typeSel.addEventListener("change", renderPlan);
  valueInput.addEventListener("input", renderPlan);
  repInput.addEventListener("input", renderPlan);
}

function readCuts() {
  const rows = Array.from(document.querySelectorAll(".cut-row"));
  const cuts = [];

  rows.forEach((row) => {
    const [typeSel, valueInput, repInput] = row.querySelectorAll("select, input");
    const type = String(typeSel.value || "").trim();
    const value = parseFloat(String(valueInput.value || "0").replace(",", "."));
    const rep = parseInt(String(repInput.value || "1"), 10);

    if (!type || Number.isNaN(value) || value <= 0) {
      return;
    }

    const times = Math.max(rep || 1, 1);
    for (let i = 0; i < times; i += 1) {
      cuts.push({ type, value });
    }
  });

  return cuts;
}

function validateCuts(cuts, sheetW, sheetH) {
  const verticalRefiles = new Set(["RP", "RX", "RV", "P", "X", "V"]);
  const horizontalRefiles = new Set(["RY", "RU", "RS", "Y", "U", "S"]);
  const kerf = 4.2;

  let sumX = 0;
  let sumY = 0;
  let countX = 0;
  let countY = 0;

  cuts.forEach((cut) => {
    const type = String(cut.type || "").toUpperCase();
    const value = Number(cut.value || 0);
    if (!Number.isFinite(value) || value <= 0) return;

    if (verticalRefiles.has(type)) {
      sumX += value;
      countX += 1;
    }
    if (horizontalRefiles.has(type)) {
      sumY += value;
      countY += 1;
    }
  });

  if (countX > 0) sumX += kerf * countX;
  if (countY > 0) sumY += kerf * countY;

  const overflowX = sumX > sheetW;
  const overflowY = sumY > sheetH;
  return { overflowX, overflowY, sumX, sumY };
}

function buildRectanglesFromCuts(cuts, sheetW, sheetH) {
  if (!cuts.length) return [];

  const stripTypes = new Set(["P", "X", "V"]);
  const horizontalTypes = new Set(["Y", "U", "S"]);
  const verticalRefiles = new Set(["RP", "RX", "RV"]);
  const horizontalRefiles = new Set(["RY", "RU", "RS"]);

  const rects = [];
  let xCursor = 0;
  let yCursor = 0;
  let pendingColumns = [];

  let currentRow = null;
  let rowUsesCuts = false;

  function addRect(x, y, w, h) {
    if (w <= 0 || h <= 0) return;
    rects.push({ x, y, w, h });
  }

  function pendingTotal() {
    return pendingColumns.reduce((sum, v) => sum + v, 0);
  }

  function finalizeRow() {
    if (!currentRow) return;
    const widths = currentRow.widths.length
      ? currentRow.widths
      : (pendingColumns.length ? pendingColumns : [Math.max(sheetW - xCursor, 0)]);

    let x = xCursor;
    widths.forEach((w) => {
      addRect(x, yCursor, w, currentRow.height);
      x += w;
    });

    yCursor += currentRow.height;
    currentRow = null;
    rowUsesCuts = false;
  }

  cuts.forEach((cut) => {
    const rawType = String(cut.type || "").trim().toUpperCase();
    if (!rawType) return;
    if (rawType.endsWith("*")) return;

    const value = Number(cut.value || 0);
    if (!Number.isFinite(value) || value <= 0) return;

    if (verticalRefiles.has(rawType)) {
      finalizeRow();
      xCursor += value;
      pendingColumns = [];
      return;
    }
    if (horizontalRefiles.has(rawType)) {
      finalizeRow();
      yCursor += value;
      return;
    }

    if (stripTypes.has(rawType)) {
      if (rawType === "P") {
        if (currentRow) {
          finalizeRow();
          pendingColumns = [];
        }
        pendingColumns.push(value);
      }

      if (rawType === "X" && currentRow && currentRow.type === "Y") {
        if (!rowUsesCuts) {
          currentRow.widths = [];
          rowUsesCuts = true;
        }
        currentRow.widths.push(value);
      }

      if (rawType === "V" && currentRow && currentRow.type === "U") {
        if (!rowUsesCuts) {
          currentRow.widths = [];
          rowUsesCuts = true;
        }
        currentRow.widths.push(value);
      }
      return;
    }

    if (horizontalTypes.has(rawType)) {
      finalizeRow();
      currentRow = {
        type: rawType,
        height: value,
        widths: [],
      };
    }
  });

  finalizeRow();

  return rects;
}

function buildCutLinesFromCuts(cuts, sheetW, sheetH) {
  if (!cuts.length) return [];

  const stripTypes = new Set(["P", "X", "V"]);
  const horizontalTypes = new Set(["Y", "U", "S"]);
  const verticalRefiles = new Set(["RP", "RX", "RV"]);
  const horizontalRefiles = new Set(["RY", "RU", "RS"]);

  const lines = [];
  let xCursor = 0;
  let yCursor = 0;
  let pendingColumns = [];

  let currentRow = null;
  let rowUsesCuts = false;

  function addVLine(x, y0, y1, type) {
    if (y1 <= y0) return;
    lines.push({ x0: x, y0, x1: x, y1, type });
  }

  function addHLine(y, x0, x1, type) {
    if (x1 <= x0) return;
    lines.push({ x0, y0: y, x1, y1: y, type });
  }

  function pendingTotal() {
    return pendingColumns.reduce((sum, v) => sum + v, 0);
  }

  function finalizeRowLines() {
    if (!currentRow) return;
    const widths = currentRow.widths.length
      ? currentRow.widths
      : (pendingColumns.length ? pendingColumns : [Math.max(sheetW - xCursor, 0)]);

    const rowTop = yCursor + currentRow.height;
    addHLine(rowTop, xCursor, xCursor + widths.reduce((s, v) => s + v, 0), currentRow.type);

    let x = xCursor;
    widths.forEach((w) => {
      x += w;
      addVLine(x, yCursor, rowTop, currentRow.type);
    });

    yCursor += currentRow.height;
    currentRow = null;
    rowUsesCuts = false;
  }

  cuts.forEach((cut) => {
    const rawType = String(cut.type || "").trim().toUpperCase();
    if (!rawType) return;
    if (rawType.endsWith("*")) return;

    const value = Number(cut.value || 0);
    if (!Number.isFinite(value) || value <= 0) return;

    if (verticalRefiles.has(rawType)) {
      finalizeRowLines();
      const cutX = xCursor + value;
      addVLine(cutX, 0, sheetH, rawType);
      xCursor += value;
      pendingColumns = [];
      return;
    }

    if (horizontalRefiles.has(rawType)) {
      finalizeRowLines();
      const cutY = yCursor + value;
      addHLine(cutY, xCursor, xCursor + pendingTotal(), rawType);
      yCursor += value;
      return;
    }

    if (stripTypes.has(rawType)) {
      if (rawType === "P") {
        if (currentRow) {
          finalizeRowLines();
          pendingColumns = [];
        }
        pendingColumns.push(value);
        const cutX = xCursor + pendingTotal();
        addVLine(cutX, 0, sheetH, rawType);
      }

      if (rawType === "X" && currentRow && currentRow.type === "Y") {
        if (!rowUsesCuts) {
          currentRow.widths = [];
          rowUsesCuts = true;
        }
        currentRow.widths.push(value);
        const cutX = xCursor + currentRow.widths.reduce((s, v) => s + v, 0);
        addVLine(cutX, yCursor, yCursor + currentRow.height, rawType);
      }

      if (rawType === "V" && currentRow && currentRow.type === "U") {
        if (!rowUsesCuts) {
          currentRow.widths = [];
          rowUsesCuts = true;
        }
        currentRow.widths.push(value);
        const cutX = xCursor + currentRow.widths.reduce((s, v) => s + v, 0);
        addVLine(cutX, yCursor, yCursor + currentRow.height, rawType);
      }
      return;
    }

    if (horizontalTypes.has(rawType)) {
      finalizeRowLines();
      currentRow = {
        type: rawType,
        height: value,
        widths: [],
      };
    }
  });

  finalizeRowLines();

  return lines;
}

function clearCanvas() {
  const { width, height } = resizeCanvas();
  ctx.clearRect(0, 0, width, height);
}

function renderPlan() {
  const inputLength = Number(sheetLengthEl.value || 0);
  const inputWidth = Number(sheetWidthEl.value || 0);
  const thickness = Number(sheetThicknessEl.value || 0);
  const material = String(sheetMaterialEl.value || "");

  // Ajuste de orientação: eixo X recebe a largura visual
  const sheetW = inputWidth;
  const sheetH = inputLength;

  if (!Number.isFinite(sheetW) || !Number.isFinite(sheetH) || sheetW <= 0 || sheetH <= 0) {
    statusEl.textContent = "Dimensões inválidas da chapa.";
    clearCanvas();
    return;
  }

  const cuts = readCuts();
  const validation = validateCuts(cuts, sheetW, sheetH);
  const rects = buildRectanglesFromCuts(cuts, sheetW, sheetH);
  const lines = buildCutLinesFromCuts(cuts, sheetW, sheetH);

  const { width: viewW, height: viewH } = resizeCanvas();

  const margin = 24;
  const scaleX = (viewW - margin * 2) / sheetW;
  const scaleY = (viewH - margin * 2) / sheetH;
  const baseScale = Math.max(Math.min(scaleX, scaleY), 0.01) * zoom;

  const originX = margin;
  const originY = margin;

  // Fundo
  ctx.fillStyle = "#111827";
  ctx.fillRect(0, 0, viewW, viewH);

  // Chapa
  ctx.fillStyle = "#f5f5f5";
  ctx.strokeStyle = "#9ca3af";
  ctx.lineWidth = 2;
  ctx.fillRect(originX, originY, sheetW * baseScale, sheetH * baseScale);
  ctx.strokeRect(originX, originY, sheetW * baseScale, sheetH * baseScale);

  // Medidas da chapa (cantos)
  ctx.fillStyle = "#111827";
  ctx.font = "11px Segoe UI";
  ctx.fillText(`${sheetW} mm`, originX + sheetW * baseScale / 2 - 20, originY + 16);
  ctx.save();
  ctx.translate(originX + 10, originY + sheetH * baseScale / 2 + 20);
  ctx.rotate(-Math.PI / 2);
  ctx.fillText(`${sheetH} mm`, 0, 0);
  ctx.restore();

  // Título
  ctx.fillStyle = "#e5e7eb";
  ctx.font = "12px Segoe UI";
  ctx.fillText(`${material} ${thickness}mm - ${inputLength} x ${inputWidth} mm`, originX, originY - 10);

  if (validation.overflowX || validation.overflowY) {
    const parts = [];
    if (validation.overflowX) parts.push(`Largura insuficiente (${validation.sumX.toFixed(1)} > ${sheetW})`);
    if (validation.overflowY) parts.push(`Altura insuficiente (${validation.sumY.toFixed(1)} > ${sheetH})`);
    statusEl.textContent = `Material insuficiente | ${parts.join(" | ")}`;
    return;
  }

  // Peças
  rects.forEach((r) => {
    const px0 = originX + r.x * baseScale;
    const py0 = originY + (sheetH - (r.y + r.h)) * baseScale;
    const pw = r.w * baseScale;
    const ph = r.h * baseScale;

    ctx.fillStyle = "#fde68a";
    ctx.strokeStyle = "#111827";
    ctx.lineWidth = 1;
    ctx.fillRect(px0, py0, pw, ph);
    ctx.strokeRect(px0, py0, pw, ph);

    if (pw > 60 && ph > 25) {
      ctx.fillStyle = "#111827";
      ctx.font = "11px Segoe UI";
      ctx.fillText(`${Math.round(r.w)}x${Math.round(r.h)}`, px0 + 6, py0 + 14);
    }
  });

  // Linhas de corte
  ctx.strokeStyle = "#f44336";
  ctx.lineWidth = 1;
  lines.forEach((l) => {
    const x0 = originX + l.x0 * baseScale;
    const y0 = originY + (sheetH - l.y0) * baseScale;
    const x1 = originX + l.x1 * baseScale;
    const y1 = originY + (sheetH - l.y1) * baseScale;
    ctx.beginPath();
    ctx.moveTo(x0, y0);
    ctx.lineTo(x1, y1);
    ctx.stroke();
  });

  statusEl.textContent = `Cortes: ${cuts.length} | Peças: ${rects.length} | Zoom: ${zoom.toFixed(2)}x`;
}

function resetZoom() {
  zoom = 1;
  renderPlan();
}

btnAddCut.addEventListener("click", () => createCutRow());
btnRender.addEventListener("click", renderPlan);
btnClear.addEventListener("click", () => {
  cutsList.innerHTML = "";
  statusEl.textContent = "Limpo.";
  clearCanvas();
});
btnLoadSample.addEventListener("click", () => {
  cutsList.innerHTML = "";
  const sample = [
    { type: "RP", value: 5, rep: 1 },
    { type: "P", value: 100, rep: 1 },
    { type: "RY", value: 5, rep: 1 },
    { type: "Y", value: 2694, rep: 1 },
    { type: "P", value: 100, rep: 1 },
    { type: "RY", value: 5, rep: 1 },
    { type: "Y", value: 2694, rep: 1 },
    { type: "P", value: 100, rep: 1 },
    { type: "RY", value: 5, rep: 1 },
    { type: "Y", value: 2170, rep: 1 },
    { type: "P", value: 100, rep: 1 },
    { type: "RY", value: 5, rep: 1 },
    { type: "Y", value: 2170, rep: 1 },
    { type: "P", value: 150, rep: 1 },
    { type: "RY", value: 5, rep: 1 },
    { type: "Y", value: 500, rep: 5 },
    { type: "Y", value: 580, rep: 2 },
    { type: "RX", value: 5, rep: 1 },
    { type: "X", value: 125, rep: 1 },
    { type: "P", value: 125, rep: 1 },
    { type: "RY", value: 5, rep: 1 },
    { type: "Y", value: 580, rep: 4 }
  ];
  sample.forEach(createCutRow);
  renderPlan();
});

btnZoomIn.addEventListener("click", () => {
  zoom = Math.min(zoomMax, zoom * 1.15);
  renderPlan();
});
btnZoomOut.addEventListener("click", () => {
  zoom = Math.max(zoomMin, zoom / 1.15);
  renderPlan();
});
btnReset.addEventListener("click", resetZoom);

window.addEventListener("resize", renderPlan);

sheetLengthEl.addEventListener("input", renderPlan);
sheetWidthEl.addEventListener("input", renderPlan);
sheetThicknessEl.addEventListener("input", renderPlan);
sheetMaterialEl.addEventListener("input", renderPlan);

// Inicial
createCutRow();
renderPlan();

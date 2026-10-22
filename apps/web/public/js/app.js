const form = document.querySelector('#generation-form');
const imageInput = document.querySelector('#image');
const sourcePreview = document.querySelector('#source-preview');
const placeholder = document.querySelector('#upload-placeholder');
const submitButton = document.querySelector('#submit-button');
const errorBox = document.querySelector('#error');
const resultSection = document.querySelector('#result');
const canvas = document.querySelector('#editor-canvas');
const context = canvas.getContext('2d');
const stage = document.querySelector('#editor-stage');
const loader = document.querySelector('#editor-loader');
const editorStatus = document.querySelector('#editor-status');
const brushSize = document.querySelector('#brush-size');
const zoomValue = document.querySelector('#zoom-value');
const applyEditsButton = document.querySelector('#apply-edits');
const discardEditsButton = document.querySelector('#discard-edits');
const eraserOptions = document.querySelector('#eraser-options');
const eraserModeHelp = document.querySelector('#eraser-mode-help');
const colorCount = document.querySelector('#colors');
const autoColors = document.querySelector('#auto-colors');
const colorRange = document.querySelector('#color-range');
const samplerCanvas = document.createElement('canvas');
const samplerContext = samplerCanvas.getContext('2d', { willReadFrequently: true });

let currentResult = null;
let targetNumber = null;
let currentTool = 'pan';
let currentView = 'scheme';
let zoom = 1;
let baseImage = null;
let pointerDown = false;
let strokePoints = [];
let panStart = null;
let pendingOperations = [];
let samplerReady = false;
let eraserMode = 'color';

document.querySelectorAll('input[type="range"]').forEach((input) => {
    const key = input.name || input.id;
    const output = document.querySelector(`output[data-for="${key}"]`);
    if (!output) return;
    const render = () => {
        if (input === colorCount && autoColors.checked) {
            output.textContent = 'Авто';
            return;
        }
        output.textContent = `${input.value}${input.dataset.unit ?? ''}`;
    };
    input.addEventListener('input', render);
    render();
});

function syncColorMode() {
    colorCount.disabled = autoColors.checked;
    colorRange.classList.toggle('muted', autoColors.checked);
    document.querySelector('output[data-for="colors"]').textContent = autoColors.checked ? 'Авто' : colorCount.value;
}

autoColors.addEventListener('change', syncColorMode);
syncColorMode();

imageInput.addEventListener('change', () => {
    const file = imageInput.files[0];
    if (!file) return;
    sourcePreview.src = URL.createObjectURL(file);
    sourcePreview.hidden = false;
    placeholder.hidden = true;
});

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.hidden = true;
    resultSection.hidden = true;
    submitButton.disabled = true;
    submitButton.textContent = 'Строим схему…';
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: requestHeaders(),
            body: new FormData(form),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(responseMessage(payload, response.status));
        zoom = 1;
        renderResult(payload, true);
    } catch (error) {
        errorBox.textContent = error.message || 'Не удалось обработать изображение.';
        errorBox.hidden = false;
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Создать картину';
    }
});

document.querySelectorAll('[data-tool]').forEach((button) => {
    button.addEventListener('click', () => selectTool(button.dataset.tool));
});
document.querySelectorAll('[data-eraser-mode]').forEach((button) => {
    button.addEventListener('click', () => {
        eraserMode = button.dataset.eraserMode;
        document.querySelectorAll('[data-eraser-mode]').forEach((item) => {
            item.classList.toggle('selected', item === button);
        });
        updateToolOptions();
    });
});
document.querySelectorAll('[data-view]').forEach((button) => {
    button.addEventListener('click', () => {
        currentView = button.dataset.view;
        document.querySelectorAll('[data-view]').forEach((item) => item.classList.toggle('selected', item === button));
        loadEditorImage(false);
    });
});

document.querySelector('#zoom-in').addEventListener('click', () => setZoom(zoom * 1.25));
document.querySelector('#zoom-out').addEventListener('click', () => setZoom(zoom / 1.25));
document.querySelector('#zoom-fit').addEventListener('click', fitEditor);
applyEditsButton.addEventListener('click', applyPendingEdits);
discardEditsButton.addEventListener('click', discardPendingEdits);
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || !pendingOperations.length || loader.hidden === false) return;
    if (event.target.closest('#generation-form')) return;
    event.preventDefault();
    applyPendingEdits();
});
stage.addEventListener('wheel', (event) => {
    if (!event.ctrlKey) return;
    event.preventDefault();
    setZoom(zoom * (event.deltaY < 0 ? 1.12 : 1 / 1.12));
}, { passive: false });

canvas.addEventListener('pointerdown', (event) => {
    if (!currentResult || loader.hidden === false) return;
    if (currentTool === 'eyedropper') {
        pickColor(eventPoint(event));
        return;
    }
    pointerDown = true;
    canvas.setPointerCapture(event.pointerId);
    if (currentTool === 'pan') {
        panStart = { x: event.clientX, y: event.clientY, left: stage.scrollLeft, top: stage.scrollTop };
        canvas.classList.add('grabbing');
        return;
    }
    strokePoints = [eventPoint(event)];
    drawEditor();
});

canvas.addEventListener('pointermove', (event) => {
    if (!pointerDown) return;
    if (currentTool === 'pan') {
        stage.scrollLeft = panStart.left - (event.clientX - panStart.x);
        stage.scrollTop = panStart.top - (event.clientY - panStart.y);
        return;
    }
    const point = eventPoint(event);
    const previous = strokePoints[strokePoints.length - 1];
    if (!previous || Math.hypot(point[0] - previous[0], point[1] - previous[1]) >= 1.5) {
        strokePoints.push(point);
        drawEditor();
    }
});

canvas.addEventListener('pointerup', finishPointer);
canvas.addEventListener('pointercancel', finishPointer);

function finishPointer(event) {
    if (!pointerDown) return;
    pointerDown = false;
    canvas.classList.remove('grabbing');
    if (canvas.hasPointerCapture(event.pointerId)) canvas.releasePointerCapture(event.pointerId);
    if (currentTool === 'pan') return;
    queueStroke();
}

function queueStroke() {
    const operation = {
        brush: 'paint_stroke',
        eraser: 'erase_stroke',
        smooth: 'smooth_stroke',
        magic: 'magic_stroke',
    }[currentTool];
    if (!operation || !strokePoints.length) return;
    if (strokePoints.length === 1) strokePoints.push(strokePoints[0]);
    const pendingOperation = {
        operation,
        points: strokePoints.map((point) => [...point]),
        radius: Number(brushSize.value),
    };
    if (operation === 'paint_stroke') {
        pendingOperation.target_number = targetNumber;
    }
    if (operation === 'erase_stroke') {
        pendingOperation.erase_mode = eraserMode;
        if (eraserMode === 'color') pendingOperation.target_number = targetNumber;
    }
    pendingOperations.push(pendingOperation);
    strokePoints = [];
    updatePendingUi();
    drawEditor();
}

async function applyPendingEdits() {
    if (!pendingOperations.length || !currentResult) return;
    setEditorBusy(true, 'Перестраиваем контуры и номера…');
    try {
        const response = await fetch(currentResult.edit_url, {
            method: 'POST',
            headers: { ...requestHeaders(), 'Content-Type': 'application/json' },
            body: JSON.stringify({ operations: pendingOperations }),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(responseMessage(payload, response.status));
        pendingOperations = [];
        renderResult(payload, false);
        editorStatus.textContent = 'Все правки применены. SVG и PDF обновлены.';
    } catch (error) {
        editorStatus.textContent = error.message || 'Не удалось применить правку.';
        editorStatus.classList.add('failed');
        drawEditor();
    } finally {
        setEditorBusy(false);
        updatePendingUi();
    }
}

function discardPendingEdits() {
    pendingOperations = [];
    strokePoints = [];
    editorStatus.textContent = 'Неприменённые правки отменены.';
    editorStatus.classList.remove('failed');
    updatePendingUi();
    drawEditor();
}

function renderResult(payload, fitAfterLoad) {
    currentResult = payload;
    pendingOperations = [];
    strokePoints = [];
    targetNumber = payload.palette.some((color) => color.number === targetNumber)
        ? targetNumber
        : payload.palette[0]?.number ?? null;
    editorStatus.textContent = '';
    editorStatus.classList.remove('failed');
    const diagnostics = payload.diagnostics;
    document.querySelector('#stats').innerHTML = [
        [diagnostics.actual_colors, 'цветов'],
        [diagnostics.segments, 'сегментов'],
        [diagnostics.labels_placed, 'номеров'],
        [`${(diagnostics.processing_ms / 1000).toFixed(1)} с`, 'обработка'],
    ].map(([value, label]) => `<div><strong>${value}</strong><span>${label}</span></div>`).join('');
    document.querySelector('#downloads').innerHTML = [
        ['svg', 'Скачать SVG'], ['pdf', 'Скачать PDF'],
        ['palette', 'Палитра JSON'], ['diagnostics', 'Диагностика'],
    ].map(([key, label]) => `<a href="${payload.downloads[key]}" target="_blank">${label}</a>`).join('');
    document.querySelector('#palette').innerHTML = payload.palette.map((color) => `
        <div class="swatch"><i style="background:${color.hex}"></i><strong>${color.number}</strong>
        <span>${color.hex}<small>${color.percentage.toFixed(1)}%</small></span></div>`).join('');
    renderEditorPalette();
    loadSamplerImage();
    updatePendingUi();
    resultSection.hidden = false;
    loadEditorImage(fitAfterLoad);
    if (fitAfterLoad) resultSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function loadEditorImage(fitAfterLoad) {
    if (!currentResult) return;
    const image = new Image();
    image.onload = () => {
        baseImage = image;
        canvas.width = currentResult.diagnostics.processing_width;
        canvas.height = currentResult.diagnostics.processing_height;
        drawEditor();
        if (fitAfterLoad) fitEditor(); else applyZoom();
    };
    image.src = currentView === 'scheme' ? currentResult.svg_data_url : currentResult.preview_data_url;
}

function drawEditor() {
    if (!baseImage) return;
    context.clearRect(0, 0, canvas.width, canvas.height);
    context.drawImage(baseImage, 0, 0, canvas.width, canvas.height);
    pendingOperations.forEach((operation) => drawStrokeOverlay(
        operation.points,
        operation.radius,
        operation.operation,
        operation.target_number,
    ));
    if (strokePoints.length) {
        const operation = {
            brush: 'paint_stroke',
            eraser: 'erase_stroke',
            smooth: 'smooth_stroke',
            magic: 'magic_stroke',
        }[currentTool];
        drawStrokeOverlay(strokePoints, Number(brushSize.value), operation, targetNumber);
    }
}

function drawStrokeOverlay(points, radius, operation, colorNumber) {
    if (!points.length || !operation) return;
    const paletteColor = currentResult?.palette.find((color) => color.number === colorNumber)?.hex;
    context.save();
    context.strokeStyle = operation === 'smooth_stroke'
        ? 'rgba(42, 132, 211, .65)'
        : operation === 'magic_stroke'
            ? 'rgba(145, 74, 201, .68)'
        : operation === 'erase_stroke'
            ? 'rgba(225, 61, 48, .60)'
            : hexToRgba(paletteColor ?? '#1f624b', .62);
    context.fillStyle = context.strokeStyle;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.lineWidth = radius * 2;
    context.beginPath();
    context.moveTo(points[0][0], points[0][1]);
    points.slice(1).forEach(([x, y]) => context.lineTo(x, y));
    context.stroke();
    if (points.length === 1) {
        context.beginPath();
        context.arc(points[0][0], points[0][1], radius, 0, Math.PI * 2);
        context.fill();
    }
    context.restore();
}

function loadSamplerImage() {
    samplerReady = false;
    const image = new Image();
    image.onload = () => {
        samplerCanvas.width = currentResult.diagnostics.processing_width;
        samplerCanvas.height = currentResult.diagnostics.processing_height;
        samplerContext.clearRect(0, 0, samplerCanvas.width, samplerCanvas.height);
        samplerContext.drawImage(image, 0, 0, samplerCanvas.width, samplerCanvas.height);
        samplerReady = true;
    };
    image.src = currentResult.preview_data_url;
}

function pickColor([x, y]) {
    if (!samplerReady) {
        editorStatus.textContent = 'Цветное превью ещё загружается.';
        return;
    }
    const [red, green, blue] = samplerContext.getImageData(Math.round(x), Math.round(y), 1, 1).data;
    const selected = currentResult.palette.reduce((closest, color) => {
        const distance = Math.hypot(red - color.rgb[0], green - color.rgb[1], blue - color.rgb[2]);
        return !closest || distance < closest.distance ? { color, distance } : closest;
    }, null)?.color;
    if (!selected) return;
    targetNumber = selected.number;
    renderEditorPalette();
    editorStatus.textContent = `Пипетка выбрала цвет №${selected.number} (${selected.hex}).`;
    editorStatus.classList.remove('failed');
}

function updatePendingUi() {
    const count = pendingOperations.length;
    applyEditsButton.disabled = count === 0;
    discardEditsButton.disabled = count === 0;
    applyEditsButton.firstChild.textContent = count ? `Применить изменения (${count}) ` : 'Применить изменения ';
    if (count) {
        const verb = count === 1 ? 'ожидает' : 'ожидают';
        editorStatus.textContent = `${count} ${pluralizeEdits(count)} ${verb} применения.`;
        editorStatus.classList.remove('failed');
    }
}

function pluralizeEdits(count) {
    const lastTwo = count % 100;
    const last = count % 10;
    if (last === 1 && lastTwo !== 11) return 'правка';
    if (last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14)) return 'правки';
    return 'правок';
}

function hexToRgba(hex, alpha) {
    const value = Number.parseInt(hex.slice(1), 16);
    return `rgba(${value >> 16}, ${(value >> 8) & 255}, ${value & 255}, ${alpha})`;
}

function renderEditorPalette() {
    if (!currentResult) return;
    document.querySelector('#editor-palette').innerHTML = currentResult.palette.map((color) => `
        <button type="button" class="palette-choice${color.number === targetNumber ? ' selected' : ''}"
            data-number="${color.number}" title="Цвет №${color.number}: ${color.hex}"
            style="--swatch:${color.hex}"><span>${color.number}</span></button>`).join('');
    document.querySelectorAll('.palette-choice').forEach((button) => {
        button.addEventListener('click', () => {
            targetNumber = Number(button.dataset.number);
            renderEditorPalette();
        });
    });
}

function selectTool(tool) {
    currentTool = tool;
    document.querySelectorAll('[data-tool]').forEach((button) => button.classList.toggle('selected', button.dataset.tool === tool));
    canvas.dataset.tool = tool;
    updateToolOptions();
}

function updateToolOptions() {
    eraserOptions.hidden = currentTool !== 'eraser';
    const usesColor = ['eyedropper', 'brush'].includes(currentTool)
        || (currentTool === 'eraser' && eraserMode === 'color');
    document.querySelector('#brush-colors').classList.toggle(
        'muted',
        !usesColor,
    );
    eraserModeHelp.textContent = eraserMode === 'color'
        ? 'Удаляет весь пересечённый сегмент выбранного цвета.'
        : 'Стирает только проведённый мазок и заполняет его ближайшими соседними областями.';
    const help = {
        pan: 'Перетаскивайте увеличенный холст. Ctrl + колёсико меняет масштаб.',
        eyedropper: 'Щёлкните по цветному участку, чтобы выбрать его для кисти или ластика.',
        brush: 'Рисует выбранным цветом. Подходит для ручного исправления формы области.',
        eraser: eraserMode === 'color'
            ? 'Удаляет целиком пересечённый сегмент выбранного цвета и объединяет его с лучшим соседом.'
            : 'Удаляет только область под мазком, заполняя её цветами ближайших областей.',
        smooth: 'Локально выравнивает границы. Перестроение произойдёт после применения правок.',
        magic: 'Убирает затронутые области без номера, присоединяя их к самому большому соседнему сегменту.',
    };
    document.querySelector('#tool-help').textContent = help[currentTool];
}

function eventPoint(event) {
    const bounds = canvas.getBoundingClientRect();
    return [
        Math.min(canvas.width - 1, Math.max(0, (event.clientX - bounds.left) * canvas.width / bounds.width)),
        Math.min(canvas.height - 1, Math.max(0, (event.clientY - bounds.top) * canvas.height / bounds.height)),
    ];
}

function fitEditor() {
    if (!canvas.width) return;
    setZoom((stage.clientWidth - 36) / canvas.width);
    stage.scrollLeft = 0;
    stage.scrollTop = 0;
}

function setZoom(value) {
    zoom = Math.min(5, Math.max(0.1, value));
    applyZoom();
}

function applyZoom() {
    canvas.style.width = `${canvas.width * zoom}px`;
    canvas.style.height = `${canvas.height * zoom}px`;
    zoomValue.textContent = `${Math.round(zoom * 100)}%`;
}

function setEditorBusy(busy, message = '') {
    loader.hidden = !busy;
    if (busy) {
        loader.textContent = message;
        editorStatus.textContent = '';
        editorStatus.classList.remove('failed');
    }
}

function requestHeaders() {
    return {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    };
}

function responseMessage(payload, status) {
    return payload.errors ? Object.values(payload.errors).flat()[0] : payload.message ?? `Ошибка ${status}`;
}

selectTool('pan');

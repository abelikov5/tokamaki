const form = document.querySelector('#generation-form');
const imageInput = document.querySelector('#image');
const sourcePreview = document.querySelector('#source-preview');
const placeholder = document.querySelector('#upload-placeholder');
const submitButton = document.querySelector('#submit-button');
const errorBox = document.querySelector('#error');
const formatControls = document.querySelector('#format-controls');
const imagePreparing = document.querySelector('#image-preparing');
const fitDescription = document.querySelector('#fit-description');
const fitModeButtons = document.querySelectorAll('[data-fit-mode]');
const resultSection = document.querySelector('#result');
const canvas = document.querySelector('#editor-canvas');
const context = canvas.getContext('2d');
const stage = document.querySelector('#editor-stage');
const loader = document.querySelector('#editor-loader');
const editorStatus = document.querySelector('#editor-status');
const brushSize = document.querySelector('#brush-size');
const zoomValue = document.querySelector('#zoom-value');
const applyEditsButton = document.querySelector('#apply-edits');
const undoChangeButton = document.querySelector('#undo-change');
const discardEditsButton = document.querySelector('#discard-edits');
const eraserOptions = document.querySelector('#eraser-options');
const eraserModeHelp = document.querySelector('#eraser-mode-help');
const colorCount = document.querySelector('#colors');
const autoColors = document.querySelector('#auto-colors');
const colorRange = document.querySelector('#color-range');
const savedProjectsSection = document.querySelector('#saved-projects');
const savedProjectList = document.querySelector('#saved-project-list');
const resumeLoading = document.querySelector('#resume-loading');
const saveProjectButton = document.querySelector('#save-project');
const projectName = document.querySelector('#project-name');
const projectSaveStatus = document.querySelector('#project-save-status');
const samplerCanvas = document.createElement('canvas');
const samplerContext = samplerCanvas.getContext('2d', { willReadFrequently: true });

let currentResult = null;
let targetNumber = null;
let currentTool = 'pan';
let currentView = 'scheme';
let zoom = 1;
let baseImage = null;
let unlabeledImage = null;
let unlabeledLoading = false;
let pointerDown = false;
let strokePoints = [];
let panStart = null;
let pendingOperations = [];
let samplerReady = false;
let eraserMode = 'color';
let sourceImage = null;
let preparedImageBlob = null;
let preparedImageUrl = null;
let fitMode = 'crop';
let imagePreparationRevision = 0;
let editorBusyTimer = null;

const PREPARED_IMAGE_WIDTH = 1600;
const PREPARED_IMAGE_HEIGHT = 2000;
const SAVED_PROJECTS_KEY = 'tokamaki.saved-projects.v1';
const MAX_SAVED_PROJECTS = 3;

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

imageInput.addEventListener('change', async () => {
    const file = imageInput.files[0];
    if (!file) {
        resetPreparedImage();
        return;
    }

    const revision = ++imagePreparationRevision;
    selectFitMode('crop');
    setImagePreparing(true);
    errorBox.hidden = true;

    try {
        const decodedImage = await loadSourceImage(file);
        if (revision !== imagePreparationRevision) return;
        sourceImage = decodedImage;
        formatControls.hidden = false;
        await prepareImage(revision);
    } catch (error) {
        if (revision !== imagePreparationRevision) return;
        resetPreparedImage();
        errorBox.textContent = 'Не удалось прочитать фотографию. Выберите другой JPG или PNG.';
        errorBox.hidden = false;
    } finally {
        if (revision === imagePreparationRevision) setImagePreparing(false);
    }
});

fitModeButtons.forEach((button) => {
    button.addEventListener('click', async () => {
        if (!sourceImage || button.dataset.fitMode === fitMode) return;
        selectFitMode(button.dataset.fitMode);
        const revision = ++imagePreparationRevision;
        setImagePreparing(true);
        try {
            await prepareImage(revision);
        } catch (error) {
            if (revision !== imagePreparationRevision) return;
            errorBox.textContent = 'Не удалось подготовить фотографию.';
            errorBox.hidden = false;
        } finally {
            if (revision === imagePreparationRevision) setImagePreparing(false);
        }
    });
});

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.hidden = true;
    resultSection.hidden = true;
    submitButton.disabled = true;
    submitButton.textContent = 'Строим схему…';
    try {
        if (!preparedImageBlob) throw new Error('Дождитесь подготовки фотографии.');
        const formData = new FormData(form);
        formData.set('image', preparedImageBlob, preparedFileName(imageInput.files[0]?.name));
        const response = await fetch(form.action, {
            method: 'POST',
            headers: requestHeaders(),
            body: formData,
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

function loadSourceImage(file) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        const objectUrl = URL.createObjectURL(file);
        image.onload = () => {
            URL.revokeObjectURL(objectUrl);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('Image decoding failed'));
        };
        image.src = objectUrl;
    });
}

async function prepareImage(revision) {
    const preparationCanvas = document.createElement('canvas');
    preparationCanvas.width = PREPARED_IMAGE_WIDTH;
    preparationCanvas.height = PREPARED_IMAGE_HEIGHT;
    const preparationContext = preparationCanvas.getContext('2d');
    preparationContext.imageSmoothingEnabled = true;
    preparationContext.imageSmoothingQuality = 'high';
    preparationContext.fillStyle = '#ffffff';
    preparationContext.fillRect(0, 0, PREPARED_IMAGE_WIDTH, PREPARED_IMAGE_HEIGHT);

    if (fitMode === 'stretch') {
        preparationContext.drawImage(sourceImage, 0, 0, PREPARED_IMAGE_WIDTH, PREPARED_IMAGE_HEIGHT);
    } else {
        const scale = Math.max(
            PREPARED_IMAGE_WIDTH / sourceImage.naturalWidth,
            PREPARED_IMAGE_HEIGHT / sourceImage.naturalHeight,
        );
        const width = sourceImage.naturalWidth * scale;
        const height = sourceImage.naturalHeight * scale;
        preparationContext.drawImage(
            sourceImage,
            (PREPARED_IMAGE_WIDTH - width) / 2,
            (PREPARED_IMAGE_HEIGHT - height) / 2,
            width,
            height,
        );
    }

    const blob = await canvasToBlob(preparationCanvas);
    if (revision !== imagePreparationRevision) return;
    preparedImageBlob = blob;
    if (preparedImageUrl) URL.revokeObjectURL(preparedImageUrl);
    preparedImageUrl = URL.createObjectURL(blob);
    sourcePreview.src = preparedImageUrl;
    sourcePreview.hidden = false;
    placeholder.hidden = true;
}

function canvasToBlob(canvas) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('Canvas export failed')), 'image/jpeg', 0.92);
    });
}

function preparedFileName(originalName = 'photo.jpg') {
    const stem = originalName.replace(/\.[^.]+$/, '') || 'photo';
    return `${stem}-40x50.jpg`;
}

function selectFitMode(mode) {
    fitMode = mode;
    fitModeButtons.forEach((item) => {
        const selected = item.dataset.fitMode === mode;
        item.classList.toggle('selected', selected);
        item.setAttribute('aria-pressed', String(selected));
    });
    fitDescription.textContent = mode === 'crop'
        ? 'Лишнее по краям обрезается автоматически, без искажения пропорций.'
        : 'Вся фотография помещается в формат 40 × 50″ с изменением пропорций.';
}

function setImagePreparing(preparing) {
    imagePreparing.hidden = !preparing;
    submitButton.disabled = preparing;
}

function resetPreparedImage() {
    ++imagePreparationRevision;
    sourceImage = null;
    preparedImageBlob = null;
    if (preparedImageUrl) URL.revokeObjectURL(preparedImageUrl);
    preparedImageUrl = null;
    sourcePreview.removeAttribute('src');
    sourcePreview.hidden = true;
    placeholder.hidden = false;
    formatControls.hidden = true;
    setImagePreparing(false);
}

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
undoChangeButton.addEventListener('click', undoLastChange);
discardEditsButton.addEventListener('click', discardPendingEdits);
saveProjectButton.addEventListener('click', saveProject);
document.addEventListener('keydown', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const isEditable = target?.matches('input, textarea, select, [contenteditable="true"]');
    if (event.ctrlKey && !event.altKey && event.key.toLowerCase() === 'z' && !isEditable) {
        event.preventDefault();
        undoLastChange();
        return;
    }
    if (event.key !== 'Enter' || !pendingOperations.length || loader.hidden === false) return;
    if (target?.closest('#generation-form')) return;
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
    if (currentTool === 'label') {
        queueLabel(eventPoint(event));
        return;
    }
    if (currentTool === 'unlabeled') return;
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
    if (!pendingOperations.length || !currentResult) return true;
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
        return true;
    } catch (error) {
        editorStatus.textContent = error.message || 'Не удалось применить правку.';
        editorStatus.classList.add('failed');
        drawEditor();
        return false;
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

async function undoLastChange() {
    if (!currentResult || loader.hidden === false) return;
    if (pendingOperations.length) {
        pendingOperations.pop();
        strokePoints = [];
        updatePendingUi();
        drawEditor();
        editorStatus.textContent = 'Последняя неприменённая правка отменена.';
        editorStatus.classList.remove('failed');
        return;
    }
    if (!currentResult.can_undo) {
        editorStatus.textContent = 'Нет сохранённого изменения для отмены.';
        editorStatus.classList.remove('failed');
        return;
    }

    setEditorBusy(true, 'Возвращаем предыдущую версию…');
    try {
        const response = await fetch(currentResult.undo_url, {
            method: 'POST',
            headers: requestHeaders(),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(responseMessage(payload, response.status));
        renderResult(payload, false);
        editorStatus.textContent = 'Последнее сохранённое изменение отменено.';
    } catch (error) {
        editorStatus.textContent = error.message || 'Не удалось отменить последнее изменение.';
        editorStatus.classList.add('failed');
    } finally {
        setEditorBusy(false);
        updatePendingUi();
    }
}

function renderResult(payload, fitAfterLoad) {
    currentResult = payload;
    unlabeledImage = null;
    unlabeledLoading = false;
    pendingOperations = [];
    strokePoints = [];
    targetNumber = payload.palette.some((color) => color.number === targetNumber)
        ? targetNumber
        : payload.palette[0]?.number ?? null;
    editorStatus.textContent = '';
    editorStatus.classList.remove('failed');
    projectName.textContent = payload.original_name || 'Сохранённая картина';
    projectSaveStatus.textContent = 'Применённые изменения сохраняются автоматически.';
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
    rememberProject(payload);
    if (window.location.href !== payload.resume_url) {
        window.history.replaceState({}, '', payload.resume_url);
    }
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
        if (fitAfterLoad) {
            requestAnimationFrame(() => requestAnimationFrame(fitEditor));
        } else {
            applyZoom();
        }
    };
    image.src = currentView === 'scheme' ? currentResult.svg_data_url : currentResult.preview_data_url;
}

function drawEditor() {
    if (!baseImage) return;
    context.clearRect(0, 0, canvas.width, canvas.height);
    context.drawImage(baseImage, 0, 0, canvas.width, canvas.height);
    if (currentTool === 'unlabeled' && unlabeledImage) {
        context.drawImage(unlabeledImage, 0, 0, canvas.width, canvas.height);
    }
    pendingOperations.forEach((operation) => {
        if (operation.operation === 'add_label') {
            drawLabelOverlay(operation);
            return;
        }
        drawStrokeOverlay(
            operation.points,
            operation.radius,
            operation.operation,
            operation.target_number,
            operation.erase_mode,
        );
    });
    if (strokePoints.length) {
        const operation = {
            brush: 'paint_stroke',
            eraser: 'erase_stroke',
            smooth: 'smooth_stroke',
            magic: 'magic_stroke',
        }[currentTool];
        drawStrokeOverlay(strokePoints, Number(brushSize.value), operation, targetNumber, eraserMode);
    }
}

function drawStrokeOverlay(points, radius, operation, colorNumber, operationEraserMode = null) {
    if (!points.length || !operation) return;
    const paletteColor = currentResult?.palette.find((color) => color.number === colorNumber)?.hex;
    context.save();
    context.strokeStyle = operation === 'smooth_stroke'
        ? 'rgba(42, 132, 211, .65)'
        : operation === 'magic_stroke'
            ? 'rgba(145, 74, 201, .68)'
        : operation === 'erase_stroke'
            ? operationEraserMode === 'local'
                ? 'rgba(255, 255, 255, .96)'
                : 'rgba(225, 61, 48, .60)'
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

function drawLabelOverlay(operation) {
    const fontSize = Math.max(8, Math.min(18, canvas.width * 0.012));
    context.save();
    context.font = `700 ${fontSize}px Arial, sans-serif`;
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    context.lineWidth = Math.max(2, fontSize * 0.28);
    context.strokeStyle = 'rgba(255,255,255,.96)';
    context.fillStyle = '#111';
    context.strokeText(String(operation.target_number), operation.x, operation.y);
    context.fillText(String(operation.target_number), operation.x, operation.y);
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

function loadUnlabeledOverlay() {
    const url = currentResult?.unlabeled_data_url;
    if (!url || unlabeledImage || unlabeledLoading) return;
    unlabeledLoading = true;
    updateToolOptions();
    const image = new Image();
    image.onload = () => {
        if (currentResult?.unlabeled_data_url !== url) return;
        unlabeledLoading = false;
        unlabeledImage = image;
        updateToolOptions();
        if (currentTool === 'unlabeled') drawEditor();
    };
    image.onerror = () => {
        if (currentResult?.unlabeled_data_url !== url) return;
        unlabeledLoading = false;
        unlabeledImage = null;
        if (currentTool === 'unlabeled') {
            editorStatus.textContent = 'Не удалось загрузить подсветку областей без номеров.';
            editorStatus.classList.add('failed');
        }
    };
    image.src = url;
}

function pickColor([x, y]) {
    if (!samplerReady) {
        editorStatus.textContent = 'Цветное превью ещё загружается.';
        return;
    }
    const selected = paletteColorAt(x, y);
    if (!selected) return;
    targetNumber = selected.number;
    renderEditorPalette();
    editorStatus.textContent = `Пипетка выбрала цвет №${selected.number} (${selected.hex}).`;
    editorStatus.classList.remove('failed');
}

function paletteColorAt(x, y) {
    if (!samplerReady) return null;
    const [red, green, blue] = samplerContext.getImageData(Math.round(x), Math.round(y), 1, 1).data;
    return currentResult.palette.reduce((closest, color) => {
        const distance = Math.hypot(red - color.rgb[0], green - color.rgb[1], blue - color.rgb[2]);
        return !closest || distance < closest.distance ? { color, distance } : closest;
    }, null)?.color;
}

function queueLabel([x, y]) {
    if (!samplerReady) {
        editorStatus.textContent = 'Цветное превью ещё загружается.';
        return;
    }
    const selected = paletteColorAt(x, y);
    if (!selected) return;
    pendingOperations.push({
        operation: 'add_label',
        x: Math.round(x),
        y: Math.round(y),
        target_number: selected.number,
    });
    updatePendingUi();
    drawEditor();
}

function updatePendingUi() {
    const count = pendingOperations.length;
    applyEditsButton.disabled = count === 0;
    discardEditsButton.disabled = count === 0;
    undoChangeButton.disabled = count === 0 && !currentResult?.can_undo;
    applyEditsButton.firstChild.textContent = count ? `Применить изменения (${count}) ` : 'Применить изменения ';
    if (count) {
        const verb = count === 1 ? 'ожидает' : 'ожидают';
        editorStatus.textContent = `${count} ${pluralizeEdits(count)} ${verb} применения.`;
        editorStatus.classList.remove('failed');
        projectSaveStatus.textContent = 'Есть неприменённые изменения.';
    } else if (currentResult) {
        projectSaveStatus.textContent = 'Все изменения сохранены.';
    }
}

async function saveProject() {
    if (!currentResult) return;
    saveProjectButton.disabled = true;
    saveProjectButton.textContent = pendingOperations.length ? 'Сохраняем…' : 'Копируем ссылку…';
    try {
        if (pendingOperations.length && !(await applyPendingEdits())) return;
        rememberProject(currentResult);
        await copyText(currentResult.resume_url);
        projectSaveStatus.textContent = 'Проект сохранён. Ссылка скопирована.';
    } catch (error) {
        projectSaveStatus.textContent = 'Проект сохранён, но ссылку не удалось скопировать.';
    } finally {
        saveProjectButton.disabled = false;
        saveProjectButton.textContent = 'Сохранить проект';
    }
}

async function copyText(value) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);
        return;
    }
    const temporaryInput = document.createElement('textarea');
    temporaryInput.value = value;
    temporaryInput.style.position = 'fixed';
    temporaryInput.style.opacity = '0';
    document.body.appendChild(temporaryInput);
    temporaryInput.select();
    document.execCommand('copy');
    temporaryInput.remove();
}

function rememberProject(payload) {
    if (!payload?.resume_url) return;
    const project = {
        url: payload.resume_url,
        name: payload.original_name || 'Картина без названия',
        updatedAt: payload.updated_at || new Date().toISOString(),
    };
    const projects = readSavedProjects().filter((item) => item.url !== project.url);
    projects.unshift(project);
    try {
        localStorage.setItem(SAVED_PROJECTS_KEY, JSON.stringify(projects.slice(0, MAX_SAVED_PROJECTS)));
    } catch (error) {
        return;
    }
    renderSavedProjects();
}

function readSavedProjects() {
    try {
        const projects = JSON.parse(localStorage.getItem(SAVED_PROJECTS_KEY) || '[]');
        return Array.isArray(projects) ? projects.filter((item) => item?.url && item?.name) : [];
    } catch (error) {
        return [];
    }
}

function renderSavedProjects() {
    const storedProjects = readSavedProjects();
    const projects = storedProjects.slice(0, MAX_SAVED_PROJECTS);
    if (storedProjects.length !== projects.length) {
        try {
            localStorage.setItem(SAVED_PROJECTS_KEY, JSON.stringify(projects));
        } catch (error) {
            // The list can still be rendered when storage is unavailable.
        }
    }
    savedProjectList.replaceChildren();
    savedProjectsSection.hidden = projects.length === 0;
    projects.forEach((project) => {
        const card = document.createElement('div');
        card.className = 'saved-project-card';
        const link = document.createElement('a');
        link.href = project.url;
        const name = document.createElement('strong');
        name.textContent = project.name;
        const date = document.createElement('span');
        date.textContent = `Изменено ${formatProjectDate(project.updatedAt)}`;
        link.append(name, date);
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'saved-project-remove';
        removeButton.setAttribute('aria-label', `Удалить ${project.name} из истории`);
        removeButton.title = 'Удалить из истории';
        removeButton.textContent = '×';
        removeButton.addEventListener('click', () => removeSavedProject(project.url));
        card.append(link, removeButton);
        savedProjectList.append(card);
    });
}

function removeSavedProject(url) {
    const projects = readSavedProjects().filter((project) => project.url !== url).slice(0, MAX_SAVED_PROJECTS);
    try {
        localStorage.setItem(SAVED_PROJECTS_KEY, JSON.stringify(projects));
    } catch (error) {
        return;
    }
    renderSavedProjects();
}

function formatProjectDate(value) {
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? 'недавно'
        : new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

async function loadResumedProject() {
    const endpoint = document.body.dataset.resumeEndpoint;
    if (!endpoint) return;
    resumeLoading.hidden = false;
    try {
        const response = await fetch(endpoint, { headers: requestHeaders() });
        const payload = await response.json();
        if (!response.ok) throw new Error(responseMessage(payload, response.status));
        renderResult(payload, true);
    } catch (error) {
        errorBox.textContent = error.message || 'Не удалось загрузить сохранённую картину.';
        errorBox.hidden = false;
    } finally {
        resumeLoading.hidden = true;
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
    if (tool === 'unlabeled') loadUnlabeledOverlay();
    drawEditor();
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
        : 'Стирает проведённый мазок, продолжая через него преобладающую соседнюю область.';
    const help = {
        pan: 'Перетаскивайте увеличенный холст. Ctrl + колёсико меняет масштаб.',
        eyedropper: 'Щёлкните по цветному участку, чтобы выбрать его для кисти или ластика.',
        brush: 'Рисует выбранным цветом. Подходит для ручного исправления формы области.',
        eraser: eraserMode === 'color'
            ? 'Удаляет целиком пересечённый сегмент выбранного цвета и объединяет его с лучшим соседом.'
            : 'Удаляет линию под мазком и заполняет это место преобладающей соседней областью.',
        smooth: 'Локально выравнивает границы. Перестроение произойдёт после применения правок.',
        magic: 'Убирает затронутые области без номера, присоединяя их к самому большому соседнему сегменту.',
        label: 'Щёлкните внутри области — номер её цвета и подходящий размер определятся автоматически.',
        unlabeled: unlabeledLoading
            ? 'Готовим подсветку областей без номеров…'
            : Number.isInteger(currentResult?.diagnostics.unlabeled_segments)
                ? `Красным выделены области без номеров: ${currentResult.diagnostics.unlabeled_segments}.`
                : 'Красным выделены области, в которых нет номера.',
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
    const availableHeight = Math.max(1, stage.clientHeight - 36);
    setZoom(availableHeight / canvas.height);
    const overflowWidth = canvas.width * zoom - (stage.clientWidth - 36);
    stage.scrollLeft = Math.max(0, overflowWidth / 2);
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
    if (editorBusyTimer) {
        clearInterval(editorBusyTimer);
        editorBusyTimer = null;
    }
    loader.hidden = !busy;
    if (busy) {
        const startedAt = Date.now();
        const renderMessage = () => {
            const elapsed = Math.floor((Date.now() - startedAt) / 1000);
            loader.textContent = elapsed > 0 ? `${message} ${elapsed} с` : message;
        };
        renderMessage();
        editorBusyTimer = setInterval(renderMessage, 1000);
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

window.addEventListener('beforeunload', (event) => {
    if (!pendingOperations.length) return;
    event.preventDefault();
    event.returnValue = '';
});

renderSavedProjects();
loadResumedProject();
selectTool('pan');

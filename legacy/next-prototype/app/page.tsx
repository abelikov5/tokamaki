"use client";

import { FormEvent, useMemo, useState } from "react";

type Result = {
  result_id: string;
  preview_data_url: string;
  svg_data_url: string;
  pdf_url: string;
  palette: { number: number; hex: string; rgb: [number, number, number]; pixel_count: number; percentage: number }[];
  diagnostics: {
    requested_colors: number;
    actual_colors: number;
    segments: number;
    small_regions_before_merge: number;
    small_regions_after_merge: number;
    thin_regions_before_merge: number;
    thin_regions_after_merge: number;
    smoothing: number;
    min_region_width: number;
    contour_smoothing: number;
    complexity: string;
    line_separation: number;
    effective_min_region_size: number;
    effective_min_region_width: number;
    effective_contour_smoothing: number;
    effective_line_separation: number;
    boundary_paths: number;
    label_density: number;
    labels_placed: number;
    processing_width: number;
    processing_height: number;
    processing_ms: number;
  };
};

const API = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

export default function Home() {
  const [file, setFile] = useState<File | null>(null);
  const [colors, setColors] = useState(24);
  const [minSize, setMinSize] = useState(100);
  const [smoothing, setSmoothing] = useState(2);
  const [minWidth, setMinWidth] = useState(4);
  const [contourSmoothing, setContourSmoothing] = useState(2);
  const [complexity, setComplexity] = useState("balanced");
  const [lineSeparation, setLineSeparation] = useState(2);
  const [labelDensity, setLabelDensity] = useState(1);
  const [result, setResult] = useState<Result | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const localPreview = useMemo(() => (file ? URL.createObjectURL(file) : ""), [file]);

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (!file) return;
    setBusy(true);
    setError("");
    setResult(null);
    const data = new FormData();
    data.append("file", file);
    data.append("colors", String(colors));
    data.append("min_region_size", String(minSize));
    data.append("smoothing", String(smoothing));
    data.append("min_region_width", String(minWidth));
    data.append("contour_smoothing", String(contourSmoothing));
    data.append("complexity", complexity);
    data.append("line_separation", String(lineSeparation));
    data.append("label_density", String(labelDensity));
    try {
      const response = await fetch(`${API}/api/v1/process`, { method: "POST", body: data });
      if (!response.ok) {
        const detail = await response.json().catch(() => null);
        throw new Error(detail?.detail ?? `Ошибка API: ${response.status}`);
      }
      setResult(await response.json());
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "Не удалось обработать изображение");
    } finally {
      setBusy(false);
    }
  }

  return (
    <main>
      <header>
        <p className="eyebrow">Технический прототип</p>
        <h1>Картина по номерам<br /><span>из вашей фотографии</span></h1>
        <p className="lead">Загрузите JPG или PNG — сервис сократит палитру, очистит мелкие области и построит векторную схему.</p>
      </header>

      <form onSubmit={submit} className="panel controls">
        <label className="upload">
          <input type="file" accept="image/jpeg,image/png" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
          {localPreview ? <img src={localPreview} alt="Исходное изображение" /> : <span><b>Выберите фотографию</b><small>JPG или PNG, до 20 МБ</small></span>}
        </label>
        <div className="fields">
          <label>Количество цветов
            <select value={colors} onChange={(e) => setColors(Number(e.target.value))}>
              {[16, 24, 36, 48].map((value) => <option key={value}>{value}</option>)}
            </select>
          </label>
          <label>Сложность сегментов
            <select value={complexity} onChange={(e) => setComplexity(e.target.value)}>
              <option value="detailed">Detailed — максимум деталей</option>
              <option value="balanced">Balanced — сбалансировано</option>
              <option value="simple">Simple — крупные области</option>
              <option value="minimal">Minimal — минимальная сложность</option>
            </select>
          </label>
          <label>Минимальный участок: <b>{minSize} px</b>
            <input type="range" min="10" max="500" step="10" value={minSize} onChange={(e) => setMinSize(Number(e.target.value))} />
          </label>
          <label>Сглаживание фотографии: <b>{smoothing.toFixed(1)}</b>
            <input type="range" min="0" max="5" step="0.5" value={smoothing} onChange={(e) => setSmoothing(Number(e.target.value))} />
          </label>
          <label>Минимальная толщина области: <b>{minWidth.toFixed(1)} px</b>
            <input type="range" min="0" max="12" step="0.5" value={minWidth} onChange={(e) => setMinWidth(Number(e.target.value))} />
          </label>
          <label>Сглаживание контуров: <b>{contourSmoothing.toFixed(1)}</b>
            <input type="range" min="0.5" max="6" step="0.5" value={contourSmoothing} onChange={(e) => setContourSmoothing(Number(e.target.value))} />
          </label>
          <label>Дистанция между линиями: <b>{lineSeparation.toFixed(1)} px</b>
            <input type="range" min="0" max="8" step="0.5" value={lineSeparation} onChange={(e) => setLineSeparation(Number(e.target.value))} />
          </label>
          <label>Плотность номеров: <b>{labelDensity.toFixed(2)}×</b>
            <input type="range" min="0.25" max="3" step="0.25" value={labelDensity} onChange={(e) => setLabelDensity(Number(e.target.value))} />
          </label>
          <button disabled={!file || busy}>{busy ? "Строим схему…" : "Создать картину"}</button>
        </div>
        {error && <p className="error">{error}</p>}
      </form>

      {result && <section className="results">
        <div className="stats">
          <div><b>{result.diagnostics.actual_colors}</b><span>цветов</span></div>
          <div><b>{result.diagnostics.segments}</b><span>сегментов</span></div>
          <div><b>{result.diagnostics.thin_regions_after_merge}</b><span>слишком тонких</span></div>
          <div><b>{(result.diagnostics.processing_ms / 1000).toFixed(1)} с</b><span>обработка</span></div>
        </div>
        <div className="visuals">
          <article className="panel"><h2>Цветное превью</h2><img src={result.preview_data_url} alt="Цветное превью" /></article>
          <article className="panel"><h2>Схема с номерами</h2><img src={result.svg_data_url} alt="Схема с номерами" /></article>
        </div>
        <div className="actions">
          <a href={`${API}/api/v1/results/${result.result_id}/scheme.svg`} download>Скачать SVG</a>
          <a href={`${API}${result.pdf_url}`} download>Скачать PDF</a>
          <a href={`${API}/api/v1/results/${result.result_id}/palette.json`} download>Палитра JSON</a>
        </div>
        <div className="panel palette"><h2>Палитра</h2><div className="swatches">
          {result.palette.map((entry) => <div key={entry.number} className="swatch"><i style={{ background: entry.hex }} /><b>{entry.number}</b><span>{entry.hex}<small>{entry.percentage.toFixed(1)}%</small></span></div>)}
        </div></div>
      </section>}
    </main>
  );
}

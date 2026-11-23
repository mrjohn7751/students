import ApexCharts from 'apexcharts';

const dataEl = document.getElementById('stats-data');
const stats = dataEl ? JSON.parse(dataEl.textContent) : null;
const drillEl = document.getElementById('drill-config');
/** Grafikdan talabalar ro'yxatiga o'tish (faqat shaxsiy ma'lumotga ruxsati borlar uchun beriladi). */
const drillConfig = drillEl ? JSON.parse(drillEl.textContent) : null;

const fmt = (v) => (v === null || v === undefined ? '—' : Number(v).toLocaleString('uz-UZ').replace(/,/g, ' '));
const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
const isDark = () => {
    const t = document.documentElement.dataset.theme;
    return t ? t === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
};

/** Kategorial ranglar — qat'iy tartibda, hech qachon aylantirilmaydi. */
const series = (i) => css(`--series-${i + 1}`);
const OTHER = () => css('--series-other');
const MAX_SLOTS = 8;

/** 8 tadan ortiq kategoriyani "Boshqa"ga yig'ish. */
function foldOther(labels, values, max = MAX_SLOTS) {
    if (labels.length <= max) return { labels, values };
    const keep = max - 1;
    return {
        labels: [...labels.slice(0, keep), 'Boshqa'],
        values: [...values.slice(0, keep), values.slice(keep).reduce((a, b) => a + b, 0)],
    };
}

function base(type, height) {
    const surface = css('--surface-1');
    return {
        chart: {
            type,
            height,
            fontFamily: 'Manrope, ui-sans-serif, system-ui, sans-serif',
            foreColor: css('--text-secondary'),
            background: 'transparent',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: true, speed: 400 },
            parentHeightOffset: 0,
        },
        theme: { mode: isDark() ? 'dark' : 'light' },
        grid: {
            borderColor: css('--grid'),
            strokeDashArray: 0,
            padding: { left: 8, right: 8 },
            xaxis: { lines: { show: false } },
        },
        dataLabels: { enabled: false },
        legend: {
            position: 'top',
            horizontalAlign: 'left',
            fontSize: '12px',
            fontWeight: 600,
            labels: { colors: css('--text-secondary') },
            markers: { size: 6, shape: 'square', strokeWidth: 0 },
            itemMargin: { horizontal: 10, vertical: 2 },
        },
        tooltip: {
            theme: isDark() ? 'dark' : 'light',
            y: { formatter: (v) => fmt(v) },
            marker: { show: true },
        },
        stroke: { width: 2, colors: [surface] },
        states: { hover: { filter: { type: 'darken', value: 0.9 } } },
        xaxis: {
            axisBorder: { color: css('--border') },
            axisTicks: { show: false },
            labels: { style: { colors: css('--text-muted'), fontSize: '11px' } },
            crosshairs: { stroke: { color: css('--text-muted'), width: 1, dashArray: 3 } },
        },
        yaxis: {
            labels: { style: { colors: css('--text-muted'), fontSize: '11px' }, formatter: (v) => fmt(Math.round(v)) },
        },
    };
}

/** Gorizontal ustunlar — bir seriyali, uzun nomli kategoriyalar uchun. */
function hbar(labels, values, height, name = 'Talabalar') {
    const o = base('bar', height);
    return {
        ...o,
        series: [{ name, data: values }],
        colors: [series(0)],
        plotOptions: {
            bar: { horizontal: true, borderRadius: 4, borderRadiusApplication: 'end', barHeight: '62%', dataLabels: { position: 'top' } },
        },
        dataLabels: {
            enabled: true,
            formatter: (v) => fmt(v),
            textAnchor: 'start',
            offsetX: 28,
            style: { fontSize: '11px', fontWeight: 700, colors: [css('--text-secondary')] },
            dropShadow: { enabled: false },
        },
        xaxis: { ...o.xaxis, categories: labels, labels: { ...o.xaxis.labels, formatter: (v) => fmt(v) } },
        yaxis: {
            labels: {
                maxWidth: 220,
                style: { colors: css('--text-secondary'), fontSize: '12px' },
            },
        },
        grid: { ...o.grid, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
        stroke: { width: 0 },
        legend: { show: false },
    };
}

/** Halqasimon — faqat ≤ 4 bo'lak uchun (aks holda ustun ishlatiladi). */
function donut(labels, values, height) {
    const folded = foldOther(labels, values, 4);
    const o = base('donut', height);
    const total = folded.values.reduce((a, b) => a + b, 0);
    return {
        ...o,
        series: folded.values,
        labels: folded.labels,
        colors: folded.labels.map((l, i) => (l === 'Boshqa' ? OTHER() : series(i))),
        stroke: { width: 2, colors: [css('--surface-1')] },
        legend: { ...o.legend, position: 'bottom', horizontalAlign: 'center' },
        plotOptions: {
            pie: {
                donut: {
                    size: '68%',
                    labels: {
                        show: true,
                        name: { fontSize: '12px', color: css('--text-muted'), offsetY: 18 },
                        value: { fontSize: '24px', fontWeight: 800, color: css('--text-primary'), offsetY: -14, formatter: (v) => fmt(v) },
                        total: { show: true, label: 'Jami', color: css('--text-muted'), formatter: () => fmt(total) },
                    },
                },
            },
        },
        tooltip: { ...o.tooltip, y: { formatter: (v) => `${fmt(v)} (${total ? ((v / total) * 100).toFixed(1) : 0}%)` } },
    };
}

function stacked(categories, seriesList, height, { horizontal = false, colors } = {}) {
    const o = base('bar', height);
    return {
        ...o,
        chart: { ...o.chart, stacked: true },
        series: seriesList,
        colors: colors ?? seriesList.map((_, i) => series(i)),
        plotOptions: {
            bar: {
                horizontal,
                borderRadius: 4,
                borderRadiusApplication: 'end',
                borderRadiusWhenStacked: 'last',
                columnWidth: '68%',
                barHeight: '66%',
            },
        },
        stroke: { width: 2, colors: [css('--surface-1')] },
        xaxis: {
            ...o.xaxis,
            categories,
            labels: { ...o.xaxis.labels, ...(horizontal ? { formatter: (v) => fmt(v) } : {}) },
        },
        yaxis: horizontal
            ? { labels: { maxWidth: 240, style: { colors: css('--text-secondary'), fontSize: '12px' } } }
            : o.yaxis,
        grid: horizontal ? { ...o.grid, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } } : o.grid,
        tooltip: { ...o.tooltip, shared: true, intersect: false },
    };
}

function column(labels, values, height, name = 'Talabalar') {
    const o = base('bar', height);
    return {
        ...o,
        series: [{ name, data: values }],
        colors: [series(0)],
        plotOptions: { bar: { borderRadius: 4, borderRadiusApplication: 'end', columnWidth: '64%' } },
        stroke: { width: 0 },
        xaxis: { ...o.xaxis, categories: labels },
        legend: { show: false },
    };
}

function line(labels, values, height, name, { suffix = '', decimals = 2 } = {}) {
    const o = base('line', height);
    const f = (v) => (v === null ? '—' : `${Number(v).toFixed(decimals)}${suffix}`);
    return {
        ...o,
        series: [{ name, data: values }],
        colors: [series(0)],
        stroke: { width: 2, curve: 'smooth' },
        markers: { size: 4, strokeWidth: 2, strokeColors: css('--surface-1'), hover: { size: 7 } },
        xaxis: { ...o.xaxis, categories: labels, tooltip: { enabled: false } },
        yaxis: { ...o.yaxis, labels: { ...o.yaxis.labels, formatter: f } },
        tooltip: { ...o.tooltip, y: { formatter: f } },
        legend: { show: false },
    };
}

/** Ro'yxat manzili: dashboard filtrlari + tanlangan qiymat. */
function drillUrl(params) {
    if (!drillConfig || !params) return null;
    const query = new URLSearchParams();
    Object.entries({ ...drillConfig.filters, ...params }).forEach(([k, v]) => {
        if (v !== null && v !== undefined && v !== '') query.set(k, v);
    });
    return `${drillConfig.url}?${query.toString()}`;
}

const STUDYING = { status: '11' };
/** Klassifikator qiymati bo'yicha filtr; "Boshqa" (yig'ilgan bo'lak) aniq to'plam emas — bosilmaydi. */
const byField = (column, label, extra = {}) => (label === undefined || label === 'Boshqa' ? null : { ...extra, [`f[${column}]`]: label });
/** "18–19", "17 va kichik", "40+" → yosh oralig'i. */
function ageRange(label) {
    const nums = (label.match(/\d+/g) || []).map(Number);
    if (label.includes('+')) return { age_min: nums[0] + 1 };
    if (label.includes('kichik')) return { age_max: nums[0] };
    return { age_min: nums[0], age_max: nums[1] ?? nums[0] };
}

/** Har bir chart uchun konfiguratsiya va jadval ko'rinishi uchun ma'lumot. */
function definitions(s) {
    const d = {};
    const kv = (b) => ({ head: ['Ko‘rsatkich', 'Soni'], rows: b.labels.map((l, i) => [l, b.values[i]]) });
    const multi = (cats, list, first = 'Kategoriya') => ({
        head: [first, ...list.map((x) => x.name)],
        rows: cats.map((c, i) => [c, ...list.map((x) => x.data[i])]),
    });

    const cohortColors = [series(0), series(2), series(1), series(3), OTHER()];
    const cohortStatuses = ['11', '14,18', '12', '13,15,16', 'other'];
    d.cohorts = {
        opts: stacked(s.cohorts.years, s.cohorts.series, 360, { colors: cohortColors }),
        table: multi(s.cohorts.years, s.cohorts.series, 'Qabul yili'),
        drill: (si, i) => ({ year_of_enter: s.cohorts.years[i], status: cohortStatuses[si] }),
    };
    d.status = { opts: hbar(s.status.labels, s.status.values, 360), table: kv(s.status), drill: (_, i) => byField('status_name', s.status.labels[i]) };

    const grad = [
        { name: 'Oddiy diplom', data: s.graduates.total.map((t, i) => t - s.graduates.honors[i]) },
        { name: 'Imtiyozli diplom', data: s.graduates.honors },
    ];
    d.graduates = {
        opts: stacked(s.graduates.years, grad, 320),
        table: multi(s.graduates.years, grad, 'Bitirish yili'),
        drill: (si, i) => ({ graduate_year: s.graduates.years[i], diploma: si === 0 ? 'regular' : 'honors' }),
    };
    d.admission = { opts: hbar(s.admission_type.labels, s.admission_type.values, 320), table: kv(s.admission_type), drill: (_, i) => byField('admission_type_name', s.admission_type.labels[i]) };

    const fac = [
        { name: 'Erkak', data: s.faculties.male },
        { name: 'Ayol', data: s.faculties.female },
    ];
    d.faculties = {
        opts: stacked(s.faculties.labels, fac, 400, { horizontal: true }),
        table: multi(s.faculties.labels, fac, 'Fakultet'),
        drill: (si, i) => ({ ...byField('department_name', s.faculties.labels[i], STUDYING), 'f[gender_name]': fac[si]?.name }),
    };

    const lv = s.levels.series.length > MAX_SLOTS
        ? [...s.levels.series.slice(0, MAX_SLOTS - 1), {
            name: 'Boshqa',
            data: s.levels.labels.map((_, i) => s.levels.series.slice(MAX_SLOTS - 1).reduce((a, x) => a + x.data[i], 0)),
        }]
        : s.levels.series;
    d.levels = {
        opts: stacked(s.levels.labels, lv, 400, { colors: lv.map((x, i) => (x.name === 'Boshqa' ? OTHER() : series(i))) }),
        table: multi(s.levels.labels, lv, 'Kurs'),
        drill: (si, i) => ({
            ...byField('level_name', s.levels.labels[i], STUDYING),
            ...(lv[si] && lv[si].name !== 'Boshqa' ? { 'f[education_form_name]': lv[si].name } : {}),
        }),
    };

    // Rang shaxsga bog'lanadi: Erkak = 1-slot, Ayol = 2-slot (Fakultetlar chartidagi kabi)
    const genderOrder = ['Erkak', 'Ayol'];
    const rank = (label) => (genderOrder.includes(label) ? genderOrder.indexOf(label) : 99);
    const g = s.gender.labels.map((l, i) => [l, s.gender.values[i]]).sort((a, b) => rank(a[0]) - rank(b[0]));
    const gender = { labels: g.map((x) => x[0]), values: g.map((x) => x[1]) };
    // Donut 4 tadan ortiq bo'lakni "Boshqa"ga yig'adi — grafikda shu ko'rinadigan yorliqlar bo'yicha
    const donutDrill = (b, column) => {
        const shown = foldOther(b.labels, b.values, 4).labels;
        return { chart: (_, i) => byField(column, shown[i], STUDYING), table: (_, i) => byField(column, b.labels[i], STUDYING) };
    };
    const studyingField = (column, b) => (_, i) => byField(column, b.labels[i], STUDYING);
    for (const [key, b, column] of [['gender', gender, 'gender_name'], ['payment', s.payment_form, 'payment_form_name'], ['edutype', s.education_type, 'education_type_name']]) {
        const dd = donutDrill(b, column);
        d[key] = { opts: donut(b.labels, b.values, 260), table: kv(b), drill: dd.chart, tableDrill: dd.table };
    }
    d.eduform = { opts: hbar(s.education_form.labels, s.education_form.values, 300), table: kv(s.education_form), drill: studyingField('education_form_name', s.education_form) };
    d.specialties = { opts: hbar(s.specialties.labels, s.specialties.values, 300), table: kv(s.specialties), drill: studyingField('specialty_name', s.specialties) };
    d.provinces = { opts: hbar(s.provinces.labels, s.provinces.values, 380), table: kv(s.provinces), drill: studyingField('province_name', s.provinces) };
    d.age = { opts: column(s.age.labels, s.age.values, 380), table: kv(s.age), drill: (_, i) => ({ ...STUDYING, ...ageRange(s.age.labels[i]) }) };
    d.accommodation = { opts: hbar(s.accommodation.labels, s.accommodation.values, 280), table: kv(s.accommodation), drill: studyingField('accommodation_name', s.accommodation) };
    d.social = { opts: hbar(s.social_category.labels, s.social_category.values, 280), table: kv(s.social_category), drill: studyingField('social_category_name', s.social_category) };
    d.lang = { opts: hbar(s.education_lang.labels, s.education_lang.values, 280), table: kv(s.education_lang), drill: studyingField('education_lang_name', s.education_lang) };

    d.gpa = {
        opts: line(s.gpa_by_year.years, s.gpa_by_year.avg_gpa, 280, 'O‘rtacha GPA'),
        table: { head: ['O‘quv yili', 'O‘rtacha GPA'], rows: s.gpa_by_year.years.map((y, i) => [y, s.gpa_by_year.avg_gpa[i]]) },
        drill: (_, i) => ({ gpa_year: s.gpa_by_year.codes?.[i] }),
    };
    d.debt = {
        opts: line(s.gpa_by_year.years, s.gpa_by_year.debt_share, 280, 'Qarzdorlar ulushi', { suffix: '%', decimals: 1 }),
        table: { head: ['O‘quv yili', 'Qarzdorlar, %'], rows: s.gpa_by_year.years.map((y, i) => [y, s.gpa_by_year.debt_share[i]]) },
        drill: (_, i) => ({ gpa_year: s.gpa_by_year.codes?.[i], debt: 1 }),
    };
    d.gpadist = {
        opts: column(s.gpa_distribution.labels, s.gpa_distribution.values, 280),
        table: kv(s.gpa_distribution),
        drill: (_, i) => {
            const min = Number(s.gpa_distribution.labels[i]);
            return { gpa_year: s.gpa_distribution.year_code, gpa_min: min.toFixed(2), gpa_max: (min + 0.25).toFixed(2) };
        },
    };
    const diplomaShown = foldOther(s.diploma_category.labels, s.diploma_category.values, 4).labels;
    d.diplomacat = {
        opts: donut(s.diploma_category.labels, s.diploma_category.values, 280),
        table: kv(s.diploma_category),
        drill: (_, i) => (diplomaShown[i] === 'Boshqa' ? null : { diploma_category: diplomaShown[i] }),
        tableDrill: (_, i) => ({ diploma_category: s.diploma_category.labels[i] }),
    };

    return d;
}

const rendered = new Map();

function renderAll() {
    if (!stats) return;
    rendered.forEach((c) => c.destroy());
    rendered.clear();

    const defs = definitions(stats);
    for (const [id, def] of Object.entries(defs)) {
        const el = document.getElementById(`chart-${id}`);
        if (!el) continue;

        const empty = !def.table.rows.length;
        if (empty) {
            el.innerHTML = '<div class="h-full min-h-[inherit] grid place-items-center text-sm muted">Ma’lumot yo‘q</div>';
            continue;
        }
        el.innerHTML = '';
        const drillable = Boolean(drillConfig && def.drill);
        const opts = drillable ? withDrill(def.opts, def.drill) : def.opts;
        el.classList.toggle('drillable', drillable);
        el.classList.toggle('drillable-line', drillable && def.opts.chart.type === 'line');
        const chart = new ApexCharts(el, opts);
        chart.render();
        rendered.set(id, chart);
        renderTable(id, def.table, drillConfig ? (def.tableDrill ?? def.drill) : null);
    }
}

/** Ustun, bo'lak yoki nuqta bosilganda mos talabalar ro'yxatiga o'tish. */
function withDrill(opts, drill) {
    const go = (seriesIndex, dataPointIndex) => {
        if (dataPointIndex < 0) return;
        const url = drillUrl(drill(seriesIndex, dataPointIndex));
        if (url) window.location.assign(url);
    };
    return {
        ...opts,
        chart: {
            ...opts.chart,
            events: {
                // Umumiy (shared) tooltip'li ustma-ust ustunlarda cfg.seriesIndex noto'g'ri keladi —
                // bosilgan ustunning o'z atributlari (index = seriya, j = nuqta) ishonchliroq
                dataPointSelection: (event, ctx, cfg) => {
                    const bar = event?.target?.closest?.('path[j]');
                    const seriesIndex = bar?.hasAttribute('index') ? Number(bar.getAttribute('index')) : cfg.seriesIndex;
                    const pointIndex = bar ? Number(bar.getAttribute('j')) : cfg.dataPointIndex;
                    go(seriesIndex, pointIndex);
                },
                // Chiziqli grafikda nuqtalar bosilmaydi (shared tooltip) — kursor turgan o'quv yili olinadi
                click: (event, ctx, cfg) => {
                    if (opts.chart.type === 'line') go(0, cfg.dataPointIndex ?? -1);
                },
            },
        },
        states: { ...opts.states, active: { filter: { type: 'none' } } },
    };
}

function renderTable(id, { head, rows }, drill = null) {
    const box = document.getElementById(`table-${id}`);
    if (!box) return;
    const esc = (v) => String(v ?? '—').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
    box.innerHTML = `<table class="data-table"><thead><tr>${head
        .map((h, i) => `<th class="${i ? 'num' : ''}">${esc(h)}</th>`)
        .join('')}</tr></thead><tbody>${rows
        .map((r, row) => `<tr>${r.map((c, i) => {
            const text = i && typeof c === 'number' ? fmt(c) : esc(c);
            const url = i && drill ? drillUrl(drill(i - 1, row)) : null;
            return `<td class="${i ? 'num' : ''}">${url ? `<a href="${esc(url)}" class="font-semibold hover:underline" style="color: var(--accent)">${text}</a>` : text}</td>`;
        }).join('')}</tr>`)
        .join('')}</tbody></table>`;
}

document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-table-toggle]');
    if (!btn) return;
    const id = btn.dataset.tableToggle;
    const showTable = btn.getAttribute('aria-pressed') !== 'true';
    btn.setAttribute('aria-pressed', String(showTable));
    btn.textContent = showTable ? 'Grafik' : 'Jadval';
    document.getElementById(`chart-${id}`).classList.toggle('hidden', showTable);
    document.getElementById(`table-${id}`).classList.toggle('hidden', !showTable);
});

document.getElementById('theme-toggle')?.addEventListener('click', () => {
    const next = isDark() ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;
    try { localStorage.setItem('theme', next); } catch (e) { /* saqlash mumkin emas */ }
    renderAll();
});

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (!document.documentElement.dataset.theme) renderAll();
});

// Filtr o'zgarganda darhol qo'llash
document.querySelectorAll('#filters select').forEach((s) => s.addEventListener('change', () => {
    [...s.form.elements].forEach((el) => { if (el.name && !el.value) el.disabled = true; });
    s.form.submit();
}));

renderAll();

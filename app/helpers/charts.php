<?php

/**
 * Composants graphiques SVG pour les dashboards (Phase 6). Respecte les
 * specs du système de dataviz interne : traits 2px, marqueurs >=8px avec
 * anneau de surface, légende toujours présente pour 2+ séries, libellés
 * directs sélectifs (valeur en bout de série), grille discrète, survol
 * avec réticule + infobulle. Palette catégorielle fixe (jamais recalculée
 * selon le filtre) — voir docs/CONSOLIDATION_LOGIC.md §Dashboards.
 */

/** Palette catégorielle fixe (6 filiales), validée CVD-safe (script dataviz). */
function chart_subsidiary_color(string $code): string
{
    $map = [
        'NOVA-CI' => '#2a78d6', // bleu
        'NOVA-FR' => '#1baf7a', // aqua
        'NOVA-GH' => '#eda100', // jaune
        'NOVA-MA' => '#008300', // vert
        'NOVA-ML' => '#4a3aa7', // violet
        'NOVA-SN' => '#e34948', // rouge
    ];
    return $map[$code] ?? '#8a8f97';
}

/** Formate un montant en notation compacte (1,2 M / 850 K) pour les axes/labels. */
function format_compact_amount(float $amount): string
{
    $abs = abs($amount);
    $sign = $amount < 0 ? '-' : '';
    if ($abs >= 1_000_000_000) {
        return $sign . number_format($abs / 1_000_000_000, 1, ',', ' ') . ' Md';
    }
    if ($abs >= 1_000_000) {
        return $sign . number_format($abs / 1_000_000, 1, ',', ' ') . ' M';
    }
    if ($abs >= 1_000) {
        return $sign . number_format($abs / 1_000, 0, ',', ' ') . ' K';
    }
    return $sign . number_format($abs, 0, ',', ' ');
}

/**
 * Graphique de tendance (courbes) — 3 séries fixes : revenu, EBITDA, résultat net.
 * @param array<int, array{period: \App\Models\ReportingPeriod, revenue: float, ebitda: float, netIncome: float}> $trend
 */
function render_trend_chart(array $trend, string $chartId): string
{
    if (empty($trend)) {
        return '<div class="empty-state">Aucune donnée pour cette sélection.</div>';
    }

    $series = [
        ['key' => 'revenue', 'label' => "Chiffre d'affaires", 'color' => '#2a78d6'],
        ['key' => 'ebitda', 'label' => 'EBITDA', 'color' => '#1baf7a'],
        ['key' => 'netIncome', 'label' => 'Résultat net', 'color' => '#4a3aa7'],
    ];

    $width = 760;
    $height = 300;
    $padLeft = 56;
    $padRight = 20;
    $padTop = 16;
    $padBottom = 32;
    $plotW = $width - $padLeft - $padRight;
    $plotH = $height - $padTop - $padBottom;
    $n = count($trend);

    $allValues = [];
    foreach ($trend as $row) {
        foreach ($series as $s) {
            $allValues[] = $row[$s['key']];
        }
    }
    $max = max($allValues);
    $min = min(0, min($allValues));
    $range = ($max - $min) ?: 1;

    $x = fn (int $i) => $padLeft + ($n > 1 ? $i / ($n - 1) * $plotW : $plotW / 2);
    $y = fn (float $v) => $padTop + $plotH - (($v - $min) / $range) * $plotH;

    // Grille horizontale : 4 paliers arrondis.
    $gridLines = '';
    $steps = 4;
    for ($i = 0; $i <= $steps; $i++) {
        $val = $min + ($range * $i / $steps);
        $gy = $y($val);
        $gridLines .= sprintf(
            '<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="#e1e0d9" stroke-width="1"/>' .
            '<text x="%d" y="%.1f" font-size="10.5" fill="#898781" text-anchor="end">%s</text>',
            $padLeft, $gy, $width - $padRight, $gy,
            $padLeft - 8, $gy + 3.5, h(format_compact_amount($val))
        );
    }

    $monthLabels = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $xLabels = '';
    foreach ($trend as $i => $row) {
        $xLabels .= sprintf(
            '<text x="%.1f" y="%d" font-size="10.5" fill="#898781" text-anchor="middle">%s</text>',
            $x($i), $height - 8, h($monthLabels[$row['period']->month - 1] ?? '')
        );
    }

    $paths = '';
    $points = '';
    $endLabels = '';
    $pointCoords = []; // pour le JS de survol

    foreach ($series as $s) {
        $d = '';
        $coords = [];
        foreach ($trend as $i => $row) {
            $px = $x($i);
            $py = $y($row[$s['key']]);
            $coords[] = [$px, $py];
            $d .= ($i === 0 ? 'M' : 'L') . sprintf('%.1f,%.1f', $px, $py);
        }
        $paths .= sprintf('<path d="%s" fill="none" stroke="%s" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>', $d, $s['color']);

        [$lastX, $lastY] = end($coords);
        $points .= sprintf(
            '<circle cx="%.1f" cy="%.1f" r="5" fill="%s" stroke="#fcfcfb" stroke-width="2"/>',
            $lastX, $lastY, $s['color']
        );
        $endLabels .= sprintf(
            '<text x="%.1f" y="%.1f" font-size="11" font-weight="600" fill="%s" text-anchor="start">%s</text>',
            $lastX + 8, $lastY + 4, $s['color'], h(format_compact_amount(end($trend)[$s['key']]))
        );
        $pointCoords[$s['key']] = $coords;
    }

    // Ligne de réticule + points de survol (masqués par défaut, pilotés en JS).
    $hoverDots = '';
    foreach ($series as $s) {
        $hoverDots .= sprintf('<circle class="hover-dot" data-series="%s" r="5" fill="%s" stroke="#fcfcfb" stroke-width="2" opacity="0"/>', $s['key'], $s['color']);
    }

    $legend = '<div class="chart-legend">';
    foreach ($series as $s) {
        $legend .= sprintf(
            '<span class="chart-legend-item"><span class="chart-legend-swatch" style="background:%s"></span>%s</span>',
            $s['color'], h($s['label'])
        );
    }
    $legend .= '</div>';

    // Données embarquées pour le JS de survol (réticule + infobulle).
    $jsData = [];
    foreach ($trend as $i => $row) {
        $entry = ['x' => $x($i), 'label' => $row['period']->label];
        foreach ($series as $s) {
            $entry[$s['key']] = ['y' => $y($row[$s['key']]), 'value' => $row[$s['key']]];
        }
        $jsData[] = $entry;
    }
    $jsDataJson = json_encode($jsData, JSON_UNESCAPED_UNICODE);
    $crosshairBottom = $padTop + $plotH;

    return <<<HTML
    <div class="chart-wrap">
        {$legend}
        <div class="chart-svg-container" style="position:relative">
            <svg viewBox="0 0 {$width} {$height}" id="{$chartId}" class="chart-svg">
                {$gridLines}
                {$paths}
                {$points}
                {$endLabels}
                {$xLabels}
                <line class="crosshair" x1="0" y1="{$padTop}" x2="0" y2="{$crosshairBottom}" stroke="#c3c2b7" stroke-width="1" opacity="0"/>
                {$hoverDots}
                <rect x="{$padLeft}" y="{$padTop}" width="{$plotW}" height="{$plotH}" fill="transparent" class="chart-hitlayer"/>
            </svg>
            <div class="chart-tooltip" style="display:none"></div>
        </div>
    </div>
    <script>
    (function() {
        var data = {$jsDataJson};
        var svg = document.getElementById('{$chartId}');
        var hit = svg.querySelector('.chart-hitlayer');
        var crosshair = svg.querySelector('.crosshair');
        var dots = svg.querySelectorAll('.hover-dot');
        var tooltip = svg.parentElement.querySelector('.chart-tooltip');
        var seriesLabels = {revenue: "Chiffre d'affaires", ebitda: 'EBITDA', netIncome: 'Résultat net'};

        function nearestIndex(px) {
            var best = 0, bestDist = Infinity;
            data.forEach(function(d, i) {
                var dist = Math.abs(d.x - px);
                if (dist < bestDist) { bestDist = dist; best = i; }
            });
            return best;
        }

        hit.addEventListener('mousemove', function(evt) {
            var rect = svg.getBoundingClientRect();
            var scaleX = {$width} / rect.width;
            var px = (evt.clientX - rect.left) * scaleX;
            var idx = nearestIndex(px);
            var d = data[idx];

            crosshair.setAttribute('x1', d.x);
            crosshair.setAttribute('x2', d.x);
            crosshair.setAttribute('opacity', 1);

            var rows = '';
            dots.forEach(function(dot) {
                var key = dot.getAttribute('data-series');
                dot.setAttribute('cy', d[key].y);
                dot.setAttribute('cx', d.x);
                dot.setAttribute('opacity', 1);
                rows += '<div class="chart-tooltip-row"><span>' + seriesLabels[key] + '</span><strong>' + d[key].value.toLocaleString('fr-FR', {maximumFractionDigits: 0}) + '</strong></div>';
            });

            tooltip.innerHTML = '<div class="chart-tooltip-title">' + d.label + '</div>' + rows;
            tooltip.style.display = 'block';
            var left = (d.x / {$width}) * rect.width;
            tooltip.style.left = Math.min(left + 12, rect.width - 190) + 'px';
            tooltip.style.top = '8px';
        });

        hit.addEventListener('mouseleave', function() {
            crosshair.setAttribute('opacity', 0);
            dots.forEach(function(dot) { dot.setAttribute('opacity', 0); });
            tooltip.style.display = 'none';
        });
    })();
    </script>
    HTML;
}

/**
 * Graphique en barres horizontales — contribution EBITDA par filiale.
 * @param array<int, array{subsidiary: \App\Models\Subsidiary, ebitda: float}> $contribution
 */
function render_contribution_chart(array $contribution): string
{
    if (empty($contribution)) {
        return '<div class="empty-state">Aucune donnée pour cette sélection.</div>';
    }

    $width = 760;
    $rowH = 34;
    $padLeft = 130;
    $padRight = 90;
    $padTop = 8;
    $height = $padTop + count($contribution) * $rowH + 8;
    $plotW = $width - $padLeft - $padRight;

    $max = max(array_map(fn ($r) => abs($r['ebitda']), $contribution)) ?: 1;

    $bars = '';
    foreach ($contribution as $i => $row) {
        $s = $row['subsidiary'];
        $value = $row['ebitda'];
        $barW = max(2, abs($value) / $max * $plotW);
        $cy = $padTop + $i * $rowH + $rowH / 2;
        $color = chart_subsidiary_color($s->code);
        $barX = $value >= 0 ? $padLeft : $padLeft - $barW;

        $bars .= sprintf(
            '<text x="%d" y="%.1f" font-size="11.5" fill="#0b0b0b" text-anchor="end" dominant-baseline="middle">%s</text>',
            $padLeft - 12, $cy, h($s->code)
        );
        $bars .= sprintf(
            '<rect x="%.1f" y="%.1f" width="%.1f" height="18" rx="4" fill="%s"><title>%s : %s</title></rect>',
            $barX, $cy - 9, $barW, $color, h($s->name), h(format_amount($value))
        );
        $bars .= sprintf(
            '<text x="%.1f" y="%.1f" font-size="11" font-weight="600" fill="#0b0b0b" text-anchor="%s" dominant-baseline="middle">%s</text>',
            $value >= 0 ? $barX + $barW + 8 : $barX - 8, $cy,
            $value >= 0 ? 'start' : 'end',
            h(format_compact_amount($value))
        );
    }

    $baseline = $padLeft;

    return <<<HTML
    <div class="chart-wrap">
        <svg viewBox="0 0 {$width} {$height}" class="chart-svg">
            <line x1="{$baseline}" y1="0" x2="{$baseline}" y2="{$height}" stroke="#c3c2b7" stroke-width="1"/>
            {$bars}
        </svg>
    </div>
    HTML;
}

/**
 * Donut de répartition par filiale, avec bascule dynamique entre 2
 * indicateurs (CA / EBITDA) sans rechargement de page — construit à partir
 * du même scorecard que le tableau "Performance par filiale" (une seule
 * source de calcul, voir ReportingService::subsidiaryScorecard()). Les
 * valeurs négatives (filiale en perte) ne sont pas représentables dans un
 * donut (pas de part négative d'un tout) : exclues du tracé, signalées
 * dans la légende.
 * @param array<int, array{subsidiary: \App\Models\Subsidiary, revenue: array, ebitda: array}> $scorecard
 */
function render_composition_donut(array $scorecard, string $chartId): string
{
    if (empty($scorecard)) {
        return '<div class="empty-state">Aucune donnée pour cette sélection.</div>';
    }

    $build = function (string $key) use ($scorecard) {
        $rows = [];
        foreach ($scorecard as $row) {
            $s = $row['subsidiary'];
            $rows[] = [
                'code' => $s->code,
                'name' => $s->name,
                'value' => round($row[$key]['actual'], 2),
                'color' => chart_subsidiary_color($s->code),
            ];
        }
        return $rows;
    };

    $datasets = [
        'revenue' => ['label' => "Chiffre d'affaires", 'rows' => $build('revenue')],
        'ebitda' => ['label' => 'EBITDA', 'rows' => $build('ebitda')],
    ];
    $dataJson = json_encode($datasets, JSON_UNESCAPED_UNICODE);

    return <<<HTML
    <div class="donut-wrap">
        <div class="donut-toggle" role="group" aria-label="Indicateur affiché">
            <button type="button" class="donut-toggle-btn is-active" data-key="revenue">Chiffre d'affaires</button>
            <button type="button" class="donut-toggle-btn" data-key="ebitda">EBITDA</button>
        </div>
        <div class="donut-body">
            <div class="chart-svg-container" style="position:relative">
                <svg viewBox="0 0 240 240" id="{$chartId}" class="donut-svg"></svg>
                <div class="chart-tooltip" style="display:none"></div>
            </div>
            <div class="donut-legend" id="{$chartId}-legend"></div>
        </div>
    </div>
    <script>
    (function() {
        var data = {$dataJson};
        var svg = document.getElementById('{$chartId}');
        var legend = document.getElementById('{$chartId}-legend');
        var tooltip = svg.parentElement.querySelector('.chart-tooltip');
        var toggle = svg.closest('.donut-wrap').querySelector('.donut-toggle');
        var cx = 120, cy = 120, rOuter = 96, rInner = 60;

        function polar(r, angle) {
            var rad = (angle - 90) * Math.PI / 180;
            return { x: cx + r * Math.cos(rad), y: cy + r * Math.sin(rad) };
        }
        function arcPath(startAngle, endAngle) {
            var so = polar(rOuter, endAngle), eo = polar(rOuter, startAngle);
            var si = polar(rInner, endAngle), ei = polar(rInner, startAngle);
            var large = (endAngle - startAngle) > 180 ? 1 : 0;
            return ['M', so.x, so.y, 'A', rOuter, rOuter, 0, large, 0, eo.x, eo.y,
                    'L', ei.x, ei.y, 'A', rInner, rInner, 0, large, 1, si.x, si.y, 'Z'].join(' ');
        }
        function fmtAmount(v) {
            return v.toLocaleString('fr-FR', {maximumFractionDigits: 0});
        }
        function fmtCompact(v) {
            var abs = Math.abs(v);
            if (abs >= 1e9) return (v / 1e9).toLocaleString('fr-FR', {maximumFractionDigits: 1}) + ' Md';
            if (abs >= 1e6) return (v / 1e6).toLocaleString('fr-FR', {maximumFractionDigits: 1}) + ' M';
            if (abs >= 1e3) return (v / 1e3).toLocaleString('fr-FR', {maximumFractionDigits: 0}) + ' K';
            return fmtAmount(v);
        }

        function draw(key) {
            var rows = data[key].rows;
            var positive = rows.filter(function(r) { return r.value > 0; });
            var excluded = rows.filter(function(r) { return r.value <= 0; });
            var total = positive.reduce(function(s, r) { return s + r.value; }, 0);

            var svgParts = [];
            var legendParts = [];
            var angle = 0;

            if (total <= 0) {
                svgParts.push('<circle cx="' + cx + '" cy="' + cy + '" r="' + rOuter + '" fill="none" stroke="#e2ddd0" stroke-width="' + (rOuter - rInner) + '"/>');
            } else {
                positive.forEach(function(r, i) {
                    var share = r.value / total;
                    var start = angle, end = angle + share * 360;
                    angle = end;
                    svgParts.push('<path d="' + arcPath(start, end) + '" fill="' + r.color + '" class="donut-slice" data-idx="' + i + '" stroke="#fcfcfb" stroke-width="1.5"><title>' + r.name + ' : ' + fmtAmount(r.value) + ' XOF (' + (share * 100).toFixed(1) + '%)</title></path>');
                });
            }

            svgParts.push('<text x="' + cx + '" y="' + (cy - 6) + '" text-anchor="middle" font-size="19" font-weight="700" fill="#241f1a">' + fmtCompact(total) + '</text>');
            svgParts.push('<text x="' + cx + '" y="' + (cy + 16) + '" text-anchor="middle" font-size="11" fill="#6b6156">' + data[key].label + '</text>');
            svg.innerHTML = svgParts.join('');

            positive.forEach(function(r) {
                var share = total > 0 ? (r.value / total * 100) : 0;
                legendParts.push('<div class="donut-legend-item"><span class="donut-legend-swatch" style="background:' + r.color + '"></span>' +
                    '<span class="donut-legend-name">' + r.code + '</span>' +
                    '<span class="donut-legend-value">' + fmtCompact(r.value) + ' &middot; ' + share.toFixed(1) + '%</span></div>');
            });
            excluded.forEach(function(r) {
                legendParts.push('<div class="donut-legend-item is-excluded"><span class="donut-legend-swatch" style="background:' + r.color + '"></span>' +
                    '<span class="donut-legend-name">' + r.code + '</span>' +
                    '<span class="donut-legend-value">' + fmtCompact(r.value) + ' (non représenté)</span></div>');
            });
            legend.innerHTML = legendParts.join('');

            svg.querySelectorAll('.donut-slice').forEach(function(el) {
                el.addEventListener('mouseenter', function(evt) {
                    var idx = parseInt(el.getAttribute('data-idx'), 10);
                    var r = positive[idx];
                    var share = total > 0 ? (r.value / total * 100) : 0;
                    tooltip.innerHTML = '<div class="chart-tooltip-title">' + r.name + '</div>' +
                        '<div class="chart-tooltip-row"><span>' + data[key].label + '</span><strong>' + fmtAmount(r.value) + '</strong></div>' +
                        '<div class="chart-tooltip-row"><span>Part</span><strong>' + share.toFixed(1) + '%</strong></div>';
                    tooltip.style.display = 'block';
                    el.setAttribute('opacity', '.82');
                });
                el.addEventListener('mousemove', function(evt) {
                    var rect = svg.parentElement.getBoundingClientRect();
                    tooltip.style.left = Math.min(evt.clientX - rect.left + 12, rect.width - 190) + 'px';
                    tooltip.style.top = Math.max(evt.clientY - rect.top - 40, 0) + 'px';
                });
                el.addEventListener('mouseleave', function() {
                    tooltip.style.display = 'none';
                    el.setAttribute('opacity', '1');
                });
            });
        }

        toggle.addEventListener('click', function(evt) {
            var btn = evt.target.closest('.donut-toggle-btn');
            if (!btn) { return; }
            toggle.querySelectorAll('.donut-toggle-btn').forEach(function(b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            draw(btn.getAttribute('data-key'));
        });

        draw('revenue');
    })();
    </script>
    HTML;
}

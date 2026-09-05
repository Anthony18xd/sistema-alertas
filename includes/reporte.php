<?php
/**
 * REPORTES — Generación de Excel (XLSX) y PDF con gráfico de uso.
 *
 * Dependencias del servidor: GD (imagepng/imagejpeg), ZipArchive, mbstring.
 * Funciones:
 *   reporte_rango_fechas()             → rango [desde, hasta] según filtros (máx. 31 días)
 *   reporte_uso_diario($pdo, $cond, $params) → serie diaria de uso de la aplicación
 *   grafico_uso_png($dias)             → recurso GD con el gráfico de barras
 *   grafico_uso_jpeg($dias)            → JPEG en memoria (para PDF)
 *   reporte_xlsx(...)                  → binario .xlsx con gráfico embebido
 *   reporte_pdf(...)                   → binario .pdf con gráfico embebido
 */

if (!function_exists('imagecreate')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Extensión GD no disponible en el servidor']);
    exit;
}

// Librería TCPDF (solo se carga al generar reportes; protegida por .htaccess)
if (!class_exists('TCPDF')) {
    require_once __DIR__ . '/tcpdf/tcpdf.php';
}

// ── Rango de fechas según filtros (desde/hasta) ────────────
function reporte_rango_fechas(?array $_post = null): array {
    $_post = $_post ?? $_POST;
    $hoy = new DateTime();
    $desde = (clone $hoy)->modify('-6 days');
    $hasta = clone $hoy;

    if (!empty($_post['fecha_desde']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_post['fecha_desde'])) {
        try { $desde = new DateTime($_post['fecha_desde']); } catch (Exception $e) {}
    }
    if (!empty($_post['fecha_hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_post['fecha_hasta'])) {
        try { $hasta = new DateTime($_post['fecha_hasta']); } catch (Exception $e) {}
    }
    if ($hasta < $desde) { $t = $desde; $desde = $hasta; $hasta = $t; }
    if ($hasta > $hoy) { $hasta = clone $hoy; }
    $min = (clone $hoy)->modify('-30 days');
    if ($desde < $min) { $desde = clone $min; }
    if ($desde > $hasta) { $desde = clone $hasta; }

    return [$desde, $hasta];
}

// ── Serie diaria de uso (respetando los mismos filtros) ────
function reporte_uso_diario(PDO $pdo, array $cond, array $params): array {
    [$desde, $hasta] = reporte_rango_fechas();
    $fechaDesde = $desde->format('Y-m-d');
    $fechaHasta = $hasta->format('Y-m-d');
    $fechaActual = $fechaDesde;

    $dias = [];
    while ($fechaActual <= $fechaHasta) {
        $dias[$fechaActual] = ['fecha' => $fechaActual, 'total' => 0, 'pendientes' => 0, 'completados' => 0];
        $fechaActual = date('Y-m-d', strtotime($fechaActual . ' +1 day'));
        if (count($dias) > 45) break;
    }

    $cond2 = $cond;
    $cond2[] = 'fecha_hora >= :__rep_desde';
    $cond2[] = 'fecha_hora <= :__rep_hasta';
    $params[':__rep_desde'] = $fechaDesde . ' 00:00:00';
    $params[':__rep_hasta'] = $fechaHasta . ' 23:59:59';

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $expr = "substr(fecha_hora, 1, 10)";
    } else {
        $expr = 'DATE(fecha_hora)';
    }
    $sql = 'SELECT ' . $expr . ' AS dia, status, COUNT(*) AS c FROM alertas';
    if ($cond2) {
        $sql .= ' WHERE ' . implode(' AND ', $cond2);
    }
    $sql .= ' GROUP BY ' . $expr . ', status ORDER BY dia ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt as $fila) {
        $dia = $fila['dia'] ?? '';
        if (!isset($dias[$dia])) continue;
        $dias[$dia]['total'] += (int)$fila['c'];
        if (($fila['status'] ?? '') === 'pendiente') {
            $dias[$dia]['pendientes'] += (int)$fila['c'];
        } else {
            $dias[$dia]['completados'] += (int)$fila['c'];
        }
    }

    return ['dias' => array_values($dias), 'desde' => $fechaDesde, 'hasta' => $fechaHasta];
}

// ── Tipografía para GD ─────────────────────────────────────
function reporte_fuente(bool $bold = false): ?string {
    $base = PHP_OS_FAMILY === 'Windows' ? 'C:\\Windows\\Fonts' : null;
    $candidatos = [];
    if ($bold) {
        $candidatos = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf',
        ];
    } else {
        $candidatos = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf',
        ];
    }
    foreach ($candidatos as $c) {
        if (is_file($c)) return $c;
    }
    return null;
}

function reporte_texto_gd($im, int $size, float $x, float $y, $color, string $text, bool $bold = false, bool $centrado = false): float {
    $fuente = reporte_fuente($bold);
    if ($fuente && function_exists('imagettftext')) {
        $w = imagettfbbox($size, 0, $fuente, $text);
        $ancho = abs($w[2] - $w[0]);
        $alto = abs($w[5] - $w[1]);
        if ($centrado) $x -= ($ancho / 2);
        imagettftext($im, (float)$size, 0, (int)round($x), (int)round($y + $alto * 0.75), $color, $fuente, $text);
        return $ancho;
    }
    $ancho = strlen($text) * ($size >= 4 ? 4 : 3);
    if ($centrado) $x -= ($ancho / 2);
    imagestring($im, $size >= 4 ? 5 : 2, (int)round($x), (int)round($y - 4), $text, $color);
    return $ancho;
}

// ── Gráfico de barras (uso de la aplicación) ───────────────
function grafico_uso_png(array $dias, int $w = 900, int $h = 330) {
    $im = imagecreatetruecolor($w, $h);
    $blanco  = imagecolorallocate($im, 255, 255, 255);
    $gris    = imagecolorallocate($im, 226, 232, 240);
    $grisTxt = imagecolorallocate($im, 100, 116, 139);
    $texto   = imagecolorallocate($im, 15, 23, 42);
    $azul    = imagecolorallocate($im, 59, 130, 246);
    $verde   = imagecolorallocate($im, 34, 197, 94);
    $ambar   = imagecolorallocate($im, 245, 158, 11);
    imagefill($im, 0, 0, $blanco);

    $padL = 64; $padR = 24; $padT = 62; $padB = 48;
    $cw = $w - $padL - $padR;
    $ch = $h - $padT - $padB;

    $mensaje = 'USO DE LA APLICACIÓN — ' . count($dias) . ' día' . (count($dias) === 1 ? '' : 's');
    reporte_texto_gd($im, 17, $padL, $padT - 52, $texto, $mensaje, true);

    // Leyenda (cuadro de color + etiqueta)
    imagefilledrectangle($im, $w - 262, $padT - 52, $w - 252, $padT - 42, $verde);
    reporte_texto_gd($im, 11, $w - 248, $padT - 46, $verde, 'Completadas');
    imagefilledrectangle($im, $w - 150, $padT - 52, $w - 140, $padT - 42, $ambar);
    reporte_texto_gd($im, 11, $w - 136, $padT - 46, $ambar, 'Pendientes');

    $totales = array_column($dias, 'total');
    $max = (int)max($totales ?: [0]);
    $maxEscala = 1;
    if ($max > 0) {
        $p = pow(10, floor(log10($max)));
        $f = $max / $p;
        if ($f <= 1) $maxEscala = $p;
        elseif ($f <= 2) $maxEscala = 2 * $p;
        elseif ($f <= 5) $maxEscala = 5 * $p;
        else $maxEscala = 10 * $p;
    }

    $n = count($dias);
    $slots = max(1, $n);
    $slot = $cw / $slots;
    $anchoBarra = min((int)($slot * 0.55), 44);
    if ($anchoBarra < 6) $anchoBarra = 6;

    // Rejilla + etiquetas del eje Y
    for ($i = 0; $i <= 4; $i++) {
        $valor = $maxEscala * $i / 4;
        $y = $padT + $ch - ($ch * $i / 4);
        imageline($im, $padL, (int)$y, $w - $padR, (int)$y, $gris);
        reporte_texto_gd($im, 10, $padL - 8, $y - 4, $grisTxt, (string)(int)$valor, false, true);
    }

    // Barras (apiladas: completadas abajo, pendientes arriba)
    foreach ($dias as $i => $d) {
        $cx = $padL + $i * $slot + $slot / 2;
        $comp = $hComp = $pend = $hPend = 0;
        if ($maxEscala > 0) {
            $comp = (int)$d['completados'];
            $pend = (int)$d['pendientes'];
            $hComp = ($comp / $maxEscala) * $ch;
            $hPend = ($pend / $maxEscala) * $ch;
        }
        $x0 = (int)($cx - $anchoBarra / 2);
        $baseY = $padT + $ch;

        if ($hComp > 0 || $hPend > 0) {
            if ($hComp > 0.5) {
                imagefilledrectangle($im, $x0, (int)($baseY - $hComp), $x0 + $anchoBarra, (int)$baseY, $verde);
            }
            if ($hPend > 0.5) {
                imagefilledrectangle($im, $x0, (int)($baseY - $hComp - $hPend), $x0 + $anchoBarra, (int)($baseY - $hComp), $ambar);
            }
            $tot = $comp + $pend;
            reporte_texto_gd($im, 11, $cx, $baseY - $hComp - $hPend - 8, $texto, (string)$tot, true, true);
        }

        // Etiqueta del eje X
        $partes = explode('-', $d['fecha']);
        $etiqueta = (int)($partes[2] ?? 0) . '/' . (int)($partes[1] ?? 0);
        $mostrar = ($n <= 7) || ($i % 2 === 0);
        if ($mostrar) {
            reporte_texto_gd($im, 10, $cx, $baseY + 10, $grisTxt, $etiqueta, false, true);
        }
    }

    if ($max === 0) {
        reporte_texto_gd($im, 15, $w / 2, $padT + $ch / 2 - 8, $grisTxt, 'Sin datos en el período', false, true);
    }
    return $im;
}

function grafico_uso_jpeg(array $dias): string {
    $im = grafico_uso_png($dias);
    ob_start();
    imagejpeg($im, null, 92);
    $data = (string)ob_get_clean();
    return $data;
}

// ── Construcción del libro Excel (.xlsx) ───────────────────
function reporte_xlsx(string $titulo, array $encabezados, array $filas, array $dias, array $resumen): string {
    $png = grafico_uso_png($dias);
    ob_start();
    imagepng($png);
    $imagen = (string)ob_get_clean();

    $xmlEnc = function (string $s): string {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };
    $colLetra = function (int $i): string {
        $letras = '';
        while ($i > 0) {
            $letras = chr(65 + (($i - 1) % 26)) . $letras;
            $i = intdiv($i - 1, 26);
        }
        return $letras;
    };
    $celda = function (string $ref, $valor, int $s = 0) use ($xmlEnc): string {
        if (is_int($valor) || is_float($valor)) {
            return '<c r="' . $ref . '"' . ($s ? ' s="' . $s . '"' : '') . '><v>' . $valor . '</v></c>';
        }
        return '<c r="' . $ref . '"' . ($s ? ' s="' . $s . '"' : '') . ' t="inlineStr"><is><t xml:space="preserve">' . $xmlEnc((string)$valor) . '</t></is></c>';
    };

    // Título del reporte (filas 1-2) y referencia del gráfico (filas 4-14)
    $row1 = '<c r="A1" s="2"><is><t xml:space="preserve">' . $xmlEnc($titulo) . '</t></is></c>';
    $subtitulo = 'Generado: ' . date('d/m/Y H:i:s') . '  |  Período: ' . $resumen['desde'] . ' a ' . $resumen['hasta'] . '  |  Usuario: ' . ($resumen['usuario'] ?? '');
    $row2 = '<c r="A2" s="0"><is><t xml:space="preserve">' . $xmlEnc($subtitulo) . '</t></is></c>';

    $sheetRows = '<row r="1">' . $row1 . '</row>';
    $sheetRows .= '<row r="2">' . $row2 . '</row>';
    $sheetRows .= '<row r="3"><c r="A3"/></row>';
    $sheetRows .= '<row r="4"><c r="A4" s="1"><is><t xml:space="preserve">GRÁFICO — USO DE LA APLICACIÓN</t></is></c></row>';

    // Filas reservadas para el gráfico (5 a 14): fijan su tamaño exacto (20 pt c/u)
    for ($i = 5; $i <= 14; $i++) {
        $sheetRows .= '<row r="' . $i . '" ht="20" customHeight="1"/>';
    }

    $iniTabla = 16;
    $filaResumen = 'ALERTAS: Total ' . $resumen['total'] . '  |  Pendientes ' . $resumen['pendientes'] . '  |  Completadas ' . $resumen['completados'];
    $sheetRows .= '<row r="' . $iniTabla . '"><c r="A' . $iniTabla . '" s="1"><is><t xml:space="preserve">' . $xmlEnc($filaResumen) . '</t></is></c></row>';
    $iniTabla++;

    // Cabecera de la tabla
    $rowing = '<row r="' . $iniTabla . '">';
    foreach ($encabezados as $j => $enc) {
        $rowing .= $celda($colLetra($j + 1) . $iniTabla, $enc, 1);
    }
    $rowing .= '</row>';
    $sheetRows .= $rowing;
    $iniTabla++;

    // Filas de datos
    foreach ($filas as $fila) {
        $r = $iniTabla;
        $html = '<row r="' . $r . '">';
        foreach ($fila as $j => $v) {
            $html .= $celda($colLetra($j + 1) . $r, $v);
        }
        $html .= '</row>';
        $sheetRows .= $html;
        $iniTabla++;
    }

    // Anchos de columna
    $colDefs = '';
    foreach ([18, 16, 16, 10, 18, 13, 13, 12, 30, 16] as $j => $w) {
        $colDefs .= '<col min="' . ($j + 1) . '" max="' . ($j + 1) . '" width="' . $w . '" customWidth="1"/>';
    }

    $rangos = '<mergeCells count="2"><mergeCell ref="A1:H1"/><mergeCell ref="A2:H2"/></mergeCells>';

    $worksheet =
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<cols>' . $colDefs . '</cols>' .
        '<sheetData>' . $sheetRows . '</sheetData>' .
        $rangos .
        '<drawing r:id="rId1"/>' .
        '</worksheet>';

    // Gráfico anclado sobre la hoja (col A, filas 5 a 14) preservando proporción
    $drawing =
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<xdr:oneCellAnchor editAs="absolute">' .
        '<xdr:from><xdr:col>0</xdr:col><xdr:colOff>95250</xdr:colOff><xdr:row>4</xdr:row><xdr:rowOff>95250</xdr:rowOff></xdr:from>' .
        '<xdr:ext cx="6927272" cy="2540000"/>' .
        '<xdr:pic>' .
        '<xdr:nvPicPr>' .
        '<xdr:cNvPr id="2" name="GraficoUso"/>' .
        '<xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr>' .
        '</xdr:nvPicPr>' .
        '<xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>' .
        '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="6927272" cy="2540000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>' .
        '</xdr:pic>' .
        '<xdr:clientData/>' .
        '</xdr:oneCellAnchor>' .
        '</xdr:wsDr>';

    $contentTypes =
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Default Extension="png" ContentType="image/png"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
        '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
        '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' .
        '</Types>';

    $rels =
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>';

    $workbook =
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets>' .
        '</workbook>';

    $workbookRels =
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
        '</Relationships>';

    $sheetRels =
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>' .
        '</Relationships>';

    $drawingRels =
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/>' .
        '</Relationships>';

    $styles =
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<fonts count="3">' .
        '<font><sz val="11"/><name val="Calibri"/></font>' .
        '<font><b/><sz val="11"/><name val="Calibri"/></font>' .
        '<font><b/><sz val="16"/><name val="Calibri"/></font>' .
        '</fonts>' .
        '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>' .
        '<borders count="1"><border/></borders>' .
        '<cellStyleXfs count="1"><xf/></cellStyleXfs>' .
        '<cellXfs count="3">' .
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
        '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' .
        '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>' .
        '</cellXfs>' .
        '</styleSheet>';

    $tmp = tempnam(sys_get_temp_dir(), 'rep_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return '';
    }
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    $zip->addFromString('xl/worksheets/sheet1.xml', $worksheet);
    $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', $sheetRels);
    $zip->addFromString('xl/drawings/drawing1.xml', $drawing);
    $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', $drawingRels);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('xl/media/image1.png', $imagen);
    $zip->close();
    $bin = (string)file_get_contents($tmp);
    @unlink($tmp);
    return $bin;
}


// ── Reporte PDF con TCPDF (calidad profesional) ────────────
class ReporteAlertaPdf extends TCPDF {
    public $frame = 'ALERTA — Sistema de Monitoreo de Emergencia';

    public function sinCreditosTcpdf(): void {
        $this->tcpdflink = false;
    }

    public function Header() {
        $logo = __DIR__ . '/../assets/img/icono.png';
        $xTxt = 12;
        if (is_file($logo)) {
            $this->Image($logo, 12, 6, 0, 7, 'PNG', '', '', true, 300, '', false, false, 0, false, false, false);
            $xTxt = 21;
        }
        $this->SetFont('freesansb', '', 12);
        $this->SetTextColor(15, 23, 42);
        $this->SetXY($xTxt, 5.5);
        $this->Cell(0, 5, 'ALERTA', 0, 1, 'L');
        $this->SetFont('freesans', '', 7.5);
        $this->SetTextColor(100, 116, 139);
        $this->SetX($xTxt);
        $this->Cell(0, 4, $this->frame, 0, 1, 'L');

        $this->SetFont('freesans', '', 7.5);
        $this->SetTextColor(100, 116, 139);
        $this->SetXY(105, 6);
        $this->Cell(0, 6, 'Generado: ' . date('d/m/Y H:i:s'), 0, 1, 'R');

        $this->SetDrawColor(59, 130, 246);
        $this->SetLineWidth(0.5);
        $this->Line(12, 16, $this->getPageWidth() - 12, 16);
    }

    public function Footer() {
        $this->SetY(-11);
        $this->SetFont('freesans', '', 7);
        $this->SetTextColor(120, 130, 150);
        $this->Cell(120, 5, 'Confidencial — uso institucional', 0, 0, 'L');
        $this->Cell(0, 5, 'Página ' . $this->getPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

function reporte_pdf(string $titulo, array $encabezados, array $filas, array $dias, array $resumen): string {
    $pdf = new ReporteAlertaPdf('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->sinCreditosTcpdf();
    $pdf->SetCreator('ALERTA — Sistema de Monitoreo');
    $pdf->SetTitle('Reporte de alertas');
    $pdf->SetMargins(12, 19, 12);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    $todoW = $pdf->getPageWidth() - 24;

    // ── Título del reporte ──
    $pdf->SetFont('freesansb', '', 16);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->SetXY(12, 21);
    $pdf->Cell(0, 8, mb_strtoupper($titulo, 'UTF-8'), 0, 1, 'L');

    $pdf->SetFont('freesans', '', 8.5);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->SetX(12);
    $pdf->Cell(0, 5, 'Período: ' . $resumen['desde'] . ' a ' . $resumen['hasta'] . '   |   Usuario: ' . ($resumen['usuario'] ?? '-'), 0, 1, 'L');

    // ── Tarjetas de resumen ──
    $tarjetas = [
        ['Total alertas',  (string)$resumen['total'],       59, 130, 246],
        ['Pendientes',     (string)$resumen['pendientes'], 245, 158, 11],
        ['Completadas',    (string)$resumen['completados'], 34, 197, 94],
        ['Dispositivos',   (string)$resumen['dispositivos'], 99, 102, 241],
    ];
    $gap = 4;
    $tw = ($todoW - 3 * $gap) / 4;
    $by = 40;
    $bh = 18;
    foreach ($tarjetas as $i => $t) {
        $x = 12 + $i * ($tw + $gap);
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetLineWidth(0.25);
        $pdf->Rect($x, $by, $tw, $bh, 'DF');
        $pdf->SetFillColor($t[2], $t[3], $t[4]);
        $pdf->Rect($x, $by, $tw, 2, 'F');
        $pdf->SetFont('freesansb', '', 15);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->SetXY($x, $by + 5.5);
        $pdf->Cell($tw, 7, $t[1], 0, 1, 'C');
        $pdf->SetFont('freesans', '', 7);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->SetX($x);
        $pdf->Cell($tw, 4, mb_strtoupper($t[0], 'UTF-8'), 0, 1, 'C');
    }

    // ── Gráfico de uso ──
    $gy = $by + $bh + 8;
    $pdf->SetFont('freesansb', '', 10.5);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->SetXY(12, $gy);
    $pdf->Cell(0, 5, 'Gráfico del uso de la aplicación', 0, 1, 'L');
    $gy += 6;

    $pngTemp = tempnam(sys_get_temp_dir(), 'chart_');
    $im = grafico_uso_png($dias);
    imagepng($im, $pngTemp);
    $imgH = $todoW * (330 / 900);
    $pdf->Image($pngTemp, 12, $gy, $todoW, $imgH, 'PNG', '', '', true, 300, '', false, false, 1, false, false, false);
    @unlink($pngTemp);
    $gy += $imgH + 8;

    // ── Tabla de alertas ──
    $esc = function (string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    };
    $filasPdf = array_slice($filas, 0, 400);
    $anchC = ['8%', '18%', '15%', '8%', '20%', '16%', '15%'];
    $encab = ['ID', 'Dispositivo', 'Número', 'Batería', 'Fecha y hora', 'Ubicación', 'Estado'];

    $html = '<table border="0.4" cellpadding="3" cellspacing="0" style="border-color:#cbd5e1;">';
    if (empty($filasPdf)) {
        $html .= '<tr><td style="font-family:freesans;font-size:9pt;color:#64748b;text-align:center;">No hay alertas con los filtros seleccionados.</td></tr>';
    } else {
        $html .= '<thead>';
        $html .= '<tr style="background-color:#1e293b;">';
        foreach ($encab as $j => $col) {
            $html .= '<th width="' . $anchC[$j] . '" style="font-family:freesansb;font-size:8pt;color:#ffffff;background-color:#1e293b;text-align:left;">' . $esc($col) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($filasPdf as $i => $fila) {
            $bg = ($i % 2) ? '#f1f5f9' : '#ffffff';
            $estado = ($fila[7] ?? '') === 'pendiente'
                ? '<span style="color:#b45309;">Pendiente</span>'
                : '<span style="color:#166534;">Completado</span>';
            $html .= '<tr style="background-color:' . $bg . ';">' .
                '<td width="8%" style="font-family:freesansb;font-size:8pt;">' . (int)$fila[0] . '</td>' .
                '<td width="18%" style="font-family:freesansb;font-size:8pt;">' . $esc((string)$fila[1]) . '</td>' .
                '<td width="15%" style="font-family:freesansb;font-size:8pt;">' . $esc((string)$fila[2]) . '</td>' .
                '<td width="8%" style="font-family:freesansb;font-size:8pt;">' . (int)$fila[3] . '%</td>' .
                '<td width="20%" style="font-family:freesansb;font-size:8pt;">' . $esc((string)$fila[4]) . '</td>' .
                '<td width="16%" style="font-family:freesansb;font-size:7pt;">' .
                    number_format((float)$fila[5], 4, '.', '') . ',' . number_format((float)$fila[6], 4, '.', '') .
                '</td>' .
                '<td width="15%" style="font-family:freesansb;font-size:8pt;">' . $estado . '</td>' .
                '</tr>';
        }
        $html .= '</tbody>';
    }
    $html .= '</table>';

    $pdf->SetY($gy);
    $pdf->writeHTML($html, true, false, true, true, '');

    // ── Notas finales ──
    $lbl = '* Columna Ubicación: latitud, longitud de la alerta.';
    if (count($filas) > 400) {
        $lbl .= '  |  Se muestran las primeras 400 de ' . count($filas) . ' alertas. Usa el Excel o el CSV para el listado completo.';
    }
    $pdf->SetFont('freesans', '', 7);
    $pdf->SetTextColor(120, 130, 150);
    $pdf->writeHTML('<div style="font-family:freesans;font-size:7pt;color:#78808f;">' . $esc($lbl) . '</div>', true, false, true, false, '');

    return $pdf->Output('', 'S');
}

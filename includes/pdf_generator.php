<?php
// includes/pdf_generator.php
// Dependency-Free Pure PHP PDF 1.4 Generator (100% compatible with XAMPP & Shared Hosting)

/**
 * Escapes ASCII text for PDF Type1 Helvetica content streams.
 */
function pdfEscapeText(string $text): string {
    // Transliterate INR symbol or unicode cleanly for Type1 Helvetica
    $clean = str_replace(['₹', '—', '•'], ['Rs. ', '-', '*'], $text);
    $clean = preg_replace('/[^\x20-\x7E]/', ' ', $clean);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $clean);
}

/**
 * Builds and outputs/returns a multi-line structured A4 PDF document.
 *
 * @param string $title Document Title
 * @param string[] $lines Ordered lines of text to render cleanly
 * @param string|null $downloadFilename If non-null, sends HTTP PDF download headers and exits
 */
function generateSimplePdf(string $title, array $lines, ?string $downloadFilename = null): string {
    $stream = "BT\n/F1 15 Tf\n48 795 Td\n(" . pdfEscapeText($title) . ") Tj\n";
    $stream .= "/F2 10 Tf\n0 -24 Td\n(Generated: " . pdfEscapeText(date('d M Y, h:i A') . ' IST') . ") Tj\n0 -16 Td\n";

    $yPos = 755;
    foreach ($lines as $line) {
        if ($yPos < 50) break;
        if (str_starts_with($line, '---')) {
            $stream .= "0 -6 Td\n(" . pdfEscapeText(str_repeat('-', 78)) . ") Tj\n0 -14 Td\n";
            $yPos -= 20;
            continue;
        }
        $wrapped = explode("\n", wordwrap((string)$line, 86, "\n", true));
        foreach ($wrapped as $w) {
            if ($yPos < 50) break;
            $stream .= "(" . pdfEscapeText($w) . ") Tj\n0 -14 Td\n";
            $yPos -= 14;
        }
    }
    $stream .= "ET\n";

    $objs = [];
    $objs[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";
    $objs[] = "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n";
    $objs[] = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >> endobj\n";
    $objs[] = "4 0 obj << /Length " . strlen($stream) . " >> stream\n" . $stream . "endstream endobj\n";
    $objs[] = "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> endobj\n";
    $objs[] = "6 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objs as $o) {
        $offsets[] = strlen($pdf);
        $pdf .= $o;
    }
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $off) {
        $pdf .= sprintf("%010d 00000 n \n", $off);
    }
    $pdf .= "trailer << /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    if ($downloadFilename !== null) {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $downloadFilename) . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }
    return $pdf;
}

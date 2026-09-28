<?php

namespace App\Support\Pdf;

use Mpdf\Mpdf;

/**
 * Shared RTL Arabic PDF styling and mPDF factory for merchant reports.
 */
final class RtlPdfDocument
{
    public static function createMpdf(): Mpdf
    {
        $tmpDir = storage_path('app/mpdf');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 18,
            'margin_footer' => 8,
            'tempDir' => $tmpDir,
            'directionality' => 'rtl',
            'autoArabic' => true,
            'autoLangToFont' => true,
            'autoScriptToLang' => true,
        ]);
    }

    public static function baseStyles(): string
    {
        return <<<'CSS'
body { font-family: xbriyaz, dejavusans, sans-serif; direction: rtl; text-align: right; color: #0f172a; font-size: 10.5pt; line-height: 1.45; }
.doc-header { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); color: #fff; padding: 18px 20px; border-radius: 8px; margin-bottom: 16px; }
.doc-header h1 { margin: 0 0 6px; font-size: 17pt; font-weight: bold; }
.doc-header .meta { font-size: 9.5pt; opacity: 0.92; }
.badge { display: inline-block; background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 999px; font-size: 9pt; margin-left: 6px; }
.disclaimer { background: #fff7ed; border: 1px solid #fdba74; border-radius: 8px; padding: 10px 12px; font-size: 9pt; color: #9a3412; margin-bottom: 14px; }
.section-title { font-size: 12pt; color: #1e40af; margin: 16px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #bfdbfe; font-weight: bold; }
.card-grid { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 8px; }
.card-grid td { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; vertical-align: top; width: 33%; }
.card-grid .k { font-size: 9pt; color: #64748b; margin-bottom: 4px; }
.card-grid .v { font-size: 13pt; font-weight: bold; color: #0f172a; }
table.data { border-collapse: collapse; width: 100%; margin-bottom: 12px; }
table.data th, table.data td { border: 1px solid #cbd5e1; padding: 7px 9px; }
table.data th { background: #eff6ff; color: #1e3a8a; font-weight: bold; font-size: 9.5pt; }
table.data tr:nth-child(even) td { background: #f8fafc; }
.ltr { direction: ltr; text-align: left; unicode-bidi: embed; }
.score-hero { text-align: center; background: #eff6ff; border: 1px solid #93c5fd; border-radius: 10px; padding: 14px; margin: 12px 0; }
.score-hero .num { font-size: 36pt; font-weight: bold; color: #1d4ed8; line-height: 1; }
.score-hero .scale { font-size: 14pt; color: #64748b; }
.bar-wrap { background: #e2e8f0; border-radius: 4px; height: 8px; overflow: hidden; }
.bar-fill { background: #2563eb; height: 8px; border-radius: 4px; }
ul.factors { margin: 6px 0 0 18px; padding: 0; }
ul.factors li { margin-bottom: 4px; font-size: 9.5pt; }
.positive li { color: #166534; }
.negative li { color: #991b1b; }
.footer-note { margin-top: 18px; font-size: 8.5pt; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 8px; }
CSS;
    }

    public static function documentShell(string $title, string $subtitle, string $bodyHtml): string
    {
        $styles = self::baseStyles();
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subtitleEsc = htmlspecialchars($subtitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>{$titleEsc}</title>
<style>{$styles}</style>
</head>
<body>
<div class="doc-header">
  <h1>{$titleEsc}</h1>
  <div class="meta">{$subtitleEsc}</div>
</div>
{$bodyHtml}
</body>
</html>
HTML;
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

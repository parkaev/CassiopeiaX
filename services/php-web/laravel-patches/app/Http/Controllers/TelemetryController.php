<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TelemetryController extends Controller
{
    public function index()
    {
        $telemetry = DB::select("SELECT id, recorded_at, voltage, temp, source_file FROM telemetry_legacy ORDER BY recorded_at DESC LIMIT 100");
        return view('telemetry', compact('telemetry'));
    }

    public function export()
    {
        $telemetry = DB::select("SELECT id, recorded_at, voltage, temp, source_file FROM telemetry_legacy ORDER BY recorded_at DESC");
        
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "telemetry_{$timestamp}.xlsx";
        
        // Create XLSX using XML (Office Open XML format)
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Telemetry" sheetId="1" r:id="rId1"/></sheets>
</workbook>';

        $sheetData = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>
<row><c t="inlineStr"><is><t>ID</t></is></c><c t="inlineStr"><is><t>Дата и время</t></is></c><c t="inlineStr"><is><t>Напряжение (V)</t></is></c><c t="inlineStr"><is><t>Температура (°C)</t></is></c><c t="inlineStr"><is><t>Файл источника</t></is></c></row>';

        foreach ($telemetry as $row) {
            $sheetData .= '<row>';
            $sheetData .= '<c t="inlineStr"><is><t>' . htmlspecialchars($row->id) . '</t></is></c>';
            $sheetData .= '<c t="inlineStr"><is><t>' . htmlspecialchars($row->recorded_at) . '</t></is></c>';
            $sheetData .= '<c t="inlineStr"><is><t>' . htmlspecialchars($row->voltage) . '</t></is></c>';
            $sheetData .= '<c t="inlineStr"><is><t>' . htmlspecialchars($row->temp) . '</t></is></c>';
            $sheetData .= '<c t="inlineStr"><is><t>' . htmlspecialchars($row->source_file) . '</t></is></c>';
            $sheetData .= '</row>';
        }
        $sheetData .= '</sheetData></worksheet>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>';

        $relsRoot = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $relsRoot);
        $zip->addFromString('xl/workbook.xml', $xml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetData);
        $zip->close();

        return response()->download($tmpFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ])->deleteFileAfterSend(true);
    }
}

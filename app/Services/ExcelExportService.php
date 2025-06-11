<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class ExcelExportService
{
    /**
     * Genera el archivo Excel de detalle de muestra con resultados de análisis.
     *
     * @param array $detailRecord Datos de la muestra principal (ahora del frontend).
     * @param array $extendedDetailRecord Datos extendidos de la muestra (ahora del frontend).
     * @param array $sampleResults Resultados del análisis.
     * @param array $resultsColumns Definición de columnas para los resultados de análisis.
     * @return array Un array con la ruta temporal ('path') y el nombre deseado ('name') del archivo XLSX generado.
     * @throws Exception
     */
    public function generateSampleDetailExcel(
        array $detailRecord,
        array $extendedDetailRecord,
        array $sampleResults,
        array $resultsColumns
    ): array { // <-- Corrección: Tipo de retorno ahora es 'array'
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Definir estilos para reusarlos
        $styles = [
            'titleSection' => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 14],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F5496']], // Azul oscuro corporativo
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1F3864']]], // Borde inferior más oscuro
            ],
            'infoLabel' => [
                'font' => ['bold' => true, 'color' => ['rgb' => '404040'], 'size' => 11], // Gris oscuro para la etiqueta
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']], // Fondo gris muy claro para agrupar
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]], // Borde suave
            ],
            'infoValue' => [
                'font' => ['bold' => false, 'color' => ['rgb' => '000000'], 'size' => 11], // Negro para el valor
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']], // Mismo fondo que la etiqueta
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
                'wrapText' => true, // Para ajustar texto largo en la celda
            ],
            'resultsTableHeader' => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12], // Letra blanca
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']], // Azul medio corporativo
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
            ],
            'resultsTableCell' => [
                'font' => ['color' => ['rgb' => '333333'], 'size' => 10], // Gris oscuro para el texto
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
            ],
            'resultsTableCellAlt' => [
                'font' => ['color' => ['rgb' => '333333'], 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6F0F9']], // Azul muy pálido para el fondo alterno
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
            ],
            'noDataMessage' => [
                'font' => ['italic' => true, 'color' => ['rgb' => '666666']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F8F8']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
            ],
        ];

        // Columnas para los paneles (usaremos 4 columnas para la disposición lado a lado)
        $numDetailCols = 4; // Col A (Label L), Col B (Value L), Col C (Label R), Col D (Value R)

        // --- Definición de campos para los paneles Izquierdo y Derecho ---
        $camposPanelIzquierdo = [
            ['field' => "solicitante", 'label' => "Cliente"],
            ['field' => "numero_identificador", 'label' => "Número Identificador"],
            ['field' => "direccion", 'label' => "Dirección"],
            ['field' => "muestreado_por", 'label' => "Muestreado por"],
            ['field' => "descripcion_muestra", 'label' => "Descripción de la Muestra"],
            ['field' => "fecha_recepcion", 'label' => "Fecha de Recepción"],
            ['field' => "fecha_inicio_analisis", 'label' => "Fecha de Inicio Análisis"],
            ['field' => "fecha_termino_analisis", 'label' => "Fecha de Término Análisis"],
            ['field' => "datalaudo", 'label' => "Fecha de Emisión"],
        ];

        $camposPanelDerecho = [
            ['field' => "codigo_muestra_cliente", 'label' => "Código Muestra Cliente"],
            ['field' => "variedad", 'label' => "Variedad"],
            ['field' => "fecha_muestreo", 'label' => "Fecha de Muestreo"],
            ['field' => "muestreador_persona", 'label' => "Muestreador"],
            ['field' => "lugar_muestreo_detail", 'label' => "Lugar de Muestreo"],
            ['field' => "nombre_productor", 'label' => "Nombre Productor"],
            ['field' => "codigo_productor", 'label' => "Código de Productor"],
            ['field' => "predio", 'label' => "Predio"],
            ['field' => "n_registro_agricola", 'label' => "N° Registro Agricola"],
            ['field' => "informacion_adicional", 'label' => "Información Adicional"],
            // Campos 'moroso', 'mrl', 'mercados', 'retailers' han sido eliminados de aquí
            // según tu última solicitud, ya que no los necesitas en el Excel.
        ];

        $maxPanelRows = max(count($camposPanelIzquierdo), count($camposPanelDerecho));
        $currentRow = 1; // Iniciar en la fila 1

        // --- 1. Título "Detalle de Muestra" ---
        $sheet->setCellValue('A' . $currentRow, 'Detalle de Muestra');
        $sheet->mergeCells('A' . $currentRow . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numDetailCols) . $currentRow);
        $sheet->getStyle('A' . $currentRow)->applyFromArray($styles['titleSection']);
        $currentRow += 2; // +1 para el título, +1 para la fila vacía

        // --- 2. Encabezados de Paneles Izquierdo y Derecho ---
        $sheet->setCellValue('A' . $currentRow, 'Información de la Muestra');
        $sheet->mergeCells('A' . $currentRow . ':B' . $currentRow);
        $sheet->getStyle('A' . $currentRow)->applyFromArray($styles['titleSection']);
        $sheet->setCellValue('C' . $currentRow, 'Otros Datos Adicionales');
        $sheet->mergeCells('C' . $currentRow . ':D' . $currentRow);
        $sheet->getStyle('C' . $currentRow)->applyFromArray($styles['titleSection']);
        $currentRow++;

        // --- 3. Contenido de los Paneles Izquierdo y Derecho Lado a Lado ---
        for ($i = 0; $i < $maxPanelRows; $i++) {
            $currentDataRow = $currentRow + $i;

            // Panel Izquierdo
            $leftLabel = $camposPanelIzquierdo[$i]['label'] ?? '';
            $leftValue = '';
            if (isset($camposPanelIzquierdo[$i])) {
                $field = $camposPanelIzquierdo[$i]['field'];
                $value = $extendedDetailRecord[$field] ?? ($detailRecord[$field] ?? null); // Usar null coalescing
                $leftValue = ($value === null || $value === "" || $value === "N/A") ? "S/INF" : $value;
            }

            // Panel Derecho
            $rightLabel = $camposPanelDerecho[$i]['label'] ?? '';
            $rightValue = '';
            if (isset($camposPanelDerecho[$i])) {
                $field = $camposPanelDerecho[$i]['field'];
                $value = $extendedDetailRecord[$field] ?? null; // Siempre buscar en extendedDetailRecord para estos campos
                $rightValue = ($value === null || $value === "" || $value === "N/A") ? "S/INF" : $value;
            }

            $sheet->setCellValue('A' . $currentDataRow, $leftLabel . (empty($leftLabel) ? '' : ':'));
            $sheet->setCellValue('B' . $currentDataRow, $leftValue);
            $sheet->setCellValue('C' . $currentDataRow, $rightLabel . (empty($rightLabel) ? '' : ':'));
            $sheet->setCellValue('D' . $currentDataRow, $rightValue);

            // Aplicar estilos a las celdas de detalle
            $sheet->getStyle('A' . $currentDataRow)->applyFromArray($styles['infoLabel']);
            $sheet->getStyle('B' . $currentDataRow)->applyFromArray($styles['infoValue']);
            $sheet->getStyle('C' . $currentDataRow)->applyFromArray($styles['infoLabel']);
            $sheet->getStyle('D' . $currentDataRow)->applyFromArray($styles['infoValue']);
        }
        $currentRow += $maxPanelRows;
        $currentRow += 2; // Espacio entre paneles y resultados

        // --- 4. Título "Resultados del Análisis" ---
        $sheet->setCellValue('A' . $currentRow, 'Resultados del Análisis');
        $sheet->mergeCells('A' . $currentRow . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($resultsColumns)) . $currentRow);
        $sheet->getStyle('A' . $currentRow)->applyFromArray($styles['titleSection']);
        $currentRow++;

        // --- 5. Cabeceras y Filas de Resultados del Análisis ---
        if (!empty($sampleResults)) {
            $headerRow = [];
            foreach ($resultsColumns as $col) {
                $headerRow[] = $col['header'];
            }
            $sheet->fromArray($headerRow, NULL, 'A' . $currentRow);
            $sheet->getStyle('A' . $currentRow . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($resultsColumns)) . $currentRow)->applyFromArray($styles['resultsTableHeader']);
            $currentRow++;

            $isEvenRow = false;
            foreach ($sampleResults as $index => $result) {
                $rowData = [];
                foreach ($resultsColumns as $col) {
                    $value = $result[$col['field']] ?? 'S/INF'; // Ya se garantiza que $result es un array en getResultadosService
                    $rowData[] = ($value === "" || $value === "N/A") ? "S/INF" : $value;
                }
                $sheet->fromArray($rowData, NULL, 'A' . $currentRow);
                $cellStyle = $isEvenRow ? $styles['resultsTableCell'] : $styles['resultsTableCellAlt'];
                $sheet->getStyle('A' . $currentRow . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($resultsColumns)) . $currentRow)->applyFromArray($cellStyle);
                $isEvenRow = !$isEvenRow;
                $currentRow++;
            }
        } else {
            $sheet->setCellValue('A' . $currentRow, 'No se encontraron resultados de análisis.');
            $sheet->mergeCells('A' . $currentRow . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($resultsColumns)) . $currentRow);
            $sheet->getStyle('A' . $currentRow)->applyFromArray($styles['noDataMessage']);
            $currentRow++;
        }

        // --- Ajustar Anchos de Columna ---
        // Anchos para las 4 columnas de detalles (A, B, C, D)
        $sheet->getColumnDimension('A')->setWidth(25); // Etiqueta Izquierda
        $sheet->getColumnDimension('B')->setWidth(40); // Valor Izquierda
        $sheet->getColumnDimension('C')->setWidth(25); // Etiqueta Derecha
        $sheet->getColumnDimension('D')->setWidth(40); // Valor Derecha

        // Anchos para las columnas de resultados
        foreach ($resultsColumns as $index => $col) {
            // Ajustar el índice de columna para que empiece después de las 4 columnas de detalles (A, B, C, D)
            // Es decir, la primera columna de resultados será la columna E (índice 4 en base 0)
            $columnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + $numDetailCols + 1);
            $sheet->getColumnDimension($columnIndex)->setAutoSize(true);
        }

        // Nombre de la hoja
        $sheet->setTitle('Detalle Muestra');

        // Generar archivo temporal
        // Usamos tempnam para crear un archivo temporal único con extensión .xlsx
        $tempFileBase = tempnam(sys_get_temp_dir(), 'excel_export_'); // Crea un nombre temporal sin extensión
        $tempFilePath = $tempFileBase . '.xlsx'; // Le añadimos la extensión correcta

        // Si tempnam crea un archivo con el nombre base, lo borramos primero antes de guardar el XLSX
        if (file_exists($tempFileBase)) {
            unlink($tempFileBase);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFilePath);

        // Nombre de archivo deseado para la descarga del usuario
        $desiredFilename = 'Resultados_Muestra_' . ($detailRecord['cdamostra'] ?? 'export') . '.xlsx';

        // Retorna la ruta del archivo temporal Y el nombre deseado
        return [
            'path' => $tempFilePath,
            'name' => $desiredFilename
        ];
    }
}

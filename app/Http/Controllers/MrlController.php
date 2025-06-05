<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use DateTime;
use DateTimeZone;

class MrlController extends Controller
{
    private $isProd;
    private $mrlUser;
    private $mrlPassword;
    private $urlLogin;
    private $urlReporteMuestra;
    private $dbConnectionName;
    private bool $downloadResponseSent = false; // Para la limpieza en finally

    public function __construct()
    {
        $this->isProd = filter_var(env('MRL_PROD_MODE', true), FILTER_VALIDATE_BOOLEAN);
        $this->mrlUser = env('MRL_USER');

        if ($this->isProd) {
            $this->mrlPassword      = env('MRL_PASS_PROD');
            $this->urlLogin         = env('MRL_LOGIN_URL_PROD');
            $this->urlReporteMuestra = env('MRL_REPORTE_URL_PROD');
        } else {
            $this->mrlPassword      = env('MRL_PASS_TEST');
            $this->urlLogin         = env('MRL_LOGIN_URL_TEST');
            $this->urlReporteMuestra = env('MRL_REPORTE_URL_TEST');
        }
        $this->dbConnectionName = env('DB_CONNECTION_LEGACY_MRL', 'mylims');
        Log::info('MrlController: Configuración inicializada.', [
            'is_prod' => $this->isProd,
            'user' => $this->mrlUser, // Solo para confirmar que se lee
            'login_url' => $this->urlLogin,
            'report_url' => $this->urlReporteMuestra,
            'db_connection' => $this->dbConnectionName
        ]);
    }

    public function getOptions()
    {
        try {
            $markets = DB::connection('onlinedata') // Asegúrate que 'onlinedata' esté bien configurada para prod/dev
                ->table('CLINK_MRL_MERCADOS')
                ->select('CDMERCADO as value', 'MERCADO as label')
                ->orderBy('MERCADO')
                ->get();

            $retailers = DB::connection('onlinedata') // Asegúrate que 'onlinedata' esté bien configurada para prod/dev
                ->table('CLINK_MRL_RETAIL')
                ->select('CDRETAIL as value', DB::raw('UPPER(RETAIL) as label'))
                ->orderBy('RETAIL')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'markets' => $markets,
                    'retailers' => $retailers,
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener opciones MRL', [
                'exception_message' => $e->getMessage(),
                'exception_trace' => substr($e->getTraceAsString(), 0, 500)
            ]);
            return response()->json([
                'success' => false,
                'message' => 'No se pudieron cargar las opciones para el informe MRL.'
            ], 500);
        }
    }

    public function generateReport(Request $request)
    {
        $validated = $request->validate([
            'market_ids' => 'nullable|array',
            'retail_ids' => 'nullable|array',
            'language'   => 'required|in:0,1',
            'sample_ids' => 'required|array|min:1',
        ]);

        $tempSessionId = Str::random(10);
        // Estas rutas son relativas al 'root' del disco de Storage por defecto (usualmente storage/app)
        $tempPdfDir = 'temp' . DIRECTORY_SEPARATOR . 'mrl_pdfs' . DIRECTORY_SEPARATOR . $tempSessionId;
        Storage::makeDirectory($tempPdfDir);
        $tempZipDir = 'temp' . DIRECTORY_SEPARATOR . 'mrl_zips' . DIRECTORY_SEPARATOR . $tempSessionId;
        Storage::makeDirectory($tempZipDir);

        $generatedPdfFiles = [];
        $baseZipNameFromDb = null;
        // $overallSuccess = true; // No se usa actualmente, el flujo se detiene si hay errores graves
        $reportResultsLog = [];
        $absoluteZipPath = null;
        $zipFilename = null;
        $this->downloadResponseSent = false;

        Log::info('MRL Interno - Iniciando generación ZIP', [
            'sample_ids' => $validated['sample_ids'],
            'prod_mode' => $this->isProd,
            'params_received' => $validated,
            'temp_pdf_dir_relative' => $tempPdfDir,
            'temp_zip_dir_relative' => $tempZipDir
        ]);

        try {
            Log::info('MRL Interno - Intentando autenticación con el servicio MRL...');
            $authDetails = $this->_loginToMrlService();
            Log::info('MRL Interno - Autenticación WS MRL completada.');

            foreach ($validated['sample_ids'] as $sampleId) {
                $sampleId = trim($sampleId);
                if (empty($sampleId)) continue;

                Log::info('MRL Interno - Procesando muestra ID: ' . $sampleId);

                $sampleDbInfo = $this->_getSampleDbInfo($sampleId);
                if (!$sampleDbInfo) {
                    $msg = "Información de BD no encontrada para muestra ID: {$sampleId}.";
                    Log::warning("MRL Interno - {$msg}");
                    $reportResultsLog[] = ['muestra' => $sampleId, 'success' => false, 'mensaje' => $msg];
                    // $overallSuccess = false; // Considerar si continuar o lanzar excepción
                    continue; // Saltar esta muestra si no hay info de BD
                }
                if (!$baseZipNameFromDb) $baseZipNameFromDb = $sampleDbInfo['nombrenew'];
                Log::info('MRL Interno - Info de BD para muestra ' . $sampleId, ['db_info' => $sampleDbInfo]);

                $currentParams = $this->_determineMrlParameters(
                    $sampleId,
                    !empty($validated['market_ids']) ? implode(',', $validated['market_ids']) : '',
                    !empty($validated['retail_ids']) ? implode(',', $validated['retail_ids']) : '',
                    $validated['language']
                );
                Log::info('MRL Interno - Parámetros determinados para muestra ' . $sampleId, ['params' => $currentParams]);

                $reportPayload = [
                    'orden_servicio' => $sampleId,
                    'mercados'       => $currentParams['mercados'],
                    'id_retailer'    => $currentParams['retail'],
                    'ingles'         => $currentParams['lng'],
                    'id_laboratorio' => $sampleDbInfo['labcode'],
                ];
                Log::info('MRL Interno - Payload para WS Reporte MRL (muestra '.$sampleId.')', $reportPayload);

                $wsResponseJson = $this->_requestMrlReportFromWs($reportPayload, $authDetails['token'], $authDetails['cookieString']);

                $pdfProcessResult = $this->_processAndSavePdfFromWsResponse(
                    $wsResponseJson, $sampleId, $sampleDbInfo['nombrenew'], $tempPdfDir, $currentParams
                );

                $reportResultsLog[] = $pdfProcessResult;
                if ($pdfProcessResult['success'] && !empty($pdfProcessResult['pdf_local_path'])) {
                    $generatedPdfFiles[] = [
                        'absolute_path'   => $pdfProcessResult['pdf_local_path'], // Esta es una ruta absoluta ya normalizada
                        'filename_in_zip' => $pdfProcessResult['pdf_filename_in_zip']
                    ];
                    Log::info('MRL Interno - PDF procesado y añadido a la lista para ZIP para muestra '.$sampleId);
                } else {
                    Log::warning('MRL Interno - Fallo al procesar PDF para muestra '.$sampleId, ['result' => $pdfProcessResult]);
                    // $overallSuccess = false; // Considerar si continuar o lanzar excepción
                }
            }

            if (empty($generatedPdfFiles)) {
                Log::error('MRL Interno - No se generaron informes PDF válidos.');
                // Devolver un JSON de error en lugar de lanzar una excepción aquí podría ser mejor para el frontend
                return response()->json([
                    'success' => false,
                    'message' => 'No se generaron informes PDF válidos para incluir en el ZIP. Revise los logs individuales de las muestras.',
                    'details' => $reportResultsLog
                ], 422); // 422 Unprocessable Entity
            }

            $zipFilename = ($baseZipNameFromDb ?: 'MRL_Informe_'.Str::random(4)) . '.zip';
            $zipRelativePath = $tempZipDir . DIRECTORY_SEPARATOR . $zipFilename;
            $absoluteZipPath = Storage::path($zipRelativePath); // Ruta absoluta del sistema de archivos

            Log::info('MRL Interno - Intentando crear archivo ZIP.', ['path_raw_from_storage' => $absoluteZipPath]);

            $zip = new ZipArchive();
            if ($zip->open($absoluteZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception('No se pudo abrir/crear el archivo ZIP: ' . $absoluteZipPath);
            }
            foreach ($generatedPdfFiles as $fileInfo) {
                // $fileInfo['absolute_path'] ya debería ser una ruta absoluta válida y normalizada
                if (file_exists($fileInfo['absolute_path'])) {
                    $zip->addFile($fileInfo['absolute_path'], $fileInfo['filename_in_zip']);
                } else {
                    Log::warning('MRL Interno - Archivo PDF no encontrado para añadir al ZIP (ya debería existir)', ['path' => $fileInfo['absolute_path']]);
                }
            }
            $zipStatus = $zip->status;
            $zip->close(); // Cierra el archivo ZIP

            if(!file_exists($absoluteZipPath) || filesize($absoluteZipPath) === 0){
                 Log::error('MRL Interno - ZipArchive::close() no generó un archivo ZIP válido o está vacío.', [
                    'path_checked' => $absoluteZipPath,
                    'zip_status_property' => $zipStatus, // zip status after operations
                    'file_exists' => file_exists($absoluteZipPath),
                    'file_size' => file_exists($absoluteZipPath) ? filesize($absoluteZipPath) : 'N/A'
                ]);
                throw new Exception('Error al finalizar la creación del archivo ZIP. El archivo no existe o está vacío: ' . basename($absoluteZipPath));
            }

            Log::info('MRL Interno - Archivo ZIP creado exitosamente por ZipArchive.', ['path' => $absoluteZipPath, 'num_files_added' => count($generatedPdfFiles)]);

            // Normalizar la ruta del archivo ZIP para asegurar compatibilidad, especialmente en Windows
            $normalizedZipPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $absoluteZipPath);
            Log::info('MRL Interno - Ruta del ZIP normalizada para descarga.', ['original' => $absoluteZipPath, 'normalized' => $normalizedZipPath]);

            // Verificación crucial justo antes de intentar la descarga
            if (!file_exists($normalizedZipPath) || !is_readable($normalizedZipPath)) {
                Log::error('MRL Interno - CRÍTICO: El archivo ZIP normalizado no existe o no es legible JUSTO ANTES de response()->download()', ['path_checked' => $normalizedZipPath]);
                throw new Exception('El archivo ZIP generado (' . basename($normalizedZipPath) . ') no pudo ser accedido para la descarga. Ruta: ' . $normalizedZipPath);
            }
            Log::info('MRL Interno - El archivo ZIP existe y es legible. Procediendo a la descarga.', ['path' => $normalizedZipPath]);

            $this->downloadResponseSent = true;
            return response()->download($normalizedZipPath, $zipFilename)->deleteFileAfterSend(true);

        } catch (Exception $e) {
            Log::error('MRL Interno - Excepción general durante la generación del informe.', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace_preview' => substr($e->getTraceAsString(), 0, 2000)
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno al generar el informe MRL: ' . $e->getMessage(),
                'details' => $reportResultsLog
            ], 500);
        } finally {
            Log::info('MRL Interno - Bloque finally: iniciando limpieza.');
            if (!empty($generatedPdfFiles)) {
                foreach($generatedPdfFiles as $fileInfo) {
                    // $fileInfo['absolute_path'] es la ruta absoluta del sistema.
                    // Para Storage::delete necesitamos la ruta relativa al disco de storage.
                    // Este método es más robusto si la raíz de Storage cambia o hay symlinks.
                    $pdfPathForStorage = Str::after($fileInfo['absolute_path'], Storage::path(''));
                    // Asegurarse que no quede un separador al inicio si Str::after no lo quita
                    $pdfPathForStorage = ltrim($pdfPathForStorage, DIRECTORY_SEPARATOR);

                    if (Storage::exists($pdfPathForStorage)) {
                        Storage::delete($pdfPathForStorage);
                        Log::info('MRL Interno - PDF temporal borrado de Storage: '.$pdfPathForStorage);
                    } else {
                        // Si Storage no lo encuentra pero el archivo existe, podría indicar un problema de configuración de Storage
                        // o que el archivo se guardó fuera del disco de Storage gestionado.
                        // $fileInfo['absolute_path'] es la ruta donde se guardó el PDF.
                        if (file_exists($fileInfo['absolute_path'])) {
                             Log::warning('MRL Interno - PDF temporal no encontrado en Storage pero existe en disco. Intentando unlink.', ['storage_path_checked' => $pdfPathForStorage, 'absolute_path_unlinked' => $fileInfo['absolute_path']]);
                             // unlink($fileInfo['absolute_path']); // Descomentar con precaución
                        } else {
                             Log::info('MRL Interno - PDF temporal ya no existe para borrar: '.$pdfPathForStorage);
                        }
                    }
                }
            }
            if (Storage::exists($tempPdfDir) && empty(Storage::allFiles($tempPdfDir)) && empty(Storage::allDirectories($tempPdfDir))) {
                 Storage::deleteDirectory($tempPdfDir);
                 Log::info('MRL Interno - Directorio temporal de PDFs borrado (estaba vacío): '.$tempPdfDir);
            } else if(Storage::exists($tempPdfDir)) {
                 Log::warning('MRL Interno - Directorio temporal de PDFs NO borrado (no estaba vacío): '.$tempPdfDir, ['files_left' => Storage::allFiles($tempPdfDir)]);
                 // Storage::deleteDirectory($tempPdfDir); // Forzar borrado si es necesario, pero investiga por qué no está vacío
            }


            if (!$this->downloadResponseSent && isset($absoluteZipPath) && file_exists($absoluteZipPath)) {
                Log::info('MRL Interno - Descarga no enviada, borrando ZIP manualmente desde finally: '.$absoluteZipPath);
                unlink($absoluteZipPath);
            }

            if (Storage::exists($tempZipDir)) {
                if (empty(Storage::allFiles($tempZipDir)) && empty(Storage::allDirectories($tempZipDir))) {
                    Storage::deleteDirectory($tempZipDir);
                    Log::info('MRL Interno - Directorio temporal de ZIPs borrado (estaba vacío): '.$tempZipDir);
                } else {
                    Log::warning('MRL Interno - Directorio temporal de ZIPs NO borrado (no estaba vacío): '.$tempZipDir, [
                        'files_left' => Storage::allFiles($tempZipDir)
                    ]);
                     // Considera borrarlo forzadamente si es seguro y deleteFileAfterSend no lo hará
                     // if ($this->downloadResponseSent) { /* Dejar que deleteFileAfterSend actúe */ }
                     // else { Storage::deleteDirectory($tempZipDir); }
                }
            }
            Log::info('MRL Interno - Fin del bloque finally.');
        }
    }

    private function _getSampleDbInfo(string $sampleId)
    {
        $sql = "
            SELECT u.idunidade, REPLACE(dbo.numero(A.CDAMOSTRA,0),'/','-') + '_mrl' as nombrenew
            FROM amostra a INNER JOIN unidade u ON u.cdunidade = a.cdunidadeneg WHERE a.cdamostra = ?
            UNION
            SELECT '36' as idunidade, REPLACE(dbo.numero(A.CDAMOSTRA,0),'/','-') + '_mrl' as nombrenew
            FROM amostra a WHERE a.cdunidadeneg = 68 AND a.cdamostra = ?
            UNION
            SELECT '53' as idunidade, REPLACE(dbo.numero(A.CDAMOSTRA,0),'/','-') + '_mrl' as nombrenew
            FROM amostra a WHERE a.cdunidadeneg = 69 AND a.cdamostra = ?
        ";
        try {
            $data = DB::connection($this->dbConnectionName)->select($sql, [$sampleId, $sampleId, $sampleId]);
            if (!empty($data)) {
                return ['labcode' => $data[0]->idunidade, 'nombrenew' => $data[0]->nombrenew];
            }
        } catch(Exception $e) {
            Log::error('MRL Interno DB - Excepción en _getSampleDbInfo para muestra '.$sampleId, [
                'error' => $e->getMessage(),
                'connection_used' => $this->dbConnectionName,
                'sql_preview' => substr($sql, 0, 200)
            ]);
            return null;
        }
        Log::warning('MRL Interno DB - No se encontró labcode/nombrenew para muestra', ['id' => $sampleId, 'connection_used' => $this->dbConnectionName]);
        return null;
    }

    private function _loginToMrlService(): array
    {
        $payload = ['email' => $this->mrlUser, 'password' => $this->mrlPassword];
        Log::info('MRL Interno - Payload de login WS MRL', ['email' => $this->mrlUser, 'url' => $this->urlLogin]);

        $response = Http::timeout(30)
                        ->withoutVerifying()
                        ->asJson()
                        ->post($this->urlLogin, $payload);

        if (!$response->successful()) {
            Log::error('MRL WS Login Failed', [
                'status' => $response->status(),
                'body' => substr($response->body(),0,500),
                'url' => $this->urlLogin
            ]);
            throw new Exception('MRL WS Login Failed: HTTP Status ' . $response->status());
        }

        $body = $response->json();
        $token = $body['token'] ?? null;

        $setCookieHeader = $response->header('Set-Cookie');
        $rawCookies = is_array($setCookieHeader) ? $setCookieHeader : (empty($setCookieHeader) ? [] : [$setCookieHeader]);

        $parsedCookieParts = [];
        foreach($rawCookies as $cookieStr) {
            if(empty(trim($cookieStr))) continue;
            $parts = explode(';', $cookieStr);
            $cookieMainPart = trim($parts[0]);
            if(!empty($cookieMainPart)){
                 $parsedCookieParts[] = $cookieMainPart;
                if (Str::startsWith($cookieMainPart, 'ARRAffinity=')) {
                    $parsedCookieParts[] = Str::replaceFirst('ARRAffinity=', 'ARRAffinitySameSite=', $cookieMainPart);
                }
            }
        }
        $cookieString = implode('; ', array_unique(array_filter($parsedCookieParts)));

        if (!$token || empty($cookieString)) {
            Log::error('MRL WS Login: Token or Cookie not found in response.', [
                'response_body_preview' => substr($response->body(),0,500),
                'token_found' => !empty($token),
                'cookie_string_generated' => $cookieString,
                'raw_set_cookie_header' => $setCookieHeader
            ]);
            throw new Exception('MRL WS Login: Token or Cookie not found in response.');
        }
        Log::info('MRL Interno - Token y Cookie obtenidos de WS MRL.', ['token_present' => !empty($token), 'cookie_str_len' => strlen($cookieString)]);
        return ['token' => $token, 'cookieString' => $cookieString];
    }

    private function _determineMrlParameters(string $sampleId, string $requestedMarkets, string $requestedRetail, string $requestedLng)
    {
        $markets = $requestedMarkets;
        $retail = $requestedRetail;
        $lng = $requestedLng;

        if (empty($markets)) {
            Log::info('MRL Interno - Mercados no proporcionados, consultando fallback en BD para muestra: ' . $sampleId);
            $q = "SELECT TOP 1
                        onlinedata.dbo.mrl_mercados(?) as mercados,
                        onlinedata.dbo.mrl_retail(?) as retail,
                        (SELECT descopcao FROM opcoesinfo WHERE cdinfo = 781 AND nmopcao = dbo.infos(?,781)) as lng
                    FROM amostra WHERE cdamostra = ?";

            try {
                $fallbackData = DB::connection($this->dbConnectionName)->select($q, [$sampleId, $sampleId, $sampleId, $sampleId]);
                if (!empty($fallbackData) && isset($fallbackData[0])) {
                    $dbResult = $fallbackData[0];
                    if (!empty($dbResult->mercados)) $markets = $dbResult->mercados;
                    $retail = $dbResult->retail ?? $retail; // Puede ser string vacía, null la mantiene
                    if (isset($dbResult->lng) && $dbResult->lng !== null) $lng = (string)$dbResult->lng; // Asegurar que sea string '0' o '1'

                    Log::info('MRL Interno - Fallback de BD para muestra ' . $sampleId . ' recuperado.', (array)$dbResult);
                } else {
                    Log::info('MRL Interno - Fallback de BD no encontró datos para ' . $sampleId);
                }
            } catch (Exception $e) {
                Log::error('MRL Interno - Error en consulta fallback de mercados/retail/lng para ' . $sampleId, [
                    'error' => $e->getMessage(),
                    'connection_used' => $this->dbConnectionName
                ]);
            }
        }

        if (empty($markets)) {
            throw new Exception('MRL Interno Error: La muestra ' . $sampleId . ' no tiene información de mercados y no se proporcionó ni se encontró en fallback.');
        }
        return ['mercados' => $markets, 'retail' => $retail, 'lng' => (string)$lng]; // Asegurar que Lng sea string
    }

    private function _requestMrlReportFromWs(array $payload, string $token, string $cookieString): array
    {
        Log::info('MRL Interno - Solicitando informe a WS MRL.', ['url' => $this->urlReporteMuestra, 'payload' => $payload]); // Log payload completo

        $response = Http::withHeaders(['Authorization' => $token, 'Cookie' => $cookieString])
                        ->withoutVerifying()
                        ->timeout(120)
                        ->asForm()
                        ->post($this->urlReporteMuestra, $payload);

        if (!$response->successful()) {
            Log::error('MRL WS Report Request Failed para muestra '.$payload['orden_servicio'], [
                'status' => $response->status(),
                'body_preview' => substr($response->body(),0,1000), // Más preview del body
                'url' => $this->urlReporteMuestra,
                'payload_sent' => $payload
            ]);
            throw new Exception('MRL WS Report Request Failed para muestra '.$payload['orden_servicio'].': HTTP Status ' . $response->status() . '. Response: ' . substr($response->body(),0,200));
        }

        $rawBody = $response->body();
        $cleanedBody = preg_replace('/[[:cntrl:]]/', '', $rawBody);
        $cleanedBody = str_replace('}, ]', '}]', $cleanedBody);

        $jsonDecoded = json_decode($cleanedBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('MRL Interno - Error decodificando JSON de WS MRL para muestra '.$payload['orden_servicio'], [
                'json_error' => json_last_error_msg(),
                'raw_body_preview' => substr($rawBody,0, 500),
                'cleaned_body_preview' => substr($cleanedBody,0,500)
            ]);
            throw new Exception('Respuesta inválida (JSON malformado) del WS MRL para muestra '.$payload['orden_servicio']);
        }
        return $jsonDecoded;
    }

    private function _processAndSavePdfFromWsResponse(
        array $wsReportJson, string $sampleId, string $pdfBaseName, string $tempPdfDirRelative, array $currentParams
    ): array {
        $logJsonPath = 'logs' . DIRECTORY_SEPARATOR . 'mrl_service_responses' . DIRECTORY_SEPARATOR . $sampleId . '_' . $this->_getCurrentDateFormatted() . '.json';
        Storage::put($logJsonPath, json_encode($wsReportJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (empty($wsReportJson['orden_servicio']) || empty($wsReportJson['pdf'])) {
            $errMsg = $wsReportJson['mensaje'] ?? ($wsReportJson['orden_servicio'] ?? 'Respuesta de WS MRL incompleta, sin PDF o sin orden_servicio.');
            Log::warning('MRL Interno - WS MRL no devolvió PDF o datos cruciales para muestra '.$sampleId, [
                'response_summary' => [
                    'has_orden_servicio' => !empty($wsReportJson['orden_servicio']),
                    'has_pdf' => !empty($wsReportJson['pdf']),
                    'mensaje' => $wsReportJson['mensaje'] ?? 'N/A'
                ]
            ]);
            return ['success' => false, 'muestra' => $sampleId, 'mensaje' => $errMsg, 'pdf_local_path' => null, 'pdf_filename_in_zip' => null];
        }

        $pdfBase64 = str_replace('data:application/pdf;base64,', '', $wsReportJson['pdf']);
        $pdfDecoded = base64_decode($pdfBase64);

        if ($pdfDecoded === false || empty($pdfDecoded)) {
            Log::error('MRL Interno - Fallo al decodificar PDF base64 o PDF vacío para muestra '.$sampleId);
            return ['success' => false, 'muestra' => $sampleId, 'mensaje' => 'Fallo al procesar el contenido del PDF (decodificación o vacío).', 'pdf_local_path' => null, 'pdf_filename_in_zip' => null];
        }

        $pdfFilenameInZip = $pdfBaseName . '.pdf';
        $relativePdfPath = $tempPdfDirRelative . DIRECTORY_SEPARATOR . $pdfFilenameInZip;

        Storage::put($relativePdfPath, $pdfDecoded);
        // Usar Storage::path y luego normalizar para asegurar la ruta del sistema de archivos correcta
        $absolutePdfPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, Storage::path($relativePdfPath));

        if (!file_exists($absolutePdfPath) || filesize($absolutePdfPath) === 0) {
            Log::error('MRL Interno - PDF guardado localmente no existe o está vacío.', ['path_checked' => $absolutePdfPath, 'relative_path_used_for_storage_put' => $relativePdfPath]);
            return ['success' => false, 'muestra' => $sampleId, 'mensaje' => 'Error al guardar el archivo PDF en el servidor.', 'pdf_local_path' => null, 'pdf_filename_in_zip' => null];
        }
        Log::info('MRL Interno - PDF guardado localmente para muestra '.$sampleId, ['path' => $absolutePdfPath]);

        try {
            $comparativaMercados = json_encode($wsReportJson['cumplimiento_mercaos'] ?? [], JSON_UNESCAPED_UNICODE);
            $comparativaRetailData = [];
            if (isset($wsReportJson['retail'])) {
                $comparativaRetailData['retail'] = $wsReportJson['retail'];
                $comparativaRetailData['resultados'] = $wsReportJson['cumplimiento_retail'] ?? [];
                $comparativaRetailData['totales'] = $wsReportJson['totales_retailer'] ?? [];
            }

            DB::connection($this->dbConnectionName)
                ->table('onlinedata.dbo.CLINK_MRL')
                ->where('cdamostra', $wsReportJson['orden_servicio'])
                ->where('TEST', $this->isProd ? 1 : 0)
                ->update([
                    'RESULTADO' => $comparativaMercados,
                    'RETAIL'    => json_encode($comparativaRetailData, JSON_UNESCAPED_UNICODE),
                    'FECHA'     => now(),
                    'MERCADOS'  => $currentParams['mercados'],
                    'RETAILERS' => $currentParams['retail'],
                    'IDIOMA'    => $currentParams['lng']
                ]);
            Log::info('MRL Interno - BD actualizada para muestra', ['sample_id' => $sampleId]);
        } catch (Exception $e) {
            Log::error('MRL Interno - Fallo al actualizar BD onlinedata.dbo.CLINK_MRL', [
                'sample_id' => $sampleId,
                'error' => $e->getMessage(),
                'connection_used' => $this->dbConnectionName
            ]);
        }

        return [
            'success'               => true,
            'muestra'               => $sampleId,
            'orden_servicio'        => $wsReportJson['orden_servicio'],
            'pdf_local_path'        => $absolutePdfPath, // Ruta absoluta y normalizada
            'pdf_filename_in_zip'   => $pdfFilenameInZip,
            'mensaje'               => 'Informe PDF para ' . $sampleId . ' procesado.'
        ];
    }

    private function _getCurrentDateFormatted(): string
    {
        return (new DateTime("now", new DateTimeZone('America/Sao_Paulo')))->format("d-m-y_H_i");
    }
}

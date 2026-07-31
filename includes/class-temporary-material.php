<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Temporary_Material {
    public const MAX_STORED_CHARS = 120000;
    public const MAX_PROMPT_CHARS = 55000;
    public const CHUNK_SIZE = 18000;
    public const MAX_CHUNKS = 7;
    public const MAX_FILE_SIZE = 6291456; // 6 MB

    public static function collect_from_request(array $workflow): array {
        $warnings = isset($workflow['temp_material_warnings']) && is_array($workflow['temp_material_warnings']) ? $workflow['temp_material_warnings'] : [];
        $previous_file_error = trim((string) ($workflow['temp_material_file_error'] ?? ''));
        if ($previous_file_error !== '') {
            $warnings = array_values(array_filter($warnings, static fn($warning) => trim((string) $warning) !== $previous_file_error));
        }
        $text = isset($_POST['temp_material_text']) ? self::clean_text((string) wp_unslash($_POST['temp_material_text'])) : (string) ($workflow['temp_material_text'] ?? '');
        $filename = (string) ($workflow['temp_material_filename'] ?? '');

        if (!empty($_POST['temp_material_remove_file']) || !empty($_POST['temp_material_clear_file_error'])) {
            $text = self::strip_appended_file_material($text);
            $filename = '';
            $workflow['temp_material_file_status'] = 'none';
            $workflow['temp_material_file_error'] = '';
            $workflow['temp_material_rejected_filename'] = '';
            $workflow['temp_material_file_chars'] = 0;
        }

        if (!empty($_FILES['temp_material_file']) && is_array($_FILES['temp_material_file'])) {
            $upload = $_FILES['temp_material_file'];
            if (!empty($upload['name']) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $extracted = self::extract_uploaded_file($upload);
                $file_label = sanitize_file_name((string) ($upload['name'] ?? 'material'));
                if (empty($extracted['ok'])) {
                    $message = sanitize_text_field((string) ($extracted['error'] ?? 'No se pudo procesar el archivo temporal.'));
                    $warnings[] = $message;
                    $workflow['temp_material_file_status'] = 'error';
                    $workflow['temp_material_file_error'] = $message;
                    $workflow['temp_material_rejected_filename'] = $file_label;
                    $workflow['temp_material_file_chars'] = 0;
                } else {
                    // Reemplazo real antes de generar: elimina el bloque adjunto anterior y conserva el texto pegado.
                    $text = self::strip_appended_file_material($text);
                    $addition = "\n\n--- MATERIAL ADJUNTO TEMPORAL: {$file_label} ---\n" . (string) ($extracted['text'] ?? '');
                    $text = trim($text . $addition);
                    $filename = $file_label;
                    $workflow['temp_material_file_status'] = 'ready';
                    $workflow['temp_material_file_error'] = '';
                    $workflow['temp_material_rejected_filename'] = '';
                    $workflow['temp_material_file_chars'] = mb_strlen((string) ($extracted['text'] ?? ''));
                    if (!empty($extracted['warning'])) {
                        $warnings[] = sanitize_text_field((string) $extracted['warning']);
                    }
                }
            }
        }

        $text = self::limit_text($text, self::MAX_STORED_CHARS);
        $workflow['temp_material_text'] = $text;
        $workflow['temp_material_filename'] = $filename;
        $workflow['temp_material_hash'] = self::hash($text);
        $workflow['temp_material_warnings'] = array_values(array_unique(array_filter(array_map('sanitize_text_field', $warnings))));

        return $workflow;
    }

    public static function has_blocking_file_error(array $workflow): bool {
        return (string) ($workflow['temp_material_file_status'] ?? '') === 'error'
            && trim((string) ($workflow['temp_material_file_error'] ?? '')) !== '';
    }

    public static function blocking_file_error(array $workflow): string {
        return self::has_blocking_file_error($workflow)
            ? trim((string) ($workflow['temp_material_file_error'] ?? ''))
            : '';
    }

    private static function strip_appended_file_material(string $text): string {
        $marker = "\n\n--- MATERIAL ADJUNTO TEMPORAL:";
        $position = mb_strpos($text, $marker);
        if ($position === false) {
            return trim($text);
        }
        return trim(mb_substr($text, 0, $position));
    }

    public static function has_material(array $workflow): bool {
        return trim((string) ($workflow['temp_material_text'] ?? '')) !== '';
    }

    public static function hash(string $text): string {
        $text = trim($text);
        return $text === '' ? '' : hash('sha256', $text);
    }

    public static function prompt_excerpt(array $workflow): string {
        $text = trim((string) ($workflow['temp_material_text'] ?? ''));
        if ($text === '') {
            return '';
        }
        return self::limit_text($text, self::MAX_PROMPT_CHARS);
    }

    public static function chunks(string $text): array {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $text = self::limit_text($text, self::CHUNK_SIZE * self::MAX_CHUNKS);
        $chunks = [];
        $length = mb_strlen($text);
        for ($offset = 0; $offset < $length; $offset += self::CHUNK_SIZE) {
            $chunks[] = mb_substr($text, $offset, self::CHUNK_SIZE);
            if (count($chunks) >= self::MAX_CHUNKS) {
                break;
            }
        }
        return $chunks;
    }

    public static function clean_text(string $text): string {
        $text = wp_check_invalid_utf8($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = wp_strip_all_tags((string) $text, true);
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
        $text = preg_replace('/[ \t]+/', ' ', (string) $text);
        $text = preg_replace('/\n{4,}/', "\n\n\n", (string) $text);
        return trim((string) $text);
    }

    public static function limit_text(string $text, int $max_chars): string {
        $text = trim($text);
        if ($max_chars > 0 && mb_strlen($text) > $max_chars) {
            $text = mb_substr($text, 0, $max_chars) . "\n\n[Material de apoyo recortado automáticamente para mantener estable el proceso.]";
        }
        return $text;
    }

    private static function extract_uploaded_file(array $file): array {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'text' => '', 'error' => self::upload_error_message($error)];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            return ['ok' => false, 'text' => '', 'error' => 'El archivo temporal está vacío.'];
        }
        if ($size > self::MAX_FILE_SIZE) {
            return ['ok' => false, 'text' => '', 'error' => 'El archivo temporal supera 6 MB. Reemplázalo por otra versión o continúa solo con el texto pegado.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'text' => '', 'error' => 'No se pudo leer el archivo temporal subido.'];
        }
        $name = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['txt', 'md', 'markdown', 'html', 'htm', 'csv', 'docx', 'pdf'];
        if (!in_array($ext, $allowed, true)) {
            return ['ok' => false, 'text' => '', 'error' => 'Formato no soportado para material temporal. Usa TXT, MD, DOCX, HTML, CSV o PDF simple.'];
        }

        $text = '';
        $warning = '';
        if (in_array($ext, ['txt', 'md', 'markdown', 'html', 'htm', 'csv'], true)) {
            $raw = file_get_contents($tmp);
            $text = self::clean_text((string) $raw);
        } elseif ($ext === 'docx') {
            $text = self::extract_docx_text($tmp);
            if ($text === '') {
                return ['ok' => false, 'text' => '', 'error' => 'No se pudo extraer texto del DOCX temporal. Reemplázalo o pega el contenido en Material de apoyo.'];
            }
        } elseif ($ext === 'pdf') {
            $raw = (string) file_get_contents($tmp);
            $text = self::extract_pdf_text_basic($raw);
            if (mb_strlen($text) < 250) {
                return ['ok' => false, 'text' => '', 'error' => 'El PDF temporal no entregó texto suficiente. Reemplázalo por una versión con texto o pega el contenido manualmente.'];
            }
        }

        $text = self::limit_text($text, self::MAX_STORED_CHARS);
        if (trim($text) === '') {
            return ['ok' => false, 'text' => '', 'error' => 'El archivo temporal no contiene texto legible.'];
        }
        return ['ok' => true, 'text' => $text, 'warning' => $warning];
    }

    private static function extract_docx_text(string $path): string {
        if (!class_exists('ZipArchive')) {
            return '';
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!is_string($xml) || $xml === '') {
            return '';
        }
        $xml = preg_replace('/<w:tab\/>/i', ' ', $xml);
        $xml = preg_replace('/<\/w:p>/i', "\n", (string) $xml);
        $xml = preg_replace('/<\/w:tr>/i', "\n", (string) $xml);
        return self::clean_text($xml);
    }

    private static function extract_pdf_text_basic(string $raw): string {
        $pieces = [];
        if (preg_match_all('/\((?:\\.|[^\\)])*\)\s*Tj/s', $raw, $matches)) {
            foreach ($matches[0] as $match) {
                if (preg_match('/\(((?:\\.|[^\\)])*)\)\s*Tj/s', $match, $m)) {
                    $pieces[] = self::decode_pdf_string((string) $m[1]);
                }
            }
        }
        if (preg_match_all('/\[((?:.|\n)*?)\]\s*TJ/s', $raw, $matches)) {
            foreach ($matches[1] as $arrayBody) {
                if (preg_match_all('/\((?:\\.|[^\\)])*\)/s', $arrayBody, $parts)) {
                    $line = '';
                    foreach ($parts[0] as $part) {
                        $line .= self::decode_pdf_string(trim($part, '()')) . ' ';
                    }
                    $pieces[] = $line;
                }
            }
        }
        $text = implode("\n", $pieces);
        $text = preg_replace('/\s+/', ' ', (string) $text);
        return self::clean_text($text);
    }

    private static function decode_pdf_string(string $value): string {
        $value = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $value);
        $value = preg_replace('/\\([nrtbf])/', ' ', (string) $value);
        return $value;
    }

    private static function upload_error_message(int $error): string {
        $map = [
            UPLOAD_ERR_INI_SIZE => 'El archivo supera el límite permitido por el servidor.',
            UPLOAD_ERR_FORM_SIZE => 'El archivo supera el límite permitido por el formulario.',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente. Intenta de nuevo.',
            UPLOAD_ERR_NO_TMP_DIR => 'No existe carpeta temporal en el servidor.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo temporal en el servidor.',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP bloqueó la subida del archivo.',
        ];
        return $map[$error] ?? 'No se pudo procesar el archivo temporal.';
    }
}

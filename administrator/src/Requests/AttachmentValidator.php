<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Requests;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Accepts only JPG, PNG, PDF, XLSX and DOCX files.
 *
 * A file must have an allowed extension AND content that really is that type (checked from the
 * file's bytes, not from the browser's MIME type). XLSX/DOCX must be real Office Open XML
 * packages. The SDP content type is chosen from the checked type.
 *
 * Limits (enforced here on the server; the form's checks are only a convenience):
 * at most MAX_FILES files, each at most MAX_FILE_BYTES, together at most MAX_TOTAL.
 * The file count includes every file sent, valid or not, and is checked before any file is read.
 */
final class AttachmentValidator
{
    public const MAX_FILES      = 3;
    public const MAX_FILE_BYTES = 1 * 1024 * 1024;
    public const MAX_TOTAL      = self::MAX_FILES * self::MAX_FILE_BYTES;

    /** extension => content type sent to SDP */
    public const TYPES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'pdf'  => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /** @var \Closure(string): bool */
    private \Closure $isUploaded;

    /**
     * @param  (\Closure(string): bool)|null  $isUploaded  Defaults to is_uploaded_file(); injected for tests
     */
    public function __construct(?\Closure $isUploaded = null)
    {
        $this->isUploaded = $isUploaded ?? static fn (string $path): bool => is_uploaded_file($path);
    }

    /**
     * @param  list<array{name?: mixed, tmp_name?: mixed, error?: mixed, size?: mixed}>  $files  As in $_FILES (normalised)
     *
     * @return list<Attachment>
     *
     * @throws InvalidRequestInput
     */
    public function validate(array $files): array
    {
        $accepted = [];
        $errors   = [];
        $total    = 0;

        // Count every file actually sent (not just the valid ones) before touching any of them,
        // so a crafted POST with many files is refused without reading their content.
        $sent = \count(array_filter(
            $files,
            static fn ($f): bool => \is_array($f) && (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ));

        if ($sent > self::MAX_FILES) {
            throw new InvalidRequestInput(['COM_SERVICEDESKPLUS_ATTACH_ERR_COUNT']);
        }

        foreach ($files as $file) {
            if (!\is_array($file)) {
                continue;
            }

            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                $errors['COM_SERVICEDESKPLUS_ATTACH_ERR_SIZE'] = true;

                continue;
            }

            $path = (string) ($file['tmp_name'] ?? '');

            if ($error !== UPLOAD_ERR_OK || $path === '' || !($this->isUploaded)($path) || !is_file($path)) {
                $errors['COM_SERVICEDESKPLUS_ATTACH_ERR_UPLOAD'] = true;

                continue;
            }

            // The size of the stored file is used, never the size claimed by the browser.
            clearstatcache(true, $path);
            $size = (int) filesize($path);

            if ($size <= 0) {
                $errors['COM_SERVICEDESKPLUS_ATTACH_ERR_EMPTY'] = true;

                continue;
            }

            if ($size > self::MAX_FILE_BYTES) {
                $errors['COM_SERVICEDESKPLUS_ATTACH_ERR_SIZE'] = true;

                continue;
            }

            $name      = self::safeName((string) ($file['name'] ?? ''));
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!isset(self::TYPES[$extension]) || !self::contentMatches($extension, $path)) {
                $errors['COM_SERVICEDESKPLUS_ATTACH_ERR_TYPE'] = true;

                continue;
            }

            $total     += $size;
            $accepted[] = new Attachment($name, self::TYPES[$extension], $path, $size);
        }

        if (\count($accepted) > self::MAX_FILES) {
            $errors['COM_SERVICEDESKPLUS_ATTACH_ERR_COUNT'] = true;
        }

        if ($total > self::MAX_TOTAL) {
            $errors['COM_SERVICEDESKPLUS_ATTACH_ERR_TOTAL'] = true;
        }

        if ($errors !== []) {
            throw new InvalidRequestInput(array_keys($errors));
        }

        return $accepted;
    }

    /**
     * Values for the placeholders in the attachment messages: %1$d files, %2$d MB each, %3$d MB in total.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function limitsForMessages(): array
    {
        return [self::MAX_FILES, intdiv(self::MAX_FILE_BYTES, 1048576), intdiv(self::MAX_TOTAL, 1048576)];
    }

    /**
     * Base name only, no control or path characters, at most 120 characters, extension kept.
     */
    public static function safeName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\x7F<>:"\/\\\\|?*]+/u', '_', $name) ?? '';

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base      = trim(pathinfo($name, PATHINFO_FILENAME), " .\t");

        if ($base === '') {
            $base = 'attachment';
        }

        $base = mb_substr($base, 0, 100);

        return $extension !== '' ? $base . '.' . strtolower($extension) : $base;
    }

    /**
     * Check the file's real content against its extension.
     */
    public static function contentMatches(string $extension, string $path): bool
    {
        $head = (string) @file_get_contents($path, false, null, 0, 8);

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return str_starts_with($head, "\xFF\xD8\xFF");

            case 'png':
                return str_starts_with($head, "\x89PNG\r\n\x1A\n");

            case 'pdf':
                return str_starts_with($head, '%PDF-');

            case 'xlsx':
            case 'docx':
                if (!str_starts_with($head, "PK\x03\x04")) {
                    return false;
                }

                // ZIP file names are stored uncompressed, so the package parts can be found as text.
                $content = (string) @file_get_contents($path);
                $part    = $extension === 'xlsx' ? 'xl/workbook.xml' : 'word/document.xml';

                return str_contains($content, '[Content_Types].xml') && str_contains($content, $part)
                    && !str_contains($content, 'vbaProject.bin');
        }

        return false;
    }
}

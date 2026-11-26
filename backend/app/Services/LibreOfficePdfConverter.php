<?php

namespace App\Services;

use App\Contracts\PdfConverterInterface;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class LibreOfficePdfConverter implements PdfConverterInterface
{
    public function isAvailable(): bool
    {
        $binary = (string) config('coordina.libreoffice_binary', 'soffice');

        if (file_exists($binary)) {
            return true;
        }

        return (new ExecutableFinder())->find($binary) !== null;
    }

    public function convertDocxToPdf(string $docxFullPath, string $outputDirectory): string
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('LibreOffice (soffice) tidak tersedia untuk konversi DOCX ke PDF.');
        }

        if (! file_exists($docxFullPath) || ! is_readable($docxFullPath)) {
            throw new RuntimeException('File DOCX sumber tidak ditemukan atau tidak dapat dibaca.');
        }

        if (! is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0775, true);
        }

        $suffix = bin2hex(random_bytes(8));
        $profileDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "coordina_lo_profile_{$suffix}";
        $stagingDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "coordina_lo_out_{$suffix}";
        mkdir($profileDir, 0700, true);
        mkdir($stagingDir, 0700, true);

        try {
            $userInstallUri = 'file:///' . ltrim(str_replace('\\', '/', $profileDir), '/');
            $binary = (string) config('coordina.libreoffice_binary', 'soffice');

            $process = new Process([
                $binary,
                '-env:UserInstallation=' . $userInstallUri,
                '--headless',
                '--norestore',
                '--nolockcheck',
                '--nodefault',
                '--nofirststartwizard',
                '--convert-to',
                'pdf',
                '--outdir',
                $stagingDir,
                $docxFullPath,
            ]);

            $process->setTimeout(60);

            try {
                $process->run();
            } catch (ProcessTimedOutException $e) {
                throw new RuntimeException('Konversi PDF melebihi batas waktu (timeout 60 detik).', 0, $e);
            }

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Konversi PDF gagal: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
            }

            $pdfFilename = pathinfo($docxFullPath, PATHINFO_FILENAME) . '.pdf';
            $stagedPdf = $stagingDir . DIRECTORY_SEPARATOR . $pdfFilename;

            if (! file_exists($stagedPdf)) {
                throw new RuntimeException('File PDF hasil konversi LibreOffice tidak ditemukan.');
            }

            $size = filesize($stagedPdf);
            if ($size === false || $size <= 0) {
                throw new RuntimeException('File PDF hasil konversi berukuran 0 byte.');
            }

            $header = @file_get_contents($stagedPdf, false, null, 0, 5);
            if ($header !== '%PDF-') {
                throw new RuntimeException('Output konversi bukan dokumen PDF yang valid.');
            }

            $finalPdf = $outputDirectory . DIRECTORY_SEPARATOR . $pdfFilename;
            if (! @rename($stagedPdf, $finalPdf)) {
                if (! @copy($stagedPdf, $finalPdf)) {
                    throw new RuntimeException('Gagal memindahkan file PDF hasil konversi ke direktori arsip.');
                }
            }

            @chmod($finalPdf, 0644);

            return $finalPdf;
        } finally {
            $this->deleteDirectoryRecursively($profileDir);
            $this->deleteDirectoryRecursively($stagingDir);
        }
    }

    private function deleteDirectoryRecursively(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = @scandir($dir);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($path)) {
                    $this->deleteDirectoryRecursively($path);
                } else {
                    @unlink($path);
                }
            }
        }

        @rmdir($dir);
    }
}

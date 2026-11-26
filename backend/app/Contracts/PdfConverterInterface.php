<?php

namespace App\Contracts;

interface PdfConverterInterface
{
    /**
     * Convert a DOCX file on disk to PDF inside $outputDirectory and return the generated PDF path.
     */
    public function convertDocxToPdf(string $docxFullPath, string $outputDirectory): string;

    public function isAvailable(): bool;
}


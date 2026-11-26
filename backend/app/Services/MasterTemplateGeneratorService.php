<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class MasterTemplateGeneratorService
{
    public const REQUIRED_PLACEHOLDERS = [
        'mpk_logo',
        'requesting_org_logo',
        'letter_date',
        'letter_number',
        'recipient_org',
        'reason',
        'requester_clause',
        'borrow_date',
        'facility_name',
        'time_range',
        'mpk_signature',
        'mpk_chair_name',
        'mpk_chair_id',
        'right_chair_title',
        'right_school_line',
        'right_signature',
        'right_chair_name_line',
        'right_chair_id_line',
        'bottom_chair_title',
        'bottom_school_line',
        'bottom_signature',
        'bottom_chair_name_line',
        'bottom_chair_id_line',
    ];

    public function generateDefaultTemplate(string $relativePath = 'templates/default_smkn4_master.docx'): string
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'marginTop' => 900,
            'marginBottom' => 900,
            'marginLeft' => 1100,
            'marginRight' => 1100,
        ]);

        // Letterhead table: MPK Logo (left), School Header (center), Requesting Org Logo (right)
        $headerTable = $section->addTable(['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 60]);
        $headerTable->addRow();
        $headerTable->addCell(1700)->addText('${mpk_logo}', ['size' => 9], ['alignment' => Jc::CENTER]);

        $centerCell = $headerTable->addCell(6200);
        $centerCell->addText('MAJELIS PERWAKILAN KELAS', ['bold' => true, 'size' => 13], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $centerCell->addText('(MPK)', ['bold' => true, 'size' => 13], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $centerCell->addText('SMK NEGERI 4 PADALARANG', ['bold' => true, 'size' => 13], ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $centerCell->addText('Jalan Raya Padalarang No. 451 Kode Pos: 40553.', ['size' => 10], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $centerCell->addText('Telp. (022) 6805406', ['size' => 10], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

        $headerTable->addCell(1700)->addText('${requesting_org_logo}', ['size' => 9], ['alignment' => Jc::CENTER]);

        // Horizontal divider line
        $divider = $section->addTable(['borderBottomSize' => 18, 'borderBottomColor' => '000000']);
        $divider->addRow();
        $divider->addCell(9600)->addText('', ['size' => 2], ['spaceAfter' => 0]);

        $section->addTextBreak(1);
        $section->addText('Padalarang, ${letter_date}', [], ['alignment' => Jc::END]);

        $section->addText('Nomor    : ${letter_number}', [], ['spaceAfter' => 0]);
        $section->addText('Lampiran : -', [], ['spaceAfter' => 0]);
        $section->addText('Perihal  : Surat Perizinan', [], ['spaceAfter' => 200]);

        $section->addText('Terhormat,', [], ['spaceAfter' => 0]);
        $section->addText('${recipient_org}', [], ['spaceAfter' => 0]);
        $section->addText('Di Sekolah', [], ['spaceAfter' => 200]);

        $section->addText('Assalamualaikum Wr. Wb.', ['italic' => true]);
        $section->addText(
            'Dalam rangka ${reason}, dengan ini Komisi 2 Majelis Perwakilan Kelas${requester_clause} bermaksud untuk meminta izin untuk meminjam sarana dengan ketentuan pelaksanaannya sebagai berikut:',
            [],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 140]
        );

        $section->addText('Hari/Tanggal : ${borrow_date}', [], ['spaceAfter' => 0, 'indentation' => ['left' => 600]]);
        $section->addText('Tempat       : ${facility_name}', [], ['spaceAfter' => 0, 'indentation' => ['left' => 600]]);
        $section->addText('Waktu        : ${time_range}', [], ['spaceAfter' => 200, 'indentation' => ['left' => 600]]);

        $section->addText(
            'Untuk itu dengan ini kami mohon dengan hormat untuk diberi izin guna terlaksananya maksud kami di atas. Kemudian atas kebijaksanaan dan pemberian izinnya kami ucapkan terima kasih.',
            [],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 60]
        );
        $section->addText('Wassalamu’alaikum Wr. Wb.', ['italic' => true]);
        $section->addText('Hormat Kami,', [], ['alignment' => Jc::END, 'spaceAfter' => 120]);

        // Top signature row: MPK (left) + Requester or Owner (right)
        $sigTable = $section->addTable(['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 80]);
        $sigTable->addRow();

        $leftSig = $sigTable->addCell(4800);
        $leftSig->addText('Ketua Komisi 2 MPK', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $leftSig->addText('SMK Negeri 4 Padalarang', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);
        $leftSig->addText('${mpk_signature}', ['size' => 9], ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);
        $leftSig->addText('(${mpk_chair_name})', ['underline' => 'single'], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $leftSig->addText('NIS. ${mpk_chair_id}', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

        $rightSig = $sigTable->addCell(4800);
        $rightSig->addText('${right_chair_title}', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $rightSig->addText('${right_school_line}', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);
        $rightSig->addText('${right_signature}', ['size' => 9], ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);
        $rightSig->addText('${right_chair_name_line}', ['underline' => 'single'], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $rightSig->addText('${right_chair_id_line}', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

        // Bottom signature block (used for Workflow A Receiving Owner; empty strings for Workflow B)
        $section->addTextBreak(1);
        $section->addText('${bottom_chair_title}', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $section->addText('${bottom_school_line}', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);
        $section->addText('${bottom_signature}', ['size' => 9], ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);
        $section->addText('${bottom_chair_name_line}', ['underline' => 'single'], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $section->addText('${bottom_chair_id_line}', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

        Storage::disk('local')->makeDirectory(dirname($relativePath));
        $fullPath = Storage::disk('local')->path($relativePath);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($fullPath);

        return $relativePath;
    }
}


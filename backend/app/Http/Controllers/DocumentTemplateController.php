<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadDocumentTemplateRequest;
use App\Models\DocumentTemplate;
use App\Services\MasterTemplateGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class DocumentTemplateController extends Controller
{
    public function __construct(
        private readonly MasterTemplateGeneratorService $generator
    ) {}

    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $templates = DocumentTemplate::with('uploadedBy:id,name')
            ->orderByDesc('version')
            ->get();

        return response()->json([
            'data' => $templates,
            'required_placeholders' => MasterTemplateGeneratorService::REQUIRED_PLACEHOLDERS,
        ]);
    }

    public function store(UploadDocumentTemplateRequest $request)
    {
        $file = $request->file('template');
        $scope = $request->validated('workflow_scope') ?? 'shared';

        try {
            $processor = new TemplateProcessor($file->getRealPath());
            $variables = $processor->getVariables();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'File DOCX tidak dapat dibaca. Pastikan format DOCX valid.',
            ], 422);
        }

        $missing = [];
        foreach (MasterTemplateGeneratorService::REQUIRED_PLACEHOLDERS as $placeholder) {
            if (in_array($placeholder, $variables, true)) {
                continue;
            }
            if ($placeholder === 'right_school_line' && in_array('right_chair_name', $variables, true)) {
                continue;
            }
            if ($placeholder === 'right_chair_name_line' && in_array('right_chair_name', $variables, true)) {
                continue;
            }
            if ($placeholder === 'right_chair_id_line' && in_array('right_chair_id', $variables, true)) {
                continue;
            }
            $missing[] = $placeholder;
        }
        if (! empty($missing)) {
            return response()->json([
                'message' => 'Template DOCX belum memiliki placeholder wajib: ' . implode(', ', $missing),
                'missing_placeholders' => $missing,
            ], 422);
        }

        $template = DB::transaction(function () use ($request, $file, $scope, $variables) {
            $nextVersion = ((int) DocumentTemplate::where('workflow_scope', $scope)->max('version')) + 1;
            $storedPath = $file->storeAs('templates', "master_{$scope}_v{$nextVersion}_" . time() . '.docx', 'local');

            DocumentTemplate::where('workflow_scope', $scope)->where('is_active', true)->update(['is_active' => false]);

            return DocumentTemplate::create([
                'name' => $request->validated('name'),
                'workflow_scope' => $scope,
                'file_path' => $storedPath,
                'placeholders_json' => array_values($variables),
                'version' => $nextVersion,
                'is_active' => true,
                'uploaded_by_user_id' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => "Master template v{$template->version} berhasil diaktifkan.",
            'data' => $template,
        ], 201);
    }

    public function generateDefault(Request $request)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $template = DB::transaction(function () use ($request) {
            $nextVersion = ((int) DocumentTemplate::where('workflow_scope', 'shared')->max('version')) + 1;
            $relativePath = "templates/smkn4_default_v{$nextVersion}_" . time() . '.docx';
            $this->generator->generateDefaultTemplate($relativePath);

            DocumentTemplate::where('workflow_scope', 'shared')->where('is_active', true)->update(['is_active' => false]);

            return DocumentTemplate::create([
                'name' => 'Template Resmi MPK SMKN 4 Padalarang',
                'workflow_scope' => 'shared',
                'file_path' => $relativePath,
                'placeholders_json' => MasterTemplateGeneratorService::REQUIRED_PLACEHOLDERS,
                'version' => $nextVersion,
                'is_active' => true,
                'uploaded_by_user_id' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => "Template default SMKN 4 Padalarang (v{$template->version}) berhasil dibuat dan diaktifkan.",
            'data' => $template,
        ], 201);
    }

    public function download(Request $request, int $id)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $template = DocumentTemplate::findOrFail($id);

        if (! Storage::disk('local')->exists($template->file_path)) {
            return response()->json(['message' => 'File template tidak ditemukan.'], 404);
        }

        return Storage::disk('local')->download($template->file_path, "template-v{$template->version}.docx");
    }
}


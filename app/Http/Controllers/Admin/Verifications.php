<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\VerificationDocument;
use App\Services\Identity\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Verifications extends Controller
{
    public function __construct(private UserAccountService $accounts) {}

    public function index(Request $request): Response
    {
        $status = VerificationStatus::tryFrom((string) $request->query('status', 'pending')) ?? VerificationStatus::Pending;

        $counts = [];

        foreach (VerificationStatus::cases() as $case) {
            $counts[$case->value] = VerificationDocument::query()->where('status', $case)->count();
        }

        return Inertia::render('Admin/Verifications/Index', [
            'documents' => VerificationDocument::query()
                ->with(['user:id,name,email', 'reviewer:id,name'])
                ->where('status', $status)
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (VerificationDocument $document) => [
                    'id' => $document->id,
                    'type' => $document->type,
                    'status' => $document->status->value,
                    'notes' => $document->notes,
                    'user' => $document->user?->only(['id', 'name', 'email']),
                    'reviewer' => $document->reviewer?->name,
                    'reviewed_at' => $document->reviewed_at?->toIso8601String(),
                    'created_at' => $document->created_at?->toIso8601String(),
                ]),
            'status' => $status->value,
            'counts' => $counts,
        ]);
    }

    /**
     * Stream a document from the private disk. Only reachable through the admin route group.
     */
    public function file(VerificationDocument $document): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404, 'The document file is missing.');

        return Storage::disk('local')->response($document->path);
    }

    public function approve(Request $request, VerificationDocument $document): RedirectResponse
    {
        $this->accounts->reviewDocument($document, VerificationStatus::Approved, $request->user(), $request->string('notes')->toString() ?: null);

        return back()->with('success', 'Document approved.');
    }

    public function reject(Request $request, VerificationDocument $document): RedirectResponse
    {
        $validated = $request->validate(['notes' => ['required', 'string', 'max:500']]);
        $this->accounts->reviewDocument($document, VerificationStatus::Rejected, $request->user(), $validated['notes']);

        return back()->with('success', 'Document rejected.');
    }
}

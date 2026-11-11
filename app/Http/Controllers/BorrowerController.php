<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\Payment;
use App\Services\LoanMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BorrowerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status', 'active');

        $sort = $request->query('sort', 'created_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['created_at', 'last_name', 'status'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $borrowers = Borrower::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->orderBy($sort, $dir)
            ->paginate(10)
            ->withQueryString();

        return view('borrowers.index', compact('borrowers', 'q', 'status', 'sort', 'dir'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        abort_unless($request->user()?->hasPermission('manage_borrowers') || $request->user()?->isAdmin(), 403);

        return view('borrowers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless($request->user()?->hasPermission('manage_borrowers') || $request->user()?->isAdmin(), 403);

        $validated = $this->validateBorrower($request);

        $validated['status'] = $validated['status'] ?? 'active';

        if ($request->hasFile('profile_photo')) {
            $validated['profile_photo_path'] = $request->file('profile_photo')->store('borrower_photos', 'public');
        }

        unset($validated['profile_photo']);

        $borrower = Borrower::create($validated);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'subject_type' => Borrower::class,
            'subject_id' => $borrower->id,
            'action' => 'created',
            'description' => 'Created borrower '.$borrower->first_name.' '.$borrower->last_name,
            'changes' => $validated,
        ]);

        return redirect()->route('borrowers.index')->with('status', 'Borrower created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Borrower $borrower)
    {
        $borrower->load(['loans' => fn ($q) => $q->latest()]);

        $loans = $borrower->loans()->with('payments')->get();
        $borrower->setRelation('loans', $loans);

        $metrics = new LoanMetricsService();
        $loanCount = $loans->count();
        $activeLoans = $loans->where('status', 'active')->count();
        $paidLoans = $loans->where('status', 'paid')->count();
        $overdueLoans = $loans->where('status', 'overdue')->count();
        $outstandingBalance = $metrics->calculateOutstandingBalance($loans);
        $recentPayments = Payment::whereHas('loan', fn ($q) => $q->where('borrower_id', $borrower->id))
            ->latest('paid_at')
            ->limit(5)
            ->get();

        return view('borrowers.show', compact(
            'borrower',
            'loanCount',
            'activeLoans',
            'paidLoans',
            'overdueLoans',
            'outstandingBalance',
            'recentPayments'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Borrower $borrower)
    {
        abort_unless($request->user()?->hasPermission('manage_borrowers') || $request->user()?->isAdmin(), 403);

        return view('borrowers.edit', compact('borrower'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Borrower $borrower)
    {
        abort_unless($request->user()?->hasPermission('manage_borrowers') || $request->user()?->isAdmin(), 403);

        $validated = $this->validateBorrower($request, $borrower);

        $validated['status'] = $validated['status'] ?? 'active';

        if ($request->hasFile('profile_photo')) {
            if ($borrower->profile_photo_path) {
                Storage::disk('public')->delete($borrower->profile_photo_path);
            }

            $validated['profile_photo_path'] = $request->file('profile_photo')->store('borrower_photos', 'public');
        }

        unset($validated['profile_photo']);

        // Track only the fields that actually changed
        $changes = [];
        foreach ($validated as $key => $value) {
            if ($borrower->getAttribute($key) !== $value) {
                $changes[$key] = [
                    'from' => $borrower->getAttribute($key),
                    'to' => $value,
                ];
            }
        }

        $borrower->update($validated);

        // Only log if there are actual changes
        if (! empty($changes)) {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'subject_type' => Borrower::class,
                'subject_id' => $borrower->id,
                'action' => 'updated',
                'description' => 'Updated borrower '.$borrower->first_name.' '.$borrower->last_name,
                'changes' => $changes,
            ]);
        }

        return redirect()->route('borrowers.show', $borrower)->with('status', 'Borrower updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Borrower $borrower)
    {
        abort_unless($request->user()?->hasPermission('manage_borrowers') || $request->user()?->isAdmin(), 403);

        $borrower->delete();

        ActivityLog::create([
            'user_id' => auth()->id(),
            'subject_type' => Borrower::class,
            'subject_id' => $borrower->id,
            'action' => 'deleted',
            'description' => 'Soft deleted borrower '.$borrower->first_name.' '.$borrower->last_name,
        ]);

        return redirect()->route('borrowers.index')->with('status', 'Borrower deleted.');
    }

    public function trashed()
    {
        $borrowers = Borrower::onlyTrashed()->paginate(10);

        return view('borrowers.trashed', compact('borrowers'));
    }

    public function restore(Request $request, int $borrowerId)
    {
        abort_unless($request->user()?->hasPermission('manage_borrowers') || $request->user()?->isAdmin(), 403);

        $borrower = Borrower::onlyTrashed()->findOrFail($borrowerId);
        $borrower->restore();

        ActivityLog::create([
            'user_id' => auth()->id(),
            'subject_type' => Borrower::class,
            'subject_id' => $borrower->id,
            'action' => 'restored',
            'description' => 'Restored borrower '.$borrower->first_name.' '.$borrower->last_name,
        ]);

        return redirect()->route('borrowers.index')->with('status', 'Borrower restored.');
    }

    private function validateBorrower(Request $request, ?Borrower $borrower = null): array
    {
        $borrowerId = $borrower?->id;

        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('borrowers', 'phone')
                    ->ignore($borrowerId)
                    ->whereNotNull('phone'),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('borrowers', 'email')
                    ->ignore($borrowerId)
                    ->whereNotNull('email'),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string'],
            'profile_photo' => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);
    }
}

@csrf

<div class="space-y-6">
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-200">Loan <span class="text-rose-400">*</span></label>
        <select name="loan_id" class="field-input" x-model="loanId" @change="updatePenalty()" required>
            <option value="">Select loan...</option>
            @foreach ($loans as $loan)
                @php($selectedLoanId = old('loan_id', $payment->loan_id ?? ($loanId ?? null)))
                <option value="{{ $loan->id }}" @selected((string) $selectedLoanId === (string) $loan->id)>
                    {{ $loan->reference_no }} - {{ $loan->borrower->last_name }}, {{ $loan->borrower->first_name }}
                </option>
            @endforeach
        </select>
        @error('loan_id') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div x-show="selectedLoan" x-cloak>
        <div class="rounded-3xl border border-slate-800 bg-slate-950/50 p-5">
            <h3 class="text-sm font-semibold uppercase tracking-[0.12em] text-slate-400">Loan Payment Summary</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <div class="text-sm text-slate-500">Borrower</div>
                    <div class="mt-2 font-semibold text-white" x-text="selectedLoan ? selectedLoan.borrower : '-'"></div>
                </div>
                <div>
                    <div class="text-sm text-slate-500">Loan Status</div>
                    <div class="mt-2">
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold uppercase ring-1" 
                            :class="{
                                'bg-green-500/10 text-green-300 ring-green-500/30': selectedLoan?.status === 'active',
                                'bg-blue-500/10 text-blue-300 ring-blue-500/30': selectedLoan?.status === 'pending',
                                'bg-emerald-500/10 text-emerald-300 ring-emerald-500/30': selectedLoan?.status === 'paid',
                                'bg-red-500/10 text-red-300 ring-red-500/30': selectedLoan?.status === 'overdue',
                                'bg-slate-500/10 text-slate-300 ring-slate-500/30': selectedLoan?.status === 'cancelled'
                            }"
                            x-text="selectedLoan ? selectedLoan.status.toUpperCase() : '-'"></span>
                    </div>
                </div>
                <div>
                    <div class="text-sm text-slate-500">Outstanding Balance</div>
                    <div class="mt-2 font-semibold text-white">₱<span x-text="selectedLoan ? selectedLoan.outstanding.toFixed(2) : '0.00'"></span></div>
                </div>
                <div>
                    <div class="text-sm text-slate-500">Payment Frequency</div>
                    <div class="mt-2 font-semibold text-white" x-text="getFrequencyLabel(selectedLoan?.frequency)"></div>
                </div>
                <div>
                    <div class="text-sm text-slate-500">Amount Per Due Date</div>
                    <div class="mt-2 font-semibold text-white">₱<span x-text="selectedLoan ? getInstallmentAmount(selectedLoan).toFixed(2) : '0.00'"></span></div>
                </div>
                <div>
                    <div class="text-sm text-slate-500">Next Due Date</div>
                    <div class="mt-2 font-semibold text-white" x-text="selectedLoan ? getNextDueDate(selectedLoan) : '-'"></div>
                </div>
            </div>
            <div class="mt-4 border-t border-slate-700 pt-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-slate-900/50 p-3">
                        <div class="text-xs text-slate-500 uppercase tracking-[0.1em]">Missed Dues</div>
                        <div class="mt-1 text-lg font-semibold text-amber-300" x-text="selectedLoan ? getMissedDueCount(selectedLoan) : '0'"></div>
                    </div>
                    <div class="rounded-2xl bg-slate-900/50 p-3">
                        <div class="text-xs text-slate-500 uppercase tracking-[0.1em]">Auto-Computed Penalty</div>
                        <div class="mt-1 text-lg font-semibold text-white">₱<span x-text="autoComputedPenalty.toFixed(2)"></span></div>
                    </div>
                </div>
            </div>
            <div class="mt-4 rounded-2xl bg-blue-500/10 border border-blue-500/30 p-3">
                <div class="text-sm text-blue-300">
                    <strong>Suggested payment today:</strong> ₱<span x-text="getSuggestedPayment().toFixed(2)"></span>
                    <span class="text-xs text-blue-400 block mt-1">(Amount due + penalty)</span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-200">Amount <span class="text-rose-400">*</span></label>
            <input name="amount" type="number" step="0.01" min="0.01" x-model="amount" class="field-input" value="{{ old('amount', $payment->amount ?? '') }}" required />
            @error('amount') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-200">Penalty Paid</label>
            <input name="penalty_paid" type="number" step="0.01" min="0" placeholder="0.00" x-model="penaltyPaid" class="field-input" />
            @error('penalty_paid') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
            <p class="mt-2 text-xs text-slate-400">Auto-filled from computed missed-due penalty. You may edit this amount.</p>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-200">Paid at <span class="text-rose-400">*</span></label>
            <input name="paid_at" type="date" x-model="paymentDate" @change="updatePenalty()" value="{{ old('paid_at', optional($payment->paid_at ?? null)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" class="field-input" required />
            @error('paid_at') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-200">Method <span class="text-rose-400">*</span></label>
            <select name="method" class="field-input" x-model="method" required>
                @foreach (['cash','gcash','bank','manual'] as $m)
                    <option value="{{ $m }}" @selected(old('method', $payment->method ?? 'cash') === $m)>{{ \App\Helpers\PaymentMethodHelper::getLabel($m) }}</option>
                @endforeach
            </select>
            @error('method') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="mb-2 block text-sm font-medium text-slate-200">Reference Number</label>
            <input name="reference_no" x-model="referenceNo" value="{{ old('reference_no', $payment->reference_no ?? '') }}" class="field-input" placeholder="Optional transaction reference" />
            @error('reference_no') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="mb-2 block text-sm font-medium text-slate-200">Remarks</label>
            <textarea name="remarks" x-model="remarks" rows="3" class="field-input" placeholder="Optional notes about this payment">{{ old('remarks', $payment->remarks ?? '') }}</textarea>
            @error('remarks') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

<script>
    function paymentPenaltyForm(initial) {
        return {
            loans: initial.loans || [],
            loanId: initial.initialLoanId || '',
            paymentDate: initial.initialDate || '',
            amount: parseFloat(initial.initialAmount) || 0.00,
            penaltyPaid: parseFloat(initial.initialPenalty) || 0.00,
            method: initial.initialMethod || 'cash',
            referenceNo: initial.initialReference || '',
            remarks: initial.initialRemarks || '',
            autoComputedPenalty: 0.00,
            autoComputedMissedDues: 0,
            serverInstallment: 0.00,

            init() {
                if (this.loanId && this.paymentDate) {
                    this.updatePenalty();
                }
            },

            get selectedLoan() {
                return this.loans.find((loan) => loan.id === Number(this.loanId)) || null;
            },

            get isValid() {
                return Boolean(this.loanId && this.amount > 0 && this.paymentDate && this.method);
            },

            getFrequencyLabel(freq) {
                const labels = {
                    daily: 'Daily',
                    weekly: 'Weekly',
                    'semi-monthly': 'Semi-Monthly',
                    monthly: 'Monthly'
                };
                return labels[freq] || freq || '-';
            },

            getInstallmentAmount(loan) {
                if (!loan) return 0;
                const terms = Math.max(1, loan.term_months || 1);
                return terms > 0 ? Math.round((loan.total_payable / terms) * 100) / 100 : 0;
            },

            getNextDueDate(loan) {
                if (!loan || !loan.due_date) return '-';
                try {
                    const date = new Date(loan.due_date);
                    return date.toISOString().slice(0, 10);
                } catch {
                    return '-';
                }
            },

            getMissedDueCount(loan) {
                if (!loan) return 0;
                // prefer server-provided missed dues
                if (this.autoComputedMissedDues !== undefined && this.autoComputedMissedDues !== null) {
                    return this.autoComputedMissedDues;
                }
                const outstanding = loan.outstanding || 0;
                const installment = this.getInstallmentAmount(loan);
                return installment > 0 ? Math.floor(outstanding / installment) : 0;
            },

            getSuggestedPayment() {
                if (!this.selectedLoan) return 0;
                const installment = this.serverInstallment || this.getInstallmentAmount(this.selectedLoan);
                const missed = this.autoComputedMissedDues || 0;
                return Math.round(((missed * installment) + (this.autoComputedPenalty || 0)) * 100) / 100;
            },

            async updatePenalty() {
                if (!this.loanId || !this.paymentDate) {
                    this.penaltyPaid = 0.00;
                    this.autoComputedPenalty = 0.00;
                    this.autoComputedMissedDues = 0;
                    this.serverInstallment = 0.00;
                    return;
                }

                try {
                    const response = await fetch(`/payments/penalty-due?loan_id=${encodeURIComponent(this.loanId)}&payment_date=${encodeURIComponent(this.paymentDate)}`);
                    if (!response.ok) {
                        throw new Error('Unable to calculate penalty');
                    }

                    const result = await response.json();
                    this.autoComputedPenalty = Number(result.penalty_due) || 0.00;
                    this.penaltyPaid = Number(result.penalty_due) ? Number(result.penalty_due).toFixed(2) : '0.00';
                    this.autoComputedMissedDues = Number(result.missed_dues) || 0;
                    this.serverInstallment = Number(result.installment) || 0.00;
                } catch (error) {
                    console.error('Penalty calculation error:', error);
                    this.autoComputedPenalty = 0.00;
                    this.autoComputedMissedDues = 0;
                    this.serverInstallment = 0.00;
                }
            },
        };
    }
</script>

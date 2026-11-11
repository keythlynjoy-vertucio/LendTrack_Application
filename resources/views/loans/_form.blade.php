@csrf

<div class="space-y-4">
    <div class="col-span-full">
        <label class="mb-2 block text-sm font-medium text-slate-200">Borrower <span class="text-rose-400">*</span></label>
        <select name="borrower_id" class="field-input" required>
            @foreach ($borrowers as $b)
                @php($selectedId = old('borrower_id', $loan->borrower_id ?? ($borrowerId ?? null)))
                <option value="{{ $b->id }}" @selected((string) $selectedId === (string) $b->id)>
                    {{ $b->last_name }}, {{ $b->first_name }} ({{ $b->phone ?? 'no phone' }})
                </option>
            @endforeach
        </select>
        @error('borrower_id') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-200">Release Date <span class="text-rose-400">*</span></label>
            <input name="start_date" type="date" x-model="startDate" @change="updatePreview()" class="field-input" />
            @error('start_date') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-200">First Due Date <span class="text-rose-400">*</span></label>
            <input name="first_due_date" type="date" x-model="firstDueDate" @change="updatePreview()" class="field-input" />
            @error('first_due_date') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>
    
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-200">Principal <span class="text-rose-400">*</span></label>
            <input name="principal_amount" type="number" step="0.01" min="0.01" x-model="principal" @input="updatePreview()" class="field-input" />
            @error('principal_amount') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-200">Interest Rate (%) <span class="text-rose-400">*</span></label>
            <input name="interest_rate" type="number" step="0.01" min="0" x-model="interestRate" @input="updatePreview()" class="field-input" />
            @error('interest_rate') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-200">Terms (number of payments) <span class="text-rose-400">*</span></label>
            <input name="term_months" type="number" step="1" min="1" x-model="termMonths" @input="updatePreview()" class="field-input" />
            @error('term_months') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-200">Frequency <span class="text-rose-400">*</span></label>
            <select name="frequency" class="field-input" x-model="frequency" @change="updatePreview()">
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="semi-monthly">Semi-monthly</option>
                <option value="monthly">Monthly</option>
            </select>
            @error('frequency') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
        </div>
    </div>

    <div>
        <label class="mb-2 block text-sm font-medium text-slate-200">Penalty per Missed Due <span class="text-rose-400">*</span></label>
        <input name="penalty_per_missed_due" type="number" step="0.01" min="0" x-model="penaltyPerMissedDue" @input="updatePreview()" class="field-input" />
        @error('penalty_per_missed_due') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="mb-2 block text-sm font-medium text-slate-200">Remarks</label>
        <textarea name="remarks" x-model="remarks" rows="3" class="field-input" placeholder="Optional loan remarks"></textarea>
        @error('remarks') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="mb-2 block text-sm font-medium text-slate-200">Notes</label>
        <textarea name="notes" x-model="notes" rows="4" class="field-input" placeholder="Optional loan details or remarks"></textarea>
        @error('notes') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>
</div>

<script>
    function loanFormPreview(initial) {
        return {
            principal: parseFloat(initial.principal) || 0,
            interestRate: parseFloat(initial.interestRate) || 0,
            termMonths: parseInt(initial.termMonths, 10) || 1,
            frequency: initial.frequency || 'monthly',
            firstDueDate: initial.firstDueDate || '',
            startDate: initial.startDate || '',
            penaltyPerMissedDue: parseFloat(initial.penaltyPerMissedDue) || 0,
            remarks: initial.remarks || '',
            notes: initial.notes || '',
            init() {
                this.updatePreview();
            },
            get formattedTotalPayable() {
                return this.totalPayable > 0 ? this.totalPayable.toFixed(2) : '0.00';
            },
            get totalPayable() {
                return parseFloat((this.principal + (this.principal * (this.interestRate / 100))).toFixed(2));
            },
            get periodCount() {
                return Math.max(1, parseInt(this.termMonths, 10) || 1);
            },
            get installment() {
                const count = this.periodCount || 1;
                return count ? parseFloat((this.totalPayable / count).toFixed(2)) : 0;
            },
            get formattedInstallment() {
                return this.installment > 0 ? this.installment.toFixed(2) : '0.00';
            },
            get formattedDueDate() {
                if (!this.firstDueDate) return '-';
                const index = Math.max(0, this.periodCount - 1);
                const due = this.calculateDueDate(this.firstDueDate, this.frequency, index);
                return due || '-';
            },
            get isValid() {
                return this.principal > 0 && this.startDate && this.firstDueDate && this.frequency && this.termMonths >= 1;
            },
            updatePreview() {
                this.principal = parseFloat(this.principal) || 0;
                this.interestRate = parseFloat(this.interestRate) || 0;
                this.termMonths = Math.max(1, parseInt(this.termMonths, 10) || 1);
            },
            calculateDueDate(dateString, frequency, index) {
                if (!dateString) return '';
                const date = new Date(dateString);
                if (Number.isNaN(date.getTime())) return '';

                const due = new Date(date.getTime());
                if (frequency === 'daily') {
                    due.setDate(due.getDate() + index);
                } else if (frequency === 'weekly') {
                    due.setDate(due.getDate() + (index * 7));
                } else if (frequency === 'semi-monthly') {
                    due.setDate(due.getDate() + (index * 15));
                } else {
                    due.setMonth(due.getMonth() + index);
                }

                return due.toISOString().slice(0, 10);
            },
        };
    }
</script>
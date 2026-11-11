<div id="closeLoanModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-xl">
        <h3 class="mb-4 text-xl font-semibold text-white">Close Loan</h3>
        
        <form method="POST" action="{{ route('loans.close', $loan) }}" class="space-y-4" id="closeLoanForm">
            @csrf
            @method('PATCH')
            
            <div>
                <label for="close_reason" class="block text-sm font-medium text-slate-300">Close Reason <span class="text-rose-400">*</span></label>
                <select 
                    name="close_reason" 
                    id="close_reason" 
                    required
                    class="mt-1 block w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-white placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                    <option value="">-- Select a reason --</option>
                    <option value="fully_paid_not_updated">Fully Paid but Not Auto Updated</option>
                    <option value="settled_outside">Settled Outside the System</option>
                    <option value="cancelled_terminated">Cancelled or Terminated</option>
                    <option value="written_off">Written Off</option>
                </select>
                @error('close_reason')
                    <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="close_remarks" class="block text-sm font-medium text-slate-300">Remarks <span class="text-slate-500">(Optional)</span></label>
                <textarea 
                    name="close_remarks" 
                    id="close_remarks" 
                    rows="3"
                    placeholder="Add any remarks for audit purposes..."
                    class="mt-1 block w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-white placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                ></textarea>
                @error('close_remarks')
                    <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3 pt-2">
                <button 
                    type="button" 
                    onclick="document.getElementById('closeLoanModal').classList.add('hidden')"
                    class="flex-1 rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800"
                >
                    Cancel
                </button>
                <button 
                    type="submit"
                    class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                >
                    Close Loan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCloseLoanModal(event) {
        if (event) {
            event.preventDefault();
        }
        document.getElementById('closeLoanModal').classList.remove('hidden');
        return false;
    }
    
    function closeCloseLoanModal() {
        document.getElementById('closeLoanModal').classList.add('hidden');
    }

    // Close modal when clicking outside
    document.getElementById('closeLoanModal').addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
</script>

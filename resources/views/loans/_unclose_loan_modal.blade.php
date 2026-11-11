<div id="uncloseLoanModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-xl">
        <h3 class="mb-4 text-xl font-semibold text-white">Reopen Loan</h3>
        
        <p class="mb-6 text-sm text-slate-300">
            Are you sure you want to reopen this loan? The loan will be changed back to Active status, and you will be able to add payments again.
        </p>

        <form method="POST" action="{{ route('loans.unclose', $loan) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            
            <div class="flex gap-3">
                <button 
                    type="button" 
                    onclick="document.getElementById('uncloseLoanModal').classList.add('hidden')"
                    class="flex-1 rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800"
                >
                    Cancel
                </button>
                <button 
                    type="submit"
                    class="flex-1 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500"
                >
                    Reopen Loan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openUncloseLoanModal() {
        document.getElementById('uncloseLoanModal').classList.remove('hidden');
    }
    
    function closeUncloseLoanModal() {
        document.getElementById('uncloseLoanModal').classList.add('hidden');
    }

    // Close modal when clicking outside
    document.getElementById('uncloseLoanModal').addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
</script>

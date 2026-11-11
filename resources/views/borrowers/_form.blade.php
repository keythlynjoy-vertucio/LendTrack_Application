@csrf

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-200">First name</label>
        <input name="first_name" x-model="firstName" value="{{ old('first_name', $borrower->first_name ?? '') }}" class="field-input" required />
        @error('first_name') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="mb-2 block text-sm font-medium text-slate-200">Last name</label>
        <input name="last_name" x-model="lastName" value="{{ old('last_name', $borrower->last_name ?? '') }}" class="field-input" required />
        @error('last_name') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="mb-2 block text-sm font-medium text-slate-200">Phone</label>
        <input name="phone" x-model="phone" value="{{ old('phone', $borrower->phone ?? '') }}" class="field-input" />
        @error('phone') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="mb-2 block text-sm font-medium text-slate-200">Email</label>
        <input name="email" type="email" x-model="email" value="{{ old('email', $borrower->email ?? '') }}" class="field-input" />
        @error('email') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="mb-2 block text-sm font-medium text-slate-200">Address</label>
        <input name="address" x-model="address" value="{{ old('address', $borrower->address ?? '') }}" class="field-input" />
        @error('address') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="mb-2 block text-sm font-medium text-slate-200">Borrower Photo</label>
        <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-sm text-slate-300 file:mr-4 file:rounded-xl file:border-0 file:bg-blue-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white" />
        @if(isset($borrower->profile_photo_path) && $borrower->profile_photo_path)
            <div class="mt-2 text-sm text-slate-400">Current photo: <a href="{{ asset('storage/'.$borrower->profile_photo_path) }}" target="_blank" class="text-blue-300 hover:text-blue-200">View</a></div>
        @endif
        @error('profile_photo') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="mb-2 block text-sm font-medium text-slate-200">Status</label>
        <select name="status" class="field-input" x-model="status" required>
            <option value="active" @selected(old('status', $borrower->status ?? 'active') === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $borrower->status ?? 'active') === 'inactive')>Inactive</option>
        </select>
        @error('status') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="mb-2 block text-sm font-medium text-slate-200">Notes</label>
        <textarea name="notes" x-model="notes" rows="4" class="field-input" placeholder="Optional notes about this borrower">{{ old('notes', $borrower->notes ?? '') }}</textarea>
        @error('notes') <div class="mt-2 text-sm text-rose-300">{{ $message }}</div> @enderror
    </div>
</div>

<script>
    function borrowerForm(initial) {
        return {
            firstName: initial.firstName || '',
            lastName: initial.lastName || '',
            phone: initial.phone || '',
            email: initial.email || '',
            address: initial.address || '',
            status: initial.status || 'active',
            notes: initial.notes || '',
            init() {
                // Sync input values from the form into Alpine state
                this.firstName = document.querySelector('input[name="first_name"]').value || this.firstName;
                this.lastName = document.querySelector('input[name="last_name"]').value || this.lastName;
                this.phone = document.querySelector('input[name="phone"]').value || this.phone;
                this.email = document.querySelector('input[name="email"]').value || this.email;
                this.address = document.querySelector('input[name="address"]').value || this.address;
                this.status = document.querySelector('select[name="status"]').value || this.status;
                this.notes = document.querySelector('textarea[name="notes"]').value || this.notes;
            },
            get isValid() {
                return this.firstName.trim().length > 0 && this.lastName.trim().length > 0 && ['active', 'inactive'].includes(this.status);
            },
        };
    }
</script>

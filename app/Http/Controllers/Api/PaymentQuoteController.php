<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\CalendarLoanService;
use Illuminate\Http\Request;
class PaymentQuoteController extends Controller {
    public function __invoke(Request $request, Loan $loan, CalendarLoanService $service) {
        abort_unless($request->user()?->hasPermission('record_payments') || $request->user()?->isAdmin(), 403);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']]);
        return response()->json($service->quote($loan, $data['date']));
    }
}

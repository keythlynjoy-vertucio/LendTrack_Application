import 'design/lend_design.dart';

import 'dart:async';
import 'dart:convert';

import 'payment_receipt_page.dart';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'services/session_http.dart' as http;
import 'services/session.dart';

import 'record_payment_page.dart';

class LoanDetailsPage extends StatefulWidget {
  const LoanDetailsPage({super.key, required this.loanId});

  final int loanId;

  @override
  State<LoanDetailsPage> createState() => _LoanDetailsPageState();
}

class _LoanDetailsPageState extends State<LoanDetailsPage> {
  final _storage = const FlutterSecureStorage();

  Map<String, dynamic>? _loan;

  bool _loading = true;
  bool _openingPayment = false;

  String? _error;

  bool get _busy => _loading || _openingPayment;

  @override
  void initState() {
    super.initState();
    _loadLoan();
  }

  String _message(Object error) {
    if (error is TimeoutException) {
      return 'Connection timed out. Please try again.';
    }

    if (error is http.ClientException) {
      return 'Cannot connect. Check that Laravel is running.';
    }

    if (error is FormatException) {
      return 'Unexpected server response. Please check the API.';
    }

    return error.toString().replaceFirst('Exception: ', '');
  }

  Future<void> _loadLoan() async {
    if (!mounted) {
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final token = await _storage.read(key: 'auth_token');

      if (token == null || token.isEmpty) {
        throw Exception('Please return and log in again.');
      }

      final response = await http
          .get(
            Uri.parse('$apiBaseUrl/loans/${widget.loanId}'),
            headers: {
              'Accept': 'application/json',
              'Authorization': 'Bearer $token',
            },
          )
          .timeout(const Duration(seconds: 30));

      if (response.statusCode == 401) {
        throw Exception('Session expired. Return and log in again.');
      }

      if (response.statusCode == 403) {
        throw Exception('Your account does not have access.');
      }

      if (response.statusCode == 404) {
        throw Exception('This loan is no longer available.');
      }

      if (response.statusCode != 200) {
        throw Exception('Unable to load loan (${response.statusCode}).');
      }

      final data = jsonDecode(response.body);

      if (data is! Map<String, dynamic>) {
        throw const FormatException('Invalid loan response.');
      }

      if (!mounted) {
        return;
      }
      setState(() => _loan = data);
    } catch (error) {
      if (mounted) {
        setState(() => _error = _message(error));
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  List<Map<String, dynamic>> _payments(Map<String, dynamic> loan) {
    final payments = (loan['payments'] as List? ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();

    payments.sort(
      (a, b) => (b['paid_at'] ?? '').toString().compareTo(
        (a['paid_at'] ?? '').toString(),
      ),
    );

    return payments;
  }

  double _remainingRegularBalance(Map<String, dynamic> loan) {
    final payable =
        double.tryParse(loan['total_payable']?.toString() ?? '') ?? 0;

    final paid = _payments(loan).fold<double>(
      0,
      (sum, payment) =>
          sum + (double.tryParse(payment['amount']?.toString() ?? '') ?? 0),
    );

    final balanceCents = (payable * 100).round() - (paid * 100).round();
    return balanceCents > 0 ? balanceCents / 100 : 0;
  }

  bool _canRecordPayment(Map<String, dynamic> loan) {
    final status = loan['status']?.toString().trim().toLowerCase();

    return ['active', 'overdue'].contains(status) &&
        loan['borrower'] is Map &&
        (_remainingRegularBalance(loan) > 0 ||
            ((loan['payment_quote']?['penalty_outstanding'] as num?) ?? 0) > 0);
  }

  Future<void> _recordPayment() async {
    final loan = _loan;

    if (_busy || loan == null || !_canRecordPayment(loan)) {
      return;
    }

    setState(() => _openingPayment = true);

    try {
      await Navigator.of(context).push<bool>(
        LendRoute<bool>(builder: (context) => RecordPaymentPage(loan: loan)),
      );

      if (!mounted) {
        return;
      }

      // Refresh even if the user returned after a connection problem.
      await _loadLoan();
    } finally {
      if (mounted) {
        setState(() => _openingPayment = false);
      }
    }
  }

  String _money(dynamic value) => formatMoney(value);

  String _date(dynamic value) {
    final date = DateTime.tryParse(value?.toString() ?? '');

    if (date == null) {
      return '—';
    }

    return '${date.year}-'
        '${date.month.toString().padLeft(2, '0')}-'
        '${date.day.toString().padLeft(2, '0')}';
  }

  Widget _section(String title, List<Widget> children) {
    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            ...children,
          ],
        ),
      ),
    );
  }

  Widget _field(String label, dynamic value) => DetailField(label, value);

  Widget _details() {
    final loan = _loan!;

    final borrower = loan['borrower'] is Map
        ? Map<String, dynamic>.from(loan['borrower'] as Map)
        : <String, dynamic>{};

    final payments = _payments(loan);

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        MetricCard(
          label: 'Remaining regular balance',
          value: _money(_remainingRegularBalance(loan)),
          prominent: true,
          caption: '${loan['reference_no'] ?? 'Loan'} • excludes penalties',
        ),
        _section('Borrower', [
          _field(
            'Name',
            '${borrower['first_name'] ?? ''} '
                '${borrower['last_name'] ?? ''}',
          ),
          _field('Phone', borrower['phone']),
          _field('Email', borrower['email']),
          _field('Address', borrower['address']),
        ]),
        _section('Loan information', [
          _field('Reference', loan['reference_no']),
          Align(
            alignment: Alignment.centerLeft,
            child: StatusPill('${loan['status'] ?? ''}'),
          ),
          const SizedBox(height: 12),
          _field('Principal', _money(loan['principal_amount'])),
          _field('Monthly interest rate (%)', loan['interest_rate']),
          _field('Calendar term (months)', loan['interest_months']),
          _field('Number of installments', loan['term_months']),
          _field('Frequency', loan['frequency']),
          _field('Start date', _date(loan['start_date'])),
          _field('First due date', _date(loan['first_due_date'])),
          _field('Final due date', _date(loan['due_date'])),
          _field('Total payable', _money(loan['total_payable'])),
          _field('Total paid', _money(loan['total_paid'])),
          _field(
            'Unpaid penalty',
            _money(loan['payment_quote']?['penalty_outstanding']),
          ),
          _field(
            'Remaining regular balance',
            _money(_remainingRegularBalance(loan)),
          ),
          const Text('Regular balance excludes penalties.'),
          const SizedBox(height: 12),
          _field(
            'Penalty per missed due',
            _money(loan['penalty_per_missed_due']),
          ),
          if (_canRecordPayment(loan)) ...[
            const SizedBox(height: 8),
            FilledButton.icon(
              onPressed: _busy ? null : _recordPayment,
              icon: const Icon(Icons.payments_outlined),
              label: const Text('Record payment'),
            ),
          ],
        ]),
        _section('Payment history (${payments.length})', [
          if (payments.isEmpty) const Text('No payments recorded yet.'),
          ...payments.map((payment) {
            final amount =
                double.tryParse(payment['amount']?.toString() ?? '') ?? 0;

            final penalty =
                double.tryParse(payment['penalty_paid']?.toString() ?? '') ?? 0;

            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _field('Payment ID', payment['id']),
                _field('Date', _date(payment['paid_at'])),
                _field('Regular amount', _money(amount)),
                _field('Penalty paid', _money(penalty)),
                _field('Total received', _money(amount + penalty)),
                _field('Method', payment['method']),
                _field('Reference', payment['reference_no']),
                _field('Remarks', payment['remarks']),
                TextButton.icon(
                  onPressed: () => Navigator.push<void>(
                    context,
                    LendRoute(
                      builder: (_) =>
                          PaymentReceiptPage(paymentId: payment['id'] as int),
                    ),
                  ),
                  icon: const Icon(Icons.picture_as_pdf),
                  label: const Text('Receipt / Share PDF'),
                ),
                const Divider(),
              ],
            );
          }),
        ]),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Loan details'),
        actions: [
          IconButton(
            onPressed: _busy ? null : _loadLoan,
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
          ),
        ],
      ),
      body: _loading
          ? const AppLoading()
          : _error != null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_error!, textAlign: TextAlign.center),
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: _busy ? null : _loadLoan,
                      child: const Text('Retry'),
                    ),
                  ],
                ),
              ),
            )
          : _details(),
    );
  }
}

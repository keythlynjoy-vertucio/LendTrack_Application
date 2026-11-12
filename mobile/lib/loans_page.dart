import 'design/lend_design.dart';

import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'services/session_http.dart' as http;
import 'services/session.dart';

import 'add_loan_page.dart';
import 'loan_details_page.dart';

class LoansPage extends StatefulWidget {
  const LoansPage({super.key});

  @override
  State<LoansPage> createState() => _LoansPageState();
}

class _LoansPageState extends State<LoansPage> {
  final _storage = const FlutterSecureStorage();

  List<Map<String, dynamic>> _loans = [];

  bool _loading = true;
  bool _openingPage = false;

  String? _error;

  final _search = TextEditingController();
  String _appliedSearch = '';
  String _status = '';

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  int _page = 1;
  int _lastPage = 1;
  int _total = 0;

  bool get _busy => _loading || _openingPage;

  @override
  void initState() {
    super.initState();
    _loadPage(1);
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

  Future<void> _loadPage(int page) async {
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
            Uri.parse(
              '$apiBaseUrl/loans?page=$page&search=${Uri.encodeQueryComponent(_appliedSearch)}&status=$_status',
            ),
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

      if (response.statusCode != 200) {
        throw Exception('Unable to load loans (${response.statusCode}).');
      }

      final decoded = jsonDecode(response.body);

      if (decoded is! Map<String, dynamic>) {
        throw const FormatException('Invalid server response.');
      }

      final records = decoded['data'];

      if (records is! List) {
        throw const FormatException('Invalid loan list.');
      }

      final loans = records
          .map((record) => Map<String, dynamic>.from(record as Map))
          .toList();

      if (!mounted) {
        return;
      }

      setState(() {
        _loans = loans;
        _page = decoded['current_page'] as int;
        _lastPage = decoded['last_page'] as int;
        _total = decoded['total'] as int;
      });
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

  Future<void> _addLoan() async {
    if (_busy) {
      return;
    }

    setState(() => _openingPage = true);

    try {
      final created = await Navigator.of(
        context,
      ).push<bool>(LendRoute<bool>(builder: (context) => const AddLoanPage()));

      if (!mounted) {
        return;
      }

      if (created == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Loan created successfully.')),
        );
      }

      // Also refresh after returning from an uncertain save result.
      await _loadPage(created == true ? 1 : _page);
    } finally {
      if (mounted) {
        setState(() => _openingPage = false);
      }
    }
  }

  Future<void> _openDetails(int id) async {
    if (_busy) {
      return;
    }

    setState(() => _openingPage = true);

    try {
      await Navigator.of(context).push<void>(
        LendRoute<void>(builder: (context) => LoanDetailsPage(loanId: id)),
      );

      if (!mounted) {
        return;
      }
      await _loadPage(_page);
    } finally {
      if (mounted) {
        setState(() => _openingPage = false);
      }
    }
  }

  String _money(dynamic value) => formatMoney(value);

  Widget _loanCard(Map<String, dynamic> loan) {
    final borrower = loan['borrower'];
    final name = borrower is Map
        ? '${borrower['first_name'] ?? ''} ${borrower['last_name'] ?? ''}'
              .trim()
        : 'Borrower unavailable';
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Wrap(
              spacing: 8,
              runSpacing: 8,
              alignment: WrapAlignment.spaceBetween,
              children: [
                Text(
                  '${loan['reference_no'] ?? '—'}',
                  style: const TextStyle(fontSize: 12, color: lendMuted),
                ),
                StatusPill('${loan['status'] ?? ''}'),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              name.isEmpty ? 'Unnamed borrower' : name,
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 16),
            const Text(
              'Principal amount',
              style: TextStyle(color: lendMuted, fontSize: 12),
            ),
            Text(
              _money(loan['principal_amount']),
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 18),
            RepaymentProgress(
              paid: loan['total_paid'],
              total: loan['total_payable'],
            ),
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                onPressed: _busy ? null : () => _openDetails(loan['id'] as int),
                icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                label: const Text('View details'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _body() {
    if (_loading) {
      return const AppLoading();
    }

    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error!, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _busy ? null : () => _loadPage(_page),
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: Text('Total loans: $_total'),
        ),
        Expanded(
          child: _loans.isEmpty
              ? const EmptyRecords('No loans found.')
              : ListView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 88),
                  itemCount: _loans.length,
                  itemBuilder: (context, index) {
                    return _loanCard(_loans[index]);
                  },
                ),
        ),
      ],
    );
  }

  Widget _filters() => Padding(
    padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
    child: Column(
      children: [
        TextField(
          controller: _search,
          enabled: !_busy,
          maxLength: 100,
          decoration: InputDecoration(
            labelText: 'Search name, phone or reference',
            counterText: '',
            border: const OutlineInputBorder(),
            suffixIcon: IconButton(
              icon: const Icon(Icons.search),
              onPressed: _busy ? null : _applySearch,
            ),
          ),
          onSubmitted: (_) => _applySearch(),
        ),
        const SizedBox(height: 8),
        DropdownButtonFormField<String>(
          initialValue: _status,
          decoration: const InputDecoration(
            labelText: 'Status',
            border: OutlineInputBorder(),
          ),
          items:
              [
                    '',
                    'pending',
                    'active',
                    'overdue',
                    'paid',
                    'closed',
                    'cancelled',
                    'written_off',
                  ]
                  .map(
                    (status) => DropdownMenuItem(
                      value: status,
                      child: Text(status.isEmpty ? 'All statuses' : status),
                    ),
                  )
                  .toList(),
          onChanged: _busy
              ? null
              : (value) {
                  setState(() => _status = value ?? '');
                  _applySearch();
                },
        ),
      ],
    ),
  );

  void _applySearch() {
    if (_busy) {
      return;
    }
    _appliedSearch = _search.text.trim();
    _loadPage(1);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Loans'),
        actions: [
          IconButton(
            onPressed: _busy ? null : () => _loadPage(_page),
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
          ),
        ],
      ),
      body: Column(
        children: [
          _filters(),
          Expanded(child: _body()),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        tooltip: 'Add loan',
        onPressed: _busy ? null : _addLoan,
        child: const Icon(Icons.add),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              TextButton(
                onPressed: !_busy && _error == null && _page > 1
                    ? () => _loadPage(_page - 1)
                    : null,
                child: const Text('Previous'),
              ),
              Text('$_page / $_lastPage'),
              TextButton(
                onPressed: !_busy && _error == null && _page < _lastPage
                    ? () => _loadPage(_page + 1)
                    : null,
                child: const Text('Next'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

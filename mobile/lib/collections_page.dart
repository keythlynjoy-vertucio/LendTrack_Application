import 'design/lend_design.dart';

import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'services/session_http.dart' as http;
import 'services/session.dart';

import 'loan_details_page.dart';

class CollectionsPage extends StatefulWidget {
  const CollectionsPage({super.key});

  @override
  State<CollectionsPage> createState() => _CollectionsPageState();
}

class _CollectionsPageState extends State<CollectionsPage> {
  final _storage = const FlutterSecureStorage();
  final _search = TextEditingController();

  List<Map<String, dynamic>> _items = [];
  Map<String, dynamic> _summary = {};

  String _bucket = 'all';
  String _searchValue = '';
  String? _asOf;
  String? _error;

  bool _loading = true;
  bool _openingLoan = false;

  int _page = 1;
  int _lastPage = 1;
  int _total = 0;

  bool get _busy => _loading || _openingLoan;

  @override
  void initState() {
    super.initState();
    _loadPage(1);
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
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

  String _money(dynamic value) => formatMoney(value);

  String _bucketLabel(String bucket) {
    return switch (bucket) {
      'overdue' => 'Overdue',
      'due_today' => 'Due today',
      'upcoming' => 'Upcoming',
      _ => 'All unpaid',
    };
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

      final uri = Uri.parse('$apiBaseUrl/collections').replace(
        queryParameters: {
          'page': '$page',
          'bucket': _bucket,
          if (_searchValue.isNotEmpty) 'search': _searchValue,
        },
      );

      final response = await http
          .get(
            uri,
            headers: {
              'Accept': 'application/json',
              'Authorization': 'Bearer $token',
            },
          )
          .timeout(const Duration(seconds: 60));

      if (response.statusCode == 401) {
        throw Exception('Session expired. Return and log in again.');
      }

      if (response.statusCode == 403) {
        throw Exception('Your account cannot access collections.');
      }

      if (response.statusCode != 200) {
        throw Exception('Unable to load collections (${response.statusCode}).');
      }

      final decoded = jsonDecode(response.body);

      if (decoded is! Map<String, dynamic> ||
          decoded['data'] is! List ||
          decoded['summary'] is! Map) {
        throw const FormatException('Invalid collections response.');
      }

      final items = (decoded['data'] as List)
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();

      final currentPage = decoded['current_page'] as int;
      final lastPage = decoded['last_page'] as int;

      if (!mounted) {
        return;
      }

      // A payment may reduce the number of result pages.
      if (currentPage > lastPage && lastPage >= 1) {
        await _loadPage(lastPage);
        return;
      }

      setState(() {
        _items = items;
        _summary = Map<String, dynamic>.from(decoded['summary'] as Map);
        _asOf = decoded['as_of']?.toString();
        _page = currentPage;
        _lastPage = lastPage;
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

  void _runSearch() {
    if (_busy) {
      return;
    }

    FocusScope.of(context).unfocus();
    _searchValue = _search.text.trim();
    _loadPage(1);
  }

  Future<void> _openLoan(int id) async {
    if (_busy) {
      return;
    }

    setState(() => _openingLoan = true);

    try {
      await Navigator.of(context).push<void>(
        LendRoute<void>(builder: (context) => LoanDetailsPage(loanId: id)),
      );

      if (!mounted) {
        return;
      }

      // Refresh balances after returning from the payment screen.
      await _loadPage(_page);
    } finally {
      if (mounted) {
        setState(() => _openingLoan = false);
      }
    }
  }

  Widget _summaryTile(String key, String label, double width) {
    final raw = _summary[key];
    final data = raw is Map ? raw : <String, dynamic>{};
    return SizedBox(
      width: width,
      child: MetricCard(
        label: label,
        value: _money(data['balance']),
        caption: '${data['count'] ?? 0} installments',
        prominent: key == 'total',
        icon: key == 'overdue'
            ? Icons.schedule_outlined
            : Icons.calendar_month_outlined,
      ),
    );
  }

  Widget _summaryCards() {
    return LayoutBuilder(
      builder: (context, constraints) {
        final width =
            constraints.maxWidth < 380 ||
                MediaQuery.textScalerOf(context).scale(14) > 19
            ? constraints.maxWidth
            : (constraints.maxWidth - 12) / 2;

        return Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [
            _summaryTile('overdue', 'Overdue', width),
            _summaryTile('due_today', 'Due today', width),
            _summaryTile('upcoming', 'Upcoming', width),
            _summaryTile('total', 'Total unpaid', width),
          ],
        );
      },
    );
  }

  Widget _itemCard(Map<String, dynamic> item) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${item['borrower_name'] ?? 'Borrower unavailable'}',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 6),
            Text('${item['reference_no'] ?? '—'}'),
            Text('Installment: ${item['installment_no'] ?? '—'}'),
            Text('Due date: ${item['due_date'] ?? '—'}'),
            const SizedBox(height: 8),
            Text('Expected: ${_money(item['expected_amount'])}'),
            Text('Paid toward installment: ${_money(item['amount_paid'])}'),
            Text(
              'Remaining: ${_money(item['remaining_amount'])}',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 6),
            StatusPill(_bucketLabel(item['bucket']?.toString() ?? '')),
            const SizedBox(height: 8),
            TextButton.icon(
              onPressed: _busy ? null : () => _openLoan(item['loan_id'] as int),
              icon: const Icon(Icons.receipt_long_outlined),
              label: const Text('View loan / record payment'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _results() {
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

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      children: [
        Text('As of: ${_asOf ?? '—'}'),
        const SizedBox(height: 12),
        _summaryCards(),
        const SizedBox(height: 12),
        const Text(
          'Amounts exclude penalties. Counts are unpaid installments, '
          'not loans. Summary includes all categories within your search.',
        ),
        const SizedBox(height: 16),
        Text(
          '${_bucketLabel(_bucket)}: $_total installments',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 12),
        if (_items.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 24),
            child: Text('No unpaid installments match this selection.'),
          ),
        ..._items.map(_itemCard),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Collections'),
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
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                TextField(
                  controller: _search,
                  enabled: !_busy,
                  maxLength: 100,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (_) => _runSearch(),
                  decoration: InputDecoration(
                    labelText: 'Search borrower, phone, or loan reference',
                    border: const OutlineInputBorder(),
                    counterText: '',
                    suffixIcon: IconButton(
                      onPressed: _busy ? null : _runSearch,
                      icon: const Icon(Icons.search),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _bucket,
                  isExpanded: true,
                  decoration: const InputDecoration(
                    labelText: 'Show',
                    border: OutlineInputBorder(),
                  ),
                  items: const [
                    DropdownMenuItem(value: 'all', child: Text('All unpaid')),
                    DropdownMenuItem(value: 'overdue', child: Text('Overdue')),
                    DropdownMenuItem(
                      value: 'due_today',
                      child: Text('Due today'),
                    ),
                    DropdownMenuItem(
                      value: 'upcoming',
                      child: Text('Upcoming'),
                    ),
                  ],
                  onChanged: _busy
                      ? null
                      : (value) {
                          if (value == null || value == _bucket) {
                            return;
                          }

                          FocusScope.of(context).unfocus();
                          setState(() => _bucket = value);
                          _loadPage(1);
                        },
                ),
              ],
            ),
          ),
          Expanded(child: _results()),
        ],
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

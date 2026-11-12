import 'design/lend_design.dart';

import 'package:flutter/material.dart';

import 'services/api.dart';
import 'loan_details_page.dart';

class ManagementPage extends StatefulWidget {
  final String section;
  const ManagementPage({super.key, required this.section});
  @override
  State<ManagementPage> createState() => _ManagementPageState();
}

class _ManagementPageState extends State<ManagementPage> {
  final _search = TextEditingController();
  String _query = '';
  String _type = 'borrowers';
  String _state = 'deleted';
  DateTime _asOf = DateTime.now();
  bool _busy = true;
  String? _error;
  Map<String, dynamic> _data = {};
  int _page = 1;
  int _last = 1;
  bool get _records => widget.section == 'records';
  bool get _dashboard => widget.section == 'dashboard';
  String get _title => switch (widget.section) {
    'dashboard' => 'Dashboard & analytics',
    'activity-logs' => 'Activity logs',
    'records' => 'Archive & restore',
    _ => 'Discrepancy monitoring',
  };
  @override
  void initState() {
    super.initState();
    _load(1);
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  String _date(DateTime date) => date.toIso8601String().substring(0, 10);
  String _money(dynamic value) => formatMoney(value);
  Future<void> _load(int page) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final path = _records ? 'records/$_type' : widget.section;
      final data = await Api.request(
        '$path?page=$page&state=$_state'
        '&search=${Uri.encodeQueryComponent(_query)}&as_of=${_date(_asOf)}',
      );
      if (!mounted) {
        return;
      }
      setState(() {
        _data = data;
        _page = data['current_page'] as int? ?? 1;
        _last = data['last_page'] as int? ?? 1;
      });
    } catch (e) {
      if (mounted) {
        setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _change(Map<String, dynamic> item) async {
    final action = _state == 'deleted' ? 'restore' : 'delete';
    final reason = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          '${action == 'delete' ? 'Archive' : 'Restore'} $_type #${item['id']}?',
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'A reason is required. This action is recorded in the activity log.',
            ),
            TextField(
              controller: reason,
              maxLength: 500,
              maxLines: 3,
              decoration: const InputDecoration(labelText: 'Reason'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () {
              if (reason.text.trim().isNotEmpty) {
                Navigator.pop(dialogContext, true);
              }
            },
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
    final text = reason.text.trim();
    // The dialog route may still be animating out; dispose after its transition.
    Future<void>.delayed(const Duration(seconds: 1), reason.dispose);
    if (confirmed != true || !mounted) {
      return;
    }
    setState(() => _busy = true);
    try {
      await Api.request(
        'records/$_type/${item['id']}',
        method: 'POST',
        body: {'action': action, 'reason': text},
      );
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('Record updated.')));
      await _load(1);
    } catch (e) {
      if (mounted) {
        setState(() => _busy = false);
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  Widget _card(String title, List<Widget> children) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          ...children,
        ],
      ),
    ),
  );

  Widget _dashboardBody() {
    final p = Map<String, dynamic>.from(_data['portfolio'] as Map? ?? {});
    final monthly = Map<String, dynamic>.from(
      _data['monthly_collections'] is Map
          ? _data['monthly_collections'] as Map
          : {},
    );
    final statuses = Map<String, dynamic>.from(
      p['loan_status_count'] is Map ? p['loan_status_count'] as Map : {},
    );
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        SectionHeading(
          'Portfolio overview',
          subtitle: 'As of ${_data['as_of']} • ${_data['borrowers']} borrowers',
        ),
        MetricCard(
          label: 'Total collected',
          value: _money(p['total_collections']),
          prominent: true,
          caption: 'Includes regular payments and penalties',
        ),
        MonthlyCollections(values: monthly),
        const SectionHeading(
          'Balance breakdown',
          subtitle: 'Outstanding balances exclude penalties.',
        ),
        for (final entry in const {
          'released_capital': 'Capital released',
          'regular_payments': 'Regular payments collected',
          'penalty_payments': 'Penalties collected',
          'outstanding_balance': 'Outstanding regular balance',
          'overdue_amount': 'Overdue installments',
          'due_today': 'Due today',
          'upcoming_amount': 'Upcoming installments',
        }.entries)
          MetricCard(label: entry.value, value: _money(p[entry.key])),
        _card('Stored loan statuses', [
          for (final e in statuses.entries)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Wrap(
                spacing: 12,
                runSpacing: 4,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [StatusPill(e.key), Text('${e.value} loans')],
              ),
            ),
        ]),
      ],
    );
  }

  Widget _item(Map<String, dynamic> item) {
    if (widget.section == 'activity-logs') {
      final user = item['user'];
      return _card('${item['action']} • ${item['created_at']}', [
        Text('${item['description']}'),
        Text('By: ${user is Map ? user['name'] : 'Unknown user'}'),
        Text('Record: ${item['subject_type']} #${item['subject_id']}'),
      ]);
    }
    if (_records) {
      final title = _type == 'borrowers'
          ? '${item['first_name']} ${item['last_name']}'
          : _type == 'loans'
          ? '${item['reference_no']}'
          : 'Payment #${item['id']} • Loan #${item['loan_id']}';
      return _card(title, [
        Text('ID: ${item['id']}'),
        if (item['amount'] != null) Text('Regular: ${_money(item['amount'])}'),
        if (item['penalty_paid'] != null)
          Text('Penalty: ${_money(item['penalty_paid'])}'),
        if (item['status'] != null) StatusPill('${item['status']}'),
        if (item['deleted_at'] != null) Text('Archived: ${item['deleted_at']}'),
        TextButton.icon(
          onPressed: _busy ? null : () => _change(item),
          icon: Icon(
            _state == 'deleted' ? Icons.restore : Icons.archive_outlined,
          ),
          label: Text(_state == 'deleted' ? 'Restore' : 'Archive'),
        ),
      ]);
    }
    final summary = item['summary'] as Map? ?? {};
    return _card('${item['reference_no']}', [
      Text('${item['borrower'] ?? 'Borrower unavailable'}'),
      StatusPill('${item['status']}'),
      Text('Expected by date: ${_money(summary['expected_paid_by'])}'),
      Text('Regular paid by date: ${_money(summary['actual_paid_by'])}'),
      Text('Shortfall: ${_money(summary['discrepancy'])}'),
      Text('Advance: ${_money(summary['advance'])}'),
      for (final warning in item['warnings'] as List? ?? [])
        Padding(
          padding: const EdgeInsets.only(top: 8),
          child: Text(
            '$warning',
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        ),
      TextButton(
        onPressed: () async {
          await Navigator.push<void>(
            context,
            LendRoute(
              builder: (_) => LoanDetailsPage(loanId: item['id'] as int),
            ),
          );
          if (mounted) {
            _load(_page);
          }
        },
        child: const Text('View loan'),
      ),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    final items = _data['data'] as List? ?? [];
    return Scaffold(
      appBar: AppBar(
        title: Text(_title),
        actions: [
          IconButton(
            onPressed: _busy ? null : () => _load(_page),
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: Column(
        children: [
          if (!_dashboard)
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  TextField(
                    controller: _search,
                    enabled: !_busy,
                    maxLength: 100,
                    decoration: InputDecoration(
                      labelText: 'Search',
                      counterText: '',
                      border: const OutlineInputBorder(),
                      suffixIcon: IconButton(
                        icon: const Icon(Icons.search),
                        onPressed: _busy
                            ? null
                            : () {
                                _query = _search.text.trim();
                                _load(1);
                              },
                      ),
                    ),
                    onSubmitted: (_) {
                      _query = _search.text.trim();
                      _load(1);
                    },
                  ),
                  if (_records)
                    Row(
                      children: [
                        Expanded(
                          child: DropdownButton<String>(
                            value: _type,
                            isExpanded: true,
                            items: ['borrowers', 'loans', 'payments']
                                .map(
                                  (v) => DropdownMenuItem(
                                    value: v,
                                    child: Text(v),
                                  ),
                                )
                                .toList(),
                            onChanged: _busy
                                ? null
                                : (v) {
                                    setState(() => _type = v!);
                                    _load(1);
                                  },
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: DropdownButton<String>(
                            value: _state,
                            isExpanded: true,
                            items: const [
                              DropdownMenuItem(
                                value: 'deleted',
                                child: Text('Archived'),
                              ),
                              DropdownMenuItem(
                                value: 'current',
                                child: Text('Current'),
                              ),
                            ],
                            onChanged: _busy
                                ? null
                                : (v) {
                                    setState(() => _state = v!);
                                    _load(1);
                                  },
                          ),
                        ),
                      ],
                    ),
                  if (widget.section == 'discrepancies')
                    TextButton.icon(
                      icon: const Icon(Icons.calendar_month),
                      label: Text('As of ${_date(_asOf)}'),
                      onPressed: _busy
                          ? null
                          : () async {
                              final date = await showDatePicker(
                                context: context,
                                initialDate: _asOf,
                                firstDate: DateTime(2000),
                                lastDate: DateTime(2100),
                              );
                              if (date != null && mounted) {
                                setState(() => _asOf = date);
                                _load(1);
                              }
                            },
                    ),
                ],
              ),
            ),
          Expanded(
            child: _busy
                ? const AppLoading()
                : _error != null
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(_error!),
                          FilledButton(
                            onPressed: () => _load(_page),
                            child: const Text('Retry'),
                          ),
                        ],
                      ),
                    ),
                  )
                : _dashboard
                ? _dashboardBody()
                : items.isEmpty
                ? const EmptyRecords('No records found.')
                : ListView(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    children: items
                        .map((v) => _item(Map<String, dynamic>.from(v as Map)))
                        .toList(),
                  ),
          ),
          if (!_dashboard)
            SafeArea(
              top: false,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  TextButton(
                    onPressed: _busy || _page <= 1
                        ? null
                        : () => _load(_page - 1),
                    child: const Text('Previous'),
                  ),
                  Text('$_page / $_last'),
                  TextButton(
                    onPressed: _busy || _page >= _last
                        ? null
                        : () => _load(_page + 1),
                    child: const Text('Next'),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

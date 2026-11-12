import 'design/lend_design.dart';

import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'services/session_http.dart' as http;
import 'services/session.dart';

class CollectionReportPage extends StatefulWidget {
  const CollectionReportPage({super.key});

  @override
  State<CollectionReportPage> createState() => _CollectionReportPageState();
}

class _CollectionReportPageState extends State<CollectionReportPage> {
  final _storage = const FlutterSecureStorage();
  final _formKey = GlobalKey<FormState>();

  final _fromController = TextEditingController();
  final _toController = TextEditingController();

  DateTime? _from;
  DateTime? _to;

  DateTime? _appliedFrom;
  DateTime? _appliedTo;

  Map<String, dynamic>? _report;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();

    final now = DateTime.now();
    _from = DateTime(now.year, now.month, 1);
    _to = DateTime(now.year, now.month, now.day);

    _updateDateText();
    _loadReport(_from, _to);
  }

  @override
  void dispose() {
    _fromController.dispose();
    _toController.dispose();
    super.dispose();
  }

  String _dateText(DateTime date) {
    return '${date.year.toString().padLeft(4, '0')}-'
        '${date.month.toString().padLeft(2, '0')}-'
        '${date.day.toString().padLeft(2, '0')}';
  }

  void _updateDateText() {
    _fromController.text = _from == null ? '' : _dateText(_from!);
    _toController.text = _to == null ? '' : _dateText(_to!);
  }

  String _money(dynamic value) => formatMoney(value);

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

  Future<void> _loadReport(DateTime? from, DateTime? to) async {
    if (!mounted) {
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
      _report = null;
    });

    try {
      final token = await _storage.read(key: 'auth_token');

      if (token == null || token.isEmpty) {
        throw Exception('Please return and log in again.');
      }

      final uri = Uri.parse('$apiBaseUrl/reports/collections').replace(
        queryParameters: {
          if (from != null) 'from': _dateText(from),
          if (to != null) 'to': _dateText(to),
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
          .timeout(const Duration(seconds: 30));

      if (response.statusCode == 401) {
        throw Exception('Session expired. Return and log in again.');
      }

      if (response.statusCode == 403) {
        throw Exception('This report is available to Admin accounts only.');
      }

      final decoded = jsonDecode(response.body);

      if (decoded is! Map<String, dynamic>) {
        throw const FormatException('Invalid report response.');
      }

      if (response.statusCode != 200) {
        final errors = decoded['errors'];

        if (errors is Map && errors.isNotEmpty) {
          throw Exception(
            errors.values
                .map((value) {
                  return value is List ? value.join('\n') : value.toString();
                })
                .join('\n'),
          );
        }

        throw Exception(
          decoded['message'] ??
              'Unable to load report (${response.statusCode}).',
        );
      }

      if (decoded['count'] is! num ||
          decoded['loans_count'] is! num ||
          decoded['regular_collected'] is! num ||
          decoded['penalty_collected'] is! num ||
          decoded['total_collected'] is! num) {
        throw const FormatException('Invalid report totals.');
      }

      if (!mounted) {
        return;
      }

      setState(() {
        _report = decoded;
        _appliedFrom = from;
        _appliedTo = to;
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

  Future<void> _pickDate({required bool fromDate}) async {
    if (_loading) {
      return;
    }

    final selected = await showDatePicker(
      context: context,
      initialDate: (fromDate ? _from : _to) ?? DateTime.now(),
      firstDate: DateTime(2000),
      lastDate: DateTime(2100, 12, 31),
    );

    if (!mounted || selected == null) {
      return;
    }

    setState(() {
      if (fromDate) {
        _from = selected;
      } else {
        _to = selected;
      }

      _updateDateText();
    });
  }

  void _apply() {
    if (_loading) {
      return;
    }
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    FocusScope.of(context).unfocus();
    _loadReport(_from, _to);
  }

  void _allTime() {
    if (_loading) {
      return;
    }

    setState(() {
      _from = null;
      _to = null;
      _updateDateText();
    });

    _formKey.currentState?.reset();
    _loadReport(null, null);
  }

  void _thisMonth() {
    if (_loading) {
      return;
    }

    final now = DateTime.now();

    setState(() {
      _from = DateTime(now.year, now.month, 1);
      _to = DateTime(now.year, now.month, now.day);
      _updateDateText();
    });

    _formKey.currentState?.reset();
    _loadReport(_from, _to);
  }

  Widget _dateField({required bool fromDate}) {
    final controller = fromDate ? _fromController : _toController;

    return TextFormField(
      controller: controller,
      enabled: !_loading,
      readOnly: true,
      onTap: () => _pickDate(fromDate: fromDate),
      decoration: InputDecoration(
        labelText: fromDate ? 'From date' : 'To date',
        hintText: 'No limit',
        border: const OutlineInputBorder(),
        suffixIcon: IconButton(
          tooltip: 'Clear date',
          onPressed: _loading
              ? null
              : () {
                  setState(() {
                    if (fromDate) {
                      _from = null;
                    } else {
                      _to = null;
                    }

                    _updateDateText();
                  });
                },
          icon: const Icon(Icons.clear),
        ),
      ),
      validator: (_) {
        if (!fromDate &&
            _from != null &&
            _to != null &&
            _to!.isBefore(_from!)) {
          return 'End date must be on or after start date.';
        }

        return null;
      },
    );
  }

  Widget _metric(String label, String value) => MetricCard(
    label: label,
    value: value,
    prominent: label == 'Total collected',
    icon: label.contains('records') || label.contains('Loans')
        ? Icons.receipt_long_outlined
        : Icons.payments_outlined,
  );

  List<Widget> _reportWidgets() {
    final report = _report;

    if (report == null) {
      return [];
    }

    final from = report['from']?.toString() ?? 'Beginning';
    final to = report['to']?.toString() ?? 'No end limit';

    return [
      const SizedBox(height: 24),
      Text(
        'Report period: $from to $to',
        style: Theme.of(context).textTheme.titleMedium,
      ),
      const SizedBox(height: 8),
      const Text('Based on payment date. Both selected dates are included.'),
      const SizedBox(height: 16),
      _metric('Total collected', _money(report['total_collected'])),
      _metric('Regular payments', _money(report['regular_collected'])),
      _metric('Penalties collected', _money(report['penalty_collected'])),
      _metric('Payment records', '${report['count']}'),
      _metric('Loans with payments', '${report['loans_count']}'),
      if (report['count'] == 0)
        const Text('No payments found for this period.'),
    ];
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Collections report'),
        actions: [
          IconButton(
            onPressed: _loading
                ? null
                : () => _loadReport(_appliedFrom, _appliedTo),
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh displayed report',
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            const Text(
              'Choose dates, then tap Apply. '
              'Leave a date blank for no limit.',
            ),
            const SizedBox(height: 16),
            Form(
              key: _formKey,
              child: Column(
                children: [
                  _dateField(fromDate: true),
                  const SizedBox(height: 16),
                  _dateField(fromDate: false),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                FilledButton(
                  onPressed: _loading ? null : _apply,
                  child: const Text('Apply'),
                ),
                OutlinedButton(
                  onPressed: _loading ? null : _thisMonth,
                  child: const Text('This month'),
                ),
                OutlinedButton(
                  onPressed: _loading ? null : _allTime,
                  child: const Text('All time'),
                ),
              ],
            ),
            if (_loading) ...[const SizedBox(height: 24), const AppLoading()],
            if (_error != null) ...[
              const SizedBox(height: 24),
              Text(_error!, style: const TextStyle(color: Colors.red)),
              const SizedBox(height: 12),
              const Text('Check the dates and tap Apply to retry.'),
            ],
            ..._reportWidgets(),
          ],
        ),
      ),
    );
  }
}

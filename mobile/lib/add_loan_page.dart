import 'design/lend_design.dart';

import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'services/session_http.dart' as http;
import 'services/session.dart';

class _LoanApi {
  static const _baseUrl = apiBaseUrl;
  final _storage = const FlutterSecureStorage();

  Future<Map<String, String>> _headers() async {
    final token = await _storage.read(key: 'auth_token');

    if (token == null || token.isEmpty) {
      throw Exception('Please return and log in again.');
    }

    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer $token',
    };
  }

  Map<String, dynamic> _decode(http.Response response) {
    if (response.statusCode == 401) {
      throw Exception('Session expired. Return and log in again.');
    }

    if (response.statusCode == 403) {
      throw Exception(
        'Your account does not have permission to perform this action.',
      );
    }

    final decoded = jsonDecode(response.body);

    if (decoded is! Map<String, dynamic>) {
      throw const FormatException('Unexpected server response.');
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
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
        decoded['message'] ?? 'Request failed (${response.statusCode}).',
      );
    }

    return decoded;
  }

  Future<Map<String, dynamic>> get(String path) async {
    final headers = await _headers();
    final response = await http
        .get(Uri.parse('$_baseUrl/$path'), headers: headers)
        .timeout(const Duration(seconds: 30));

    return _decode(response);
  }

  Future<Map<String, dynamic>> create(Map<String, dynamic> body) async {
    final headers = await _headers();
    final response = await http
        .post(
          Uri.parse('$_baseUrl/loans'),
          headers: headers,
          body: jsonEncode(body),
        )
        .timeout(const Duration(seconds: 60));

    return _decode(response);
  }
}

String _errorMessage(Object error) {
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

String _borrowerName(Map<String, dynamic> borrower) {
  final name =
      '${borrower['first_name'] ?? ''} '
              '${borrower['last_name'] ?? ''}'
          .trim();

  return name.isEmpty ? 'Unnamed borrower' : name;
}

class AddLoanPage extends StatefulWidget {
  const AddLoanPage({super.key});

  @override
  State<AddLoanPage> createState() => _AddLoanPageState();
}

class _AddLoanPageState extends State<AddLoanPage> {
  final _api = _LoanApi();
  final _formKey = GlobalKey<FormState>();

  final _borrowerText = TextEditingController();
  final _startText = TextEditingController();
  final _firstDueText = TextEditingController();

  final _principal = TextEditingController();
  final _interest = TextEditingController(text: '3');
  final _terms = TextEditingController(text: '1');
  final _penalty = TextEditingController(text: '0');
  final _remarks = TextEditingController();
  final _notes = TextEditingController();

  Map<String, dynamic>? _borrower;

  late DateTime _startDate;
  DateTime? _firstDueDate;

  String _frequency = 'monthly';
  String? _error;

  bool _saving = false;
  bool _choosingBorrower = false;
  bool _saveUncertain = false;

  bool get _busy => _saving || _choosingBorrower;
  bool get _enabled => !_busy && !_saveUncertain;

  @override
  void initState() {
    super.initState();

    final now = DateTime.now();
    _startDate = DateTime(now.year, now.month, now.day);
    _startText.text = _dateText(_startDate);
  }

  @override
  void dispose() {
    for (final controller in [
      _borrowerText,
      _startText,
      _firstDueText,
      _principal,
      _interest,
      _terms,
      _penalty,
      _remarks,
      _notes,
    ]) {
      controller.dispose();
    }

    super.dispose();
  }

  String _dateText(DateTime date) {
    return '${date.year.toString().padLeft(4, '0')}-'
        '${date.month.toString().padLeft(2, '0')}-'
        '${date.day.toString().padLeft(2, '0')}';
  }

  double? get _estimatedTotal {
    final principal = double.tryParse(_principal.text.trim());
    final interest = double.tryParse(_interest.text.trim());

    if (principal == null ||
        interest == null ||
        !principal.isFinite ||
        !interest.isFinite ||
        principal <= 0 ||
        interest < 0) {
      return null;
    }

    final months = int.tryParse(_terms.text.trim());
    if (months == null || months < 1 || months > 60) {
      return null;
    }
    final principalCents = (principal * 100).round();
    final rateBasisPoints = (interest * 100).round();
    final total =
        (principalCents +
            (principalCents * rateBasisPoints * months / 10000).round()) /
        100;
    return total.isFinite ? total : null;
  }

  Future<void> _chooseBorrower() async {
    if (!_enabled) {
      return;
    }

    setState(() => _choosingBorrower = true);

    try {
      final selected = await Navigator.of(context).push<Map<String, dynamic>>(
        LendRoute<Map<String, dynamic>>(
          builder: (context) => _BorrowerPickerPage(api: _api),
        ),
      );

      if (!mounted) {
        return;
      }

      if (selected != null) {
        setState(() {
          _borrower = selected;
          _borrowerText.text = _borrowerName(selected);
        });
      }
    } finally {
      if (mounted) {
        setState(() => _choosingBorrower = false);
      }
    }
  }

  Future<void> _pickDate({required bool firstDue}) async {
    if (!_enabled) {
      return;
    }

    final picked = await showDatePicker(
      context: context,
      initialDate: firstDue ? (_firstDueDate ?? _startDate) : _startDate,
      firstDate: firstDue ? _startDate : DateTime(2000),
      lastDate: DateTime(2100, 12, 31),
    );

    if (!mounted || picked == null) {
      return;
    }

    setState(() {
      if (firstDue) {
        _firstDueDate = picked;
        _firstDueText.text = _dateText(picked);
      } else {
        _startDate = picked;
        _startText.text = _dateText(picked);

        if (_firstDueDate != null && !_firstDueDate!.isAfter(_startDate)) {
          _firstDueDate = null;
          _firstDueText.clear();
        }
      }
    });
  }

  String? _validateAmount(
    String? value, {
    required double minimum,
    required double maximum,
  }) {
    final text = value?.trim() ?? '';

    if (!RegExp(r'^\d+(\.\d{1,2})?$').hasMatch(text)) {
      return 'Enter a number with up to 2 decimal places.';
    }

    final number = double.tryParse(text);

    if (number == null ||
        !number.isFinite ||
        number < minimum ||
        number > maximum) {
      return 'Enter a value from $minimum to $maximum.';
    }

    return null;
  }

  Future<void> _save() async {
    if (!_enabled) {
      return;
    }
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    final total = _estimatedTotal;

    if (total == null || total > 9999999999.99) {
      setState(() {
        _error = 'The calculated total payable exceeds the supported amount.';
      });
      return;
    }

    FocusScope.of(context).unfocus();

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await _api.create({
        'borrower_id': _borrower!['id'],
        'start_date': _dateText(_startDate),
        'first_due_date': _dateText(_firstDueDate!),
        'principal_amount': _principal.text.trim(),
        'interest_rate': _interest.text.trim(),
        'interest_months': int.parse(_terms.text.trim()),
        'frequency': _frequency,
        'penalty_per_missed_due': _penalty.text.trim(),
        'remarks': _remarks.text.trim(),
        'notes': _notes.text.trim(),
      });

      if (!mounted) {
        return;
      }

      setState(() => _saving = false);
      Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) {
        return;
      }

      final uncertain =
          error is TimeoutException ||
          error is http.ClientException ||
          error is FormatException;

      setState(() {
        _saveUncertain = uncertain;
        _error = uncertain
            ? 'The save result could not be confirmed. Return to the '
                  'Loans page and refresh to check whether the loan was '
                  'created before submitting another one.'
            : _errorMessage(error);
      });
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  Widget _amountField(
    TextEditingController controller,
    String label, {
    required double minimum,
    required double maximum,
    bool percentage = false,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: TextFormField(
        controller: controller,
        enabled: _enabled,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        decoration: InputDecoration(
          floatingLabelBehavior: FloatingLabelBehavior.always,
          labelText: '$label *',
          prefixText: percentage ? null : '₱ ',
          suffixText: percentage ? '%' : null,
          border: const OutlineInputBorder(),
        ),
        onChanged: (_) => setState(() {}),
        validator: (value) =>
            _validateAmount(value, minimum: minimum, maximum: maximum),
      ),
    );
  }

  Widget _dateField({
    required TextEditingController controller,
    required String label,
    required bool firstDue,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: TextFormField(
        controller: controller,
        enabled: _enabled,
        readOnly: true,
        decoration: InputDecoration(
          floatingLabelBehavior: FloatingLabelBehavior.always,
          labelText: '$label *',
          suffixIcon: const Icon(Icons.calendar_month_outlined),
          border: const OutlineInputBorder(),
        ),
        onTap: () => _pickDate(firstDue: firstDue),
        validator: (value) {
          if (value == null || value.isEmpty) {
            return 'Select $label.';
          }

          if (firstDue &&
              (_firstDueDate == null || !_firstDueDate!.isAfter(_startDate))) {
            return 'First due date must be after the start date.';
          }

          return null;
        },
      ),
    );
  }

  Widget _optionalText(TextEditingController controller, String label) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: TextFormField(
        controller: controller,
        enabled: _enabled,
        maxLines: 3,
        maxLength: 2000,
        decoration: InputDecoration(
          floatingLabelBehavior: FloatingLabelBehavior.always,
          labelText: label,
          border: const OutlineInputBorder(),
          counterText: '',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final estimatedTotal = _estimatedTotal;

    return PopScope(
      canPop: !_saving,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Add loan'),
          leading: BackButton(
            onPressed: _saving ? null : () => Navigator.of(context).pop(),
          ),
        ),
        body: SafeArea(
          child: Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                const SectionHeading('Loan details'),
                TextFormField(
                  controller: _borrowerText,
                  enabled: _enabled,
                  readOnly: true,
                  decoration: const InputDecoration(
                    floatingLabelBehavior: FloatingLabelBehavior.always,
                    labelText: 'Borrower *',
                    hintText: 'Tap to search borrowers',
                    suffixIcon: Icon(Icons.person_search_outlined),
                    border: OutlineInputBorder(),
                  ),
                  onTap: _chooseBorrower,
                  validator: (_) =>
                      _borrower == null ? 'Select a borrower.' : null,
                ),
                const SizedBox(height: 16),
                _amountField(
                  _principal,
                  'Principal amount',
                  minimum: 0.01,
                  maximum: 9999999999.99,
                ),
                _amountField(
                  _interest,
                  'Monthly interest rate',
                  minimum: 0,
                  maximum: 999.99,
                  percentage: true,
                ),
                TextFormField(
                  controller: _terms,
                  onChanged: (_) => setState(() {}),
                  enabled: _enabled,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    floatingLabelBehavior: FloatingLabelBehavior.always,
                    labelText: 'Loan term (months) *',
                    border: OutlineInputBorder(),
                  ),
                  validator: (value) {
                    final count = int.tryParse(value?.trim() ?? '');

                    if (count == null || count < 1 || count > 60) {
                      return 'Enter a whole number from 1 to 60.';
                    }

                    return null;
                  },
                ),
                const SizedBox(height: 16),
                DropdownButtonFormField<String>(
                  initialValue: _frequency,
                  isExpanded: true,
                  decoration: const InputDecoration(
                    floatingLabelBehavior: FloatingLabelBehavior.always,
                    labelText: 'Payment frequency *',
                    border: OutlineInputBorder(),
                  ),
                  items: const [
                    DropdownMenuItem(value: 'daily', child: Text('Daily')),
                    DropdownMenuItem(value: 'weekly', child: Text('Weekly')),
                    DropdownMenuItem(
                      value: 'semi-monthly',
                      child: Text('Semi-monthly (every 15 days)'),
                    ),
                    DropdownMenuItem(value: 'monthly', child: Text('Monthly')),
                  ],
                  onChanged: !_enabled
                      ? null
                      : (value) {
                          if (value != null) {
                            setState(() => _frequency = value);
                          }
                        },
                ),
                const SizedBox(height: 16),
                _dateField(
                  controller: _startText,
                  label: 'Start date',
                  firstDue: false,
                ),
                _dateField(
                  controller: _firstDueText,
                  label: 'First due date',
                  firstDue: true,
                ),
                _amountField(
                  _penalty,
                  'Penalty per missed installment',
                  minimum: 0,
                  maximum: 9999999999.99,
                ),
                _optionalText(_remarks, 'Remarks'),
                _optionalText(_notes, 'Notes'),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Total payable (before penalties)',
                          style: TextStyle(fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          estimatedTotal == null
                              ? '—'
                              : '₱${estimatedTotal.toStringAsFixed(2)}',
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                if (_error != null) ...[
                  Text(_error!, style: const TextStyle(color: Colors.red)),
                  const SizedBox(height: 16),
                ],
                FilledButton.icon(
                  onPressed: _enabled ? _save : null,
                  icon: const Icon(Icons.save_outlined),
                  label: Text(_saving ? 'Saving…' : 'Create loan'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _BorrowerPickerPage extends StatefulWidget {
  const _BorrowerPickerPage({required this.api});

  final _LoanApi api;

  @override
  State<_BorrowerPickerPage> createState() => _BorrowerPickerPageState();
}

class _BorrowerPickerPageState extends State<_BorrowerPickerPage> {
  final _search = TextEditingController();

  List<Map<String, dynamic>> _borrowers = [];
  String _query = '';
  String? _error;
  bool _loading = true;

  int _page = 1;
  int _lastPage = 1;

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

  Future<void> _load(int page) async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final query = Uri(
        queryParameters: {
          'page': '$page',
          if (_query.isNotEmpty) 'search': _query,
        },
      ).query;

      final data = await widget.api.get('borrowers?$query');
      final records = data['data'];

      if (records is! List) {
        throw const FormatException('Invalid borrower list.');
      }

      final borrowers = records
          .map((record) => Map<String, dynamic>.from(record as Map))
          .toList();

      if (!mounted) {
        return;
      }

      setState(() {
        _borrowers = borrowers;
        _page = data['current_page'] as int;
        _lastPage = data['last_page'] as int;
      });
    } catch (error) {
      if (mounted) {
        setState(() => _error = _errorMessage(error));
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  void _runSearch() {
    if (_loading) {
      return;
    }

    FocusScope.of(context).unfocus();
    _query = _search.text.trim();
    _load(1);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Select borrower')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: _search,
              enabled: !_loading,
              maxLength: 100,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _runSearch(),
              decoration: InputDecoration(
                floatingLabelBehavior: FloatingLabelBehavior.always,
                labelText: 'Search name, phone, or email',
                border: const OutlineInputBorder(),
                counterText: '',
                suffixIcon: IconButton(
                  onPressed: _loading ? null : _runSearch,
                  icon: const Icon(Icons.search),
                ),
              ),
            ),
          ),
          Expanded(
            child: _loading
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
                            onPressed: () => _load(_page),
                            child: const Text('Retry'),
                          ),
                        ],
                      ),
                    ),
                  )
                : _borrowers.isEmpty
                ? const EmptyRecords('No borrowers found.')
                : ListView.builder(
                    itemCount: _borrowers.length,
                    itemBuilder: (context, index) {
                      final borrower = _borrowers[index];

                      return ListTile(
                        leading: const Icon(Icons.person_outline),
                        title: Text(_borrowerName(borrower)),
                        subtitle: Text(
                          'ID: ${borrower['id']} · '
                          'Status: ${borrower['status'] ?? '—'}',
                        ),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () {
                          Navigator.of(context).pop(borrower);
                        },
                      );
                    },
                  ),
          ),
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
                onPressed: !_loading && _error == null && _page > 1
                    ? () => _load(_page - 1)
                    : null,
                child: const Text('Previous'),
              ),
              Text('$_page / $_lastPage'),
              TextButton(
                onPressed: !_loading && _error == null && _page < _lastPage
                    ? () => _load(_page + 1)
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

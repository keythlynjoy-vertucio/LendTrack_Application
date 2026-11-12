import 'design/lend_design.dart';

import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'services/session_http.dart' as http;
import 'services/session.dart';

class _PaymentApiException implements Exception {
  const _PaymentApiException(this.statusCode, this.message);

  final int statusCode;
  final String message;

  @override
  String toString() => message;
}

class RecordPaymentPage extends StatefulWidget {
  const RecordPaymentPage({super.key, required this.loan});

  final Map<String, dynamic> loan;

  @override
  State<RecordPaymentPage> createState() => _RecordPaymentPageState();
}

class _RecordPaymentPageState extends State<RecordPaymentPage> {
  static const _baseUrl = apiBaseUrl;

  final _storage = const FlutterSecureStorage();
  final _formKey = GlobalKey<FormState>();

  final _amount = TextEditingController();
  final _penalty = TextEditingController(text: '0');
  final _dateController = TextEditingController();
  final _reference = TextEditingController();
  final _remarks = TextEditingController();

  late DateTime _paymentDate;
  String _method = 'cash';

  String? _draftKey;
  Map<String, dynamic>? _pendingBody;

  bool _loading = true;
  bool _saving = false;
  bool _confirmed = false;

  String? _error;
  bool _quoteReady = false;

  bool get _canEdit =>
      !_loading && !_saving && !_confirmed && _pendingBody == null;

  @override
  void initState() {
    super.initState();

    final now = DateTime.now();
    _paymentDate = DateTime(now.year, now.month, now.day);
    _dateController.text = _dateText(_paymentDate);

    _loadPendingPayment();
  }

  @override
  void dispose() {
    _amount.dispose();
    _penalty.dispose();
    _dateController.dispose();
    _reference.dispose();
    _remarks.dispose();
    super.dispose();
  }

  String _dateText(DateTime date) {
    return '${date.year.toString().padLeft(4, '0')}-'
        '${date.month.toString().padLeft(2, '0')}-'
        '${date.day.toString().padLeft(2, '0')}';
  }

  String _money(dynamic value) => formatMoney(value);

  String _message(Object error) {
    if (error is TimeoutException) {
      return 'Connection timed out.';
    }

    if (error is http.ClientException) {
      return 'Cannot connect. Check that Laravel is running.';
    }

    if (error is FormatException) {
      return 'Unexpected server response.';
    }

    return error.toString().replaceFirst('Exception: ', '');
  }

  Future<Map<String, String>> _headers() async {
    final token = await _storage.read(key: 'auth_token');

    if (token == null || token.isEmpty) {
      throw const _PaymentApiException(401, 'Please return and log in again.');
    }

    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer $token',
    };
  }

  Map<String, dynamic> _decode(http.Response response) {
    if (response.statusCode == 401) {
      throw const _PaymentApiException(
        401,
        'Session expired. Return and log in again.',
      );
    }

    if (response.statusCode == 403) {
      throw const _PaymentApiException(
        403,
        'Your account does not have permission to record payments.',
      );
    }

    final decoded = jsonDecode(response.body);

    if (decoded is! Map<String, dynamic>) {
      throw const FormatException('Invalid response.');
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      final errors = decoded['errors'];

      if (errors is Map && errors.isNotEmpty) {
        final message = errors.values
            .map((value) {
              return value is List ? value.join('\n') : value.toString();
            })
            .join('\n');

        throw _PaymentApiException(response.statusCode, message);
      }

      throw _PaymentApiException(
        response.statusCode,
        decoded['message']?.toString() ??
            'Request failed (${response.statusCode}).',
      );
    }

    return decoded;
  }

  Future<void> _loadPendingPayment() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final headers = await _headers();

      final response = await http
          .get(Uri.parse('$_baseUrl/me'), headers: headers)
          .timeout(const Duration(seconds: 30));

      final account = _decode(response);
      final user = account['user'];

      if (user is! Map || user['id'] == null) {
        throw const FormatException('Invalid user response.');
      }

      final draftKey =
          'pending_payment_v1_'
          'user_${user['id']}_loan_${widget.loan['id']}';

      final saved = await _storage.read(key: draftKey);
      Map<String, dynamic>? pending;

      if (saved != null) {
        final decoded = jsonDecode(saved);

        if (decoded is! Map<String, dynamic> ||
            decoded['client_request_id'] is! String ||
            decoded['amount'] == null ||
            decoded['penalty_paid'] == null ||
            decoded['paid_at'] is! String ||
            !['cash', 'gcash', 'bank', 'manual'].contains(decoded['method'])) {
          throw const FormatException('Invalid pending payment.');
        }

        final date = DateTime.tryParse(decoded['paid_at'] as String);

        if (date == null) {
          throw const FormatException('Invalid pending payment date.');
        }

        pending = decoded;
        _amount.text = decoded['amount'].toString();
        _penalty.text = decoded['penalty_paid'].toString();
        _paymentDate = date;
        _dateController.text = _dateText(date);
        _method = decoded['method'] as String;
        _reference.text = decoded['reference_no']?.toString() ?? '';
        _remarks.text = decoded['remarks']?.toString() ?? '';
      }

      if (!mounted) {
        return;
      }

      setState(() {
        _draftKey = draftKey;
        _pendingBody = pending;
      });
      if (pending == null) {
        await _fillSuggested();
      }
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

  Future<void> _fillSuggested() async {
    _quoteReady = false;
    final response = await http
        .get(
          Uri.parse(
            '$_baseUrl/loans/${widget.loan['id']}/payment-quote?date=${_dateText(_paymentDate)}',
          ),
          headers: await _headers(),
        )
        .timeout(const Duration(seconds: 30));
    final quote = _decode(response);
    if (!mounted) {
      return;
    }
    _amount.text = (num.parse(quote['suggested_regular'].toString()))
        .toStringAsFixed(2);
    _penalty.text = (num.parse(quote['penalty_outstanding'].toString()))
        .toStringAsFixed(2);
    _quoteReady = true;
  }

  String _newRequestId() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));

    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    final hex = bytes
        .map((value) => value.toRadixString(16).padLeft(2, '0'))
        .join();

    return '${hex.substring(0, 8)}-'
        '${hex.substring(8, 12)}-'
        '${hex.substring(12, 16)}-'
        '${hex.substring(16, 20)}-'
        '${hex.substring(20)}';
  }

  String? _validateAmount(String? value, {required bool positive}) {
    final text = value?.trim() ?? '';

    if (!RegExp(r'^\d+(\.\d{1,2})?$').hasMatch(text)) {
      return 'Enter a number with up to 2 decimal places.';
    }

    final number = double.tryParse(text);
    final minimum = positive ? 0.01 : 0.0;

    if (number == null ||
        !number.isFinite ||
        number < minimum ||
        number > 9999999999.99) {
      return 'Enter a value from $minimum to 9999999999.99.';
    }

    return null;
  }

  Future<void> _pickDate() async {
    if (!_canEdit) {
      return;
    }

    final selected = await showDatePicker(
      context: context,
      initialDate: _paymentDate,
      firstDate: DateTime(2000),
      lastDate: DateTime.now(),
    );

    if (!mounted || selected == null) {
      return;
    }

    setState(() {
      _loading = true;
      _paymentDate = selected;
      _dateController.text = _dateText(selected);
    });
    try {
      await _fillSuggested();
    } catch (e) {
      if (mounted) {
        setState(() => _error = _message(e));
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _submit() async {
    if (_loading || _saving || _confirmed || _draftKey == null) {
      return;
    }

    if (_pendingBody == null && !(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    FocusScope.of(context).unfocus();

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      if (_pendingBody == null && !_quoteReady) {
        throw Exception(
          'Suggested amounts could not load. Reopen this page and try again.',
        );
      }
      if (_pendingBody == null) {
        final body = <String, dynamic>{
          'client_request_id': _newRequestId(),
          'amount': _amount.text.trim(),
          'penalty_paid': _penalty.text.trim(),
          'paid_at': _dateText(_paymentDate),
          'method': _method,
          'reference_no': _reference.text.trim(),
          'remarks': _remarks.text.trim(),
        };

        // Persist before sending so a retry uses the same submission.
        await _storage.write(key: _draftKey!, value: jsonEncode(body));

        if (!mounted) {
          return;
        }
        setState(() => _pendingBody = body);
      }

      final headers = await _headers();

      final response = await http
          .post(
            Uri.parse('$_baseUrl/loans/${widget.loan['id']}/payments'),
            headers: headers,
            body: jsonEncode(_pendingBody),
          )
          .timeout(const Duration(seconds: 60));

      final result = _decode(response);

      if (result['data'] is! Map || result['loan'] is! Map) {
        throw const FormatException('Invalid payment confirmation.');
      }

      // The server has confirmed the payment.
      // Cleanup failure must not make it appear unsuccessful.
      try {
        await _storage.delete(key: _draftKey!);
      } catch (_) {
        // A remaining draft will replay the same request safely.
      }

      if (!mounted) {
        return;
      }

      setState(() {
        _confirmed = true;
        _saving = false;
      });

      await _showConfirmation(result);

      if (!mounted) {
        return;
      }
      Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) {
        return;
      }

      // A validation response means this submission was rejected.
      // Allow the user to correct its fields.
      if (error is _PaymentApiException && error.statusCode == 422) {
        try {
          await _storage.delete(key: _draftKey!);

          if (!mounted) {
            return;
          }
          setState(() => _pendingBody = null);
        } catch (_) {
          // Keep the pending request if cleanup fails.
        }
      }

      if (!mounted) {
        return;
      }

      setState(() {
        _error = _message(error);

        if (_pendingBody != null && error is! _PaymentApiException) {
          _error =
              'The save result could not be confirmed. '
              'Retry the same payment below, or return to the loan '
              'details and refresh its payment history.';
        }
      });
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  Future<void> _showConfirmation(Map<String, dynamic> result) async {
    final payment = Map<String, dynamic>.from(result['data'] as Map);
    final loan = Map<String, dynamic>.from(result['loan'] as Map);

    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
        title: Reveal(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: const BoxDecoration(
                  color: Color(0xFFEAF5F2),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.check_rounded,
                  color: lendTeal,
                  size: 30,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                result['replayed'] == true
                    ? 'Payment already recorded'
                    : 'Payment recorded',
              ),
            ],
          ),
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Payment ID: ${payment['id']}'),
              const SizedBox(height: 8),
              Text('Loan: ${loan['reference_no']}'),
              const SizedBox(height: 8),
              Text('Regular amount: ${_money(payment['amount'])}'),
              Text('Penalty paid: ${_money(payment['penalty_paid'])}'),
              Text('Total received: ${_money(payment['total_received'])}'),
              const SizedBox(height: 8),
              Text('Date: ${payment['paid_at']}'),
              Text('Method: ${payment['method']}'),
              const SizedBox(height: 8),
              Text(
                'Remaining regular balance: '
                '${_money(loan['remaining_regular_balance'])}',
              ),
              Text('Loan status: ${loan['status']}'),
            ],
          ),
        ),
        actions: [
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Done'),
          ),
        ],
      ),
    );
  }

  Widget _amountField(
    TextEditingController controller,
    String label, {
    required bool positive,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: TextFormField(
        controller: controller,
        enabled: _canEdit,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        decoration: InputDecoration(
          floatingLabelBehavior: FloatingLabelBehavior.always,
          labelText: '$label *',
          prefixText: '₱ ',
          border: const OutlineInputBorder(),
        ),
        onChanged: (_) => setState(() {}),
        validator: (value) => _validateAmount(value, positive: positive),
      ),
    );
  }

  Widget _form() {
    final amount = double.tryParse(_amount.text.trim()) ?? 0;
    final penalty = double.tryParse(_penalty.text.trim()) ?? 0;

    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          const SectionHeading('Payment details'),
          Text(
            '${widget.loan['reference_no'] ?? 'Loan'}',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: 16),
          if (_pendingBody != null) ...[
            const Card(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: Text('Pending payment • Retry same payment'),
              ),
            ),
            const SizedBox(height: 16),
          ],
          _amountField(_amount, 'Regular payment', positive: false),
          _amountField(_penalty, 'Penalty paid', positive: false),
          TextFormField(
            controller: _dateController,
            enabled: _canEdit,
            readOnly: true,
            onTap: _pickDate,
            decoration: const InputDecoration(
              floatingLabelBehavior: FloatingLabelBehavior.always,
              labelText: 'Payment date *',
              suffixIcon: Icon(Icons.calendar_month_outlined),
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            key: ValueKey(_method),
            initialValue: _method,
            decoration: const InputDecoration(
              floatingLabelBehavior: FloatingLabelBehavior.always,
              labelText: 'Payment method *',
              border: OutlineInputBorder(),
            ),
            items: const [
              DropdownMenuItem(value: 'cash', child: Text('Cash')),
              DropdownMenuItem(value: 'gcash', child: Text('GCash')),
              DropdownMenuItem(value: 'bank', child: Text('Bank')),
              DropdownMenuItem(value: 'manual', child: Text('Manual')),
            ],
            onChanged: !_canEdit
                ? null
                : (value) {
                    if (value != null) {
                      setState(() => _method = value);
                    }
                  },
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _reference,
            enabled: _canEdit,
            maxLength: 255,
            decoration: const InputDecoration(
              floatingLabelBehavior: FloatingLabelBehavior.always,
              labelText: 'Transaction reference (optional)',
              border: OutlineInputBorder(),
              counterText: '',
            ),
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _remarks,
            enabled: _canEdit,
            maxLength: 2000,
            maxLines: 3,
            decoration: const InputDecoration(
              floatingLabelBehavior: FloatingLabelBehavior.always,
              labelText: 'Remarks (optional)',
              border: OutlineInputBorder(),
              counterText: '',
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'Total received: ${_money(amount + penalty)}',
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 16),
          if (_error != null) ...[
            Text(_error!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
          ],
          FilledButton.icon(
            onPressed: _saving || _confirmed ? null : _submit,
            icon: const Icon(Icons.payments_outlined),
            label: Text(
              _saving
                  ? 'Saving…'
                  : _pendingBody != null
                  ? 'Retry same payment'
                  : 'Record payment',
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: !_saving,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Record payment'),
          leading: BackButton(
            onPressed: _saving ? null : () => Navigator.of(context).pop(),
          ),
        ),
        body: SafeArea(
          child: _loading
              ? const AppLoading()
              : _draftKey == null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          _error ?? 'Unable to prepare payment form.',
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 16),
                        FilledButton(
                          onPressed: _loadPendingPayment,
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  ),
                )
              : _form(),
        ),
      ),
    );
  }
}

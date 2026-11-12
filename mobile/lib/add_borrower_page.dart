import 'design/lend_design.dart';

import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'services/session_http.dart' as http;
import 'services/session.dart';

class AddBorrowerPage extends StatefulWidget {
  const AddBorrowerPage({super.key, this.borrower});

  final Map<String, dynamic>? borrower;

  @override
  State<AddBorrowerPage> createState() => _AddBorrowerPageState();
}

class _AddBorrowerPageState extends State<AddBorrowerPage> {
  static const _baseUrl = apiBaseUrl;

  final _formKey = GlobalKey<FormState>();
  final _storage = const FlutterSecureStorage();

  final _fields = {
    'first_name': TextEditingController(),
    'last_name': TextEditingController(),
    'phone': TextEditingController(),
    'email': TextEditingController(),
    'street_house': TextEditingController(),
    'notes': TextEditingController(),
  };

  List<Map<String, String>> _provinces = [];
  List<Map<String, String>> _cities = [];
  List<Map<String, String>> _barangays = [];

  String? _province;
  String? _city;
  String? _barangay;
  String? _error;

  Future<void> Function()? _retryLookup;

  bool _changeAddress = false;
  bool _loading = false;
  bool _saving = false;

  bool get _editing => widget.borrower != null;
  bool get _busy => _loading || _saving;
  bool get _showAddress => !_editing || _changeAddress;

  @override
  void initState() {
    super.initState();

    final borrower = widget.borrower;

    if (borrower != null) {
      for (final entry in _fields.entries) {
        entry.value.text = borrower[entry.key]?.toString() ?? '';
      }
    } else {
      _runLookup(_prepareAddress);
    }
  }

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

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
      throw Exception('Your account cannot perform this action.');
    }

    if (response.statusCode == 404) {
      throw Exception('The requested record is no longer available.');
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

  String _message(Object error) {
    if (error is TimeoutException) {
      return 'Connection timed out. Please try again.';
    }

    if (error is http.ClientException) {
      return 'Cannot connect. Check Laravel and your connection.';
    }

    if (error is FormatException) {
      return 'Unexpected server response. Please check the API.';
    }

    return error.toString().replaceFirst('Exception: ', '');
  }

  Future<List<Map<String, String>>> _getAreas(String path) async {
    final headers = await _headers();

    final response = await http
        .get(Uri.parse('$_baseUrl/$path'), headers: headers)
        .timeout(const Duration(seconds: 30));

    final data = _decode(response);
    final records = data['data'];

    if (records is! List) {
      throw const FormatException('Invalid address list.');
    }

    final items = records.map<Map<String, String>>((item) {
      if (item is! Map || item['code'] is! String || item['name'] is! String) {
        throw const FormatException('Invalid address option.');
      }

      return {'code': item['code'] as String, 'name': item['name'] as String};
    }).toList();

    if (items.isEmpty) {
      throw Exception('No address options returned for this selection.');
    }

    return items;
  }

  Future<void> _runLookup(Future<void> Function() task) async {
    if (!mounted) {
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
      _retryLookup = null;
    });

    try {
      await task();
    } catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _error = _message(error);
        _retryLookup = task;
      });
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  String? _matchingCode(List<Map<String, String>> items, dynamic value) {
    final code = value?.toString();

    return items.any((item) => item['code'] == code) ? code : null;
  }

  Future<void> _prepareAddress() async {
    final provinces = await _getAreas('psgc/provinces');
    provinces.add({
      'code': '1300000000',
      'name': 'NCR / Metro Manila (no province)',
    });

    final province = _matchingCode(
      provinces,
      widget.borrower?['province_code'] ?? widget.borrower?['region_code'],
    );

    List<Map<String, String>> cities = [];
    List<Map<String, String>> barangays = [];
    String? city;
    String? barangay;

    if (province != null) {
      cities = await _getAreas(
        province == '1300000000'
            ? 'psgc/regions/1300000000/cities-municipalities'
            : 'psgc/provinces/$province/cities-municipalities',
      );

      city = _matchingCode(cities, widget.borrower?['city_municipality_code']);

      if (city != null) {
        barangays = await _getAreas(
          'psgc/cities-municipalities/$city/barangays',
        );

        barangay = _matchingCode(barangays, widget.borrower?['barangay_code']);
      }
    }

    if (!mounted) {
      return;
    }

    setState(() {
      _provinces = provinces;
      _cities = cities;
      _barangays = barangays;
      _province = province;
      _city = city;
      _barangay = barangay;
    });
  }

  Future<void> _loadCities(String province) async {
    final cities = await _getAreas(
      province == '1300000000'
          ? 'psgc/regions/1300000000/cities-municipalities'
          : 'psgc/provinces/$province/cities-municipalities',
    );

    if (!mounted) {
      return;
    }
    setState(() => _cities = cities);
  }

  Future<void> _loadBarangays(String city) async {
    final barangays = await _getAreas(
      'psgc/cities-municipalities/$city/barangays',
    );

    if (!mounted) {
      return;
    }
    setState(() => _barangays = barangays);
  }

  Future<void> _save() async {
    if (_busy) {
      return;
    }
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    FocusScope.of(context).unfocus();

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final headers = await _headers();

      final body = <String, dynamic>{
        for (final entry in _fields.entries)
          if (entry.key != 'street_house') entry.key: entry.value.text.trim(),
      };

      if (_showAddress) {
        body.addAll({
          'region_code': _province == '1300000000' ? _province : null,
          'province_code': _province == '1300000000' ? null : _province,
          'city_municipality_code': _city,
          'barangay_code': _barangay,
          'street_house': _fields['street_house']!.text.trim(),
        });
      }

      final uri = Uri.parse(
        _editing
            ? '$_baseUrl/borrowers/${widget.borrower!['id']}'
            : '$_baseUrl/borrowers',
      );

      final response =
          await (_editing
                  ? http.patch(uri, headers: headers, body: jsonEncode(body))
                  : http.post(uri, headers: headers, body: jsonEncode(body)))
              .timeout(const Duration(seconds: 60));

      _decode(response);

      if (!mounted) {
        return;
      }

      setState(() => _saving = false);
      Navigator.of(context).pop(true);
    } on TimeoutException {
      if (!mounted) {
        return;
      }

      setState(() {
        _error = _editing
            ? 'Save timed out. Return to the list and check the borrower '
                  'details before trying again.'
            : 'Save timed out. Check the borrower list before submitting '
                  'again to avoid a duplicate record.';
      });
    } catch (error) {
      if (mounted) {
        setState(() => _error = _message(error));
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  Widget _textField(
    String field,
    String label, {
    bool required = false,
    int maxLength = 255,
    int lines = 1,
    TextInputType? keyboard,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: TextFormField(
        controller: _fields[field],
        enabled: !_busy,
        maxLength: maxLength,
        maxLines: lines,
        keyboardType: keyboard,
        decoration: InputDecoration(
          floatingLabelBehavior: FloatingLabelBehavior.always,
          labelText: required ? '$label *' : label,
          border: const OutlineInputBorder(),
          counterText: '',
        ),
        validator: (value) {
          final text = value?.trim() ?? '';

          if (required && text.isEmpty) {
            return 'Enter $label.';
          }

          if (field == 'email' &&
              text.isNotEmpty &&
              !RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(text)) {
            return 'Enter a valid email address.';
          }

          return null;
        },
      ),
    );
  }

  Widget _dropdown(
    String label,
    String? selected,
    List<Map<String, String>> items,
    ValueChanged<String?> onChanged,
  ) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: DropdownButtonFormField<String>(
        key: ValueKey('$label|$selected|${items.length}'),
        initialValue: selected,
        isExpanded: true,
        decoration: InputDecoration(
          floatingLabelBehavior: FloatingLabelBehavior.always,
          labelText: '$label *',
          border: const OutlineInputBorder(),
        ),
        items: items.map((item) {
          return DropdownMenuItem<String>(
            value: item['code'],
            child: Text(item['name']!, overflow: TextOverflow.ellipsis),
          );
        }).toList(),
        onChanged: _busy || items.isEmpty ? null : onChanged,
        validator: (value) => value == null ? 'Select $label.' : null,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final currentAddress = widget.borrower?['address']?.toString().trim() ?? '';

    return PopScope(
      canPop: !_saving,
      child: Scaffold(
        appBar: AppBar(
          title: Text(_editing ? 'Edit borrower' : 'Add borrower'),
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
                const SectionHeading('Borrower details'),
                _textField('first_name', 'First name', required: true),
                _textField('last_name', 'Last name', required: true),
                _textField(
                  'phone',
                  'Phone number',
                  maxLength: 32,
                  keyboard: TextInputType.phone,
                ),
                _textField(
                  'email',
                  'Email address',
                  keyboard: TextInputType.emailAddress,
                ),
                const Text(
                  'Address',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                if (_editing) ...[
                  Text(
                    currentAddress.isEmpty
                        ? 'No saved address.'
                        : 'Current address: $currentAddress',
                  ),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Change address'),
                    value: _changeAddress,
                    onChanged: _busy
                        ? null
                        : (value) {
                            setState(() {
                              _changeAddress = value;
                              _error = null;
                              _retryLookup = null;
                            });

                            if (value) {
                              _runLookup(_prepareAddress);
                            }
                          },
                  ),
                ],
                if (_showAddress) ...[
                  _dropdown('Province / NCR', _province, _provinces, (value) {
                    if (value == null) {
                      return;
                    }

                    setState(() {
                      _province = value;
                      _city = null;
                      _barangay = null;
                      _cities = [];
                      _barangays = [];
                    });

                    _runLookup(() => _loadCities(value));
                  }),
                  _dropdown('City/Municipality', _city, _cities, (value) {
                    if (value == null) {
                      return;
                    }

                    setState(() {
                      _city = value;
                      _barangay = null;
                      _barangays = [];
                    });

                    _runLookup(() => _loadBarangays(value));
                  }),
                  _dropdown('Barangay', _barangay, _barangays, (value) {
                    setState(() => _barangay = value);
                  }),
                  if (_loading) ...[
                    const LookupProgress(),
                    const SizedBox(height: 16),
                  ],
                  _textField(
                    'street_house',
                    'Street/House details',
                    required: true,
                    maxLength: 150,
                  ),
                ],
                const SizedBox(height: 8),
                _textField('notes', 'Notes', lines: 3, maxLength: 2000),
                if (_error != null) ...[
                  Text(_error!, style: const TextStyle(color: Colors.red)),
                  const SizedBox(height: 12),
                ],
                if (_retryLookup != null)
                  TextButton(
                    onPressed: _busy ? null : () => _runLookup(_retryLookup!),
                    child: const Text('Retry address lookup'),
                  ),
                FilledButton.icon(
                  onPressed: _busy || _retryLookup != null ? null : _save,
                  icon: const Icon(Icons.save_outlined),
                  label: Text(
                    _saving
                        ? 'Saving…'
                        : _editing
                        ? 'Save changes'
                        : 'Save borrower',
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

import 'design/lend_design.dart';

import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'services/session_http.dart' as http;
import 'services/session.dart';

import 'add_borrower_page.dart';

class BorrowersPage extends StatefulWidget {
  const BorrowersPage({super.key});

  @override
  State<BorrowersPage> createState() => _BorrowersPageState();
}

class _BorrowersPageState extends State<BorrowersPage> {
  static const _baseUrl = apiBaseUrl;

  final _storage = const FlutterSecureStorage();

  List<Map<String, dynamic>> _borrowers = [];

  bool _loading = true;
  bool _openingDetails = false;
  bool _fetchingDetails = false;
  bool _openingForm = false;

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

  bool get _busy => _loading || _openingDetails || _openingForm;

  @override
  void initState() {
    super.initState();
    _loadPage(1);
  }

  Future<Map<String, dynamic>> _get(String path) async {
    final token = await _storage.read(key: 'auth_token');

    if (token == null || token.isEmpty) {
      throw Exception('Please return and log in again.');
    }

    final response = await http
        .get(
          Uri.parse('$_baseUrl/$path'),
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
      throw Exception('The requested record is no longer available.');
    }

    if (response.statusCode != 200) {
      throw Exception('Request failed (${response.statusCode}).');
    }

    final decoded = jsonDecode(response.body);

    if (decoded is! Map<String, dynamic>) {
      throw const FormatException('Unexpected server response.');
    }

    return decoded;
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
      final data = await _get(
        'borrowers?page=$page&search=${Uri.encodeQueryComponent(_appliedSearch)}&status=$_status',
      );
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
        _total = data['total'] as int;
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

  String _borrowerName(Map<String, dynamic> borrower) {
    final firstName = borrower['first_name']?.toString().trim() ?? '';
    final lastName = borrower['last_name']?.toString().trim() ?? '';
    final name = '$firstName $lastName'.trim();

    return name.isEmpty ? 'Unnamed borrower' : name;
  }

  // Dialogs measure intrinsic width. Avoid LayoutBuilder in this content.
  Widget _field(String label, dynamic value) {
    final raw = value?.toString().trim();
    final text = raw == null || raw.isEmpty ? '—' : raw;
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(color: lendMuted, fontSize: 12)),
          const SizedBox(height: 3),
          Text(text),
        ],
      ),
    );
  }

  Future<void> _showDetails(int id) async {
    if (_busy) {
      return;
    }

    setState(() {
      _openingDetails = true;
      _fetchingDetails = true;
    });

    Map<String, dynamic>? editBorrower;

    try {
      final borrower = await _get('borrowers/$id');

      if (!mounted) {
        return;
      }

      setState(() => _fetchingDetails = false);

      final edit = await showDialog<bool>(
        context: context,
        builder: (dialogContext) {
          return AlertDialog(
            title: const Text('Borrower details'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _field('Name', _borrowerName(borrower)),
                  _field('Phone', borrower['phone']),
                  _field('Email', borrower['email']),
                  _field('Address', borrower['address']),
                  _field('Status', borrower['status']),
                  _field('Loans', borrower['loans_count']),
                  _field('Notes', borrower['notes']),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: const Text('Close'),
              ),
              FilledButton.icon(
                onPressed: () => Navigator.of(dialogContext).pop(true),
                icon: const Icon(Icons.edit_outlined),
                label: const Text('Edit'),
              ),
            ],
          );
        },
      );

      if (edit == true) {
        editBorrower = borrower;
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_message(error))));
      }
    } finally {
      if (mounted) {
        setState(() {
          _openingDetails = false;
          _fetchingDetails = false;
        });
      }
    }

    if (!mounted) {
      return;
    }

    if (editBorrower != null) {
      await _openForm(borrower: editBorrower);
    }
  }

  Future<void> _openForm({Map<String, dynamic>? borrower}) async {
    if (_busy) {
      return;
    }

    setState(() => _openingForm = true);

    try {
      final saved = await Navigator.of(context).push<bool>(
        LendRoute<bool>(
          builder: (context) => AddBorrowerPage(borrower: borrower),
        ),
      );

      if (!mounted) {
        return;
      }

      if (saved == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              borrower == null
                  ? 'Borrower created successfully.'
                  : 'Borrower updated successfully.',
            ),
          ),
        );

        await _loadPage(borrower == null ? 1 : _page);
      }
    } finally {
      if (mounted) {
        setState(() => _openingForm = false);
      }
    }
  }

  Widget _buildBorrowerCard(Map<String, dynamic> borrower) {
    final name = _borrowerName(borrower);
    final initial = name.isEmpty ? '?' : name.characters.first.toUpperCase();
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: _busy ? null : () => _showDetails(borrower['id'] as int),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                backgroundColor: const Color(0xFFEAF5F2),
                foregroundColor: lendTeal,
                child: Text(
                  initial,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name, style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 10,
                      runSpacing: 6,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        StatusPill('${borrower['status'] ?? ''}'),
                        Text(
                          '${borrower['loans_count'] ?? 0} loans',
                          style: const TextStyle(
                            color: lendMuted,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: lendMuted),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBody() {
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
          child: Text('Total borrowers: $_total'),
        ),
        if (_fetchingDetails) const LookupProgress(),
        Expanded(
          child: _borrowers.isEmpty
              ? const EmptyRecords('No borrowers found.')
              : ListView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 88),
                  itemCount: _borrowers.length,
                  itemBuilder: (context, index) {
                    return _buildBorrowerCard(_borrowers[index]);
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
            labelText: 'Search name, phone or email',
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
          items: ['', 'active', 'inactive']
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
        title: const Text('Borrowers'),
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
          Expanded(child: _buildBody()),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        tooltip: 'Add borrower',
        onPressed: _busy ? null : () => _openForm(),
        child: const Icon(Icons.person_add_alt_1),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
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

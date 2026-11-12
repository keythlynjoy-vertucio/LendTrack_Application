import 'dart:async';

import 'package:flutter/material.dart';

import 'design/lend_design.dart';
import 'services/api.dart';
import 'loans_page.dart';
import 'borrowers_page.dart';
import 'collections_page.dart';
import 'collection_report_page.dart';
import 'management_page.dart';
import 'add_staff_page.dart';

class HomePage extends StatefulWidget {
  final Map<String, dynamic> user;
  final VoidCallback onLogout;
  final bool busy;
  final String? error;
  const HomePage({
    super.key,
    required this.user,
    required this.onLogout,
    required this.busy,
    this.error,
  });
  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  Map<String, dynamic>? _dashboard;
  String? _error;
  bool _loading = false;
  bool _opening = false;
  bool _refreshQueued = false;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool afterChange = false}) async {
    if (_loading) {
      _refreshQueued = _refreshQueued || afterChange;
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await Api.request('dashboard');
      if (mounted) {
        setState(() => _dashboard = data);
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'Could not refresh your overview. Check your connection and try again.',
        );
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
        if (_refreshQueued) {
          _refreshQueued = false;
          unawaited(_load());
        }
      }
    }
  }

  Future<void> _open(Widget page) async {
    if (_opening || widget.busy) {
      return;
    }
    setState(() => _opening = true);
    try {
      await Navigator.of(context).push<void>(LendRoute(builder: (_) => page));
    } finally {
      if (mounted) {
        setState(() => _opening = false);
        unawaited(_load(afterChange: true));
      }
    }
  }

  Widget _link(String label, String subtitle, IconData icon, Widget page) =>
      Card(
        child: ListTile(
          enabled: !widget.busy && !_opening,
          leading: Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: const Color(0xFFEAF5F2),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: lendTeal),
          ),
          title: Text(
            label,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
          subtitle: Text(
            subtitle,
            style: const TextStyle(color: lendMuted, fontSize: 12),
          ),
          trailing: const Icon(Icons.chevron_right_rounded, color: lendMuted),
          onTap: () => _open(page),
        ),
      );

  Widget _overview() {
    if (_loading && _dashboard == null) {
      return const SizedBox(
        height: 170,
        child: AppLoading(label: 'Refreshing overview…'),
      );
    }
    if (_error != null && _dashboard == null) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(Icons.cloud_off_outlined, color: lendMuted),
              const SizedBox(height: 12),
              Text(_error!),
              TextButton.icon(
                onPressed: _load,
                icon: const Icon(Icons.refresh),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }
    final portfolio = _dashboard?['portfolio'];
    final p = portfolio is Map ? portfolio : <String, dynamic>{};
    return Reveal(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_loading) ...[
            const LinearProgressIndicator(),
            const SizedBox(height: 6),
            const Text('Refreshing overview…'),
            const SizedBox(height: 8),
          ],
          if (_error != null) ...[
            const Text('Showing last loaded overview. Refresh failed.'),
            TextButton(onPressed: _load, child: const Text('Retry')),
          ],
          MetricCard(
            label: 'Outstanding balance',
            value: formatMoney(p['outstanding_balance']),
            caption: 'Regular balance • excludes penalties',
            prominent: true,
          ),
          LayoutBuilder(
            builder: (context, constraints) {
              final stacked =
                  constraints.maxWidth < 340 ||
                  MediaQuery.textScalerOf(context).scale(14) > 19;
              final width = stacked
                  ? constraints.maxWidth
                  : (constraints.maxWidth - 12) / 2;
              return Wrap(
                spacing: 12,
                children: [
                  SizedBox(
                    width: width,
                    child: MetricCard(
                      label: 'Due today',
                      value: formatMoney(p['due_today']),
                      icon: Icons.today_outlined,
                    ),
                  ),
                  SizedBox(
                    width: width,
                    child: MetricCard(
                      label: 'Overdue',
                      value: formatMoney(p['overdue_amount']),
                      icon: Icons.schedule_outlined,
                    ),
                  ),
                ],
              );
            },
          ),
          Text(
            'Updated as of ${_dashboard?['as_of'] ?? '—'}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final name = widget.user['name']?.toString().trim() ?? '';
    final admin = widget.user['role']?.toString().toLowerCase() == 'admin';
    return Scaffold(
      appBar: AppBar(
        title: const Row(
          children: [
            BrandMark(size: 32),
            SizedBox(width: 10),
            Text('LendTrack'),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Refresh overview',
            onPressed: _loading || widget.busy ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
          children: [
            const Text(
              'YOUR WORKSPACE',
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                letterSpacing: 1.5,
                color: lendTeal,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Hello, ${name.isEmpty ? 'there' : name.split(' ').first}.',
              style: Theme.of(context).textTheme.headlineLarge,
            ),
            const SizedBox(height: 6),
            const Text(
              'A clear view of your lending day.',
              style: TextStyle(color: lendMuted),
            ),
            const SizedBox(height: 24),
            _overview(),
            const SizedBox(height: 20),
            const SectionHeading(
              'Daily operations',
              subtitle: 'Manage accounts and keep collections on track.',
            ),
            _link(
              'Loans',
              'Balances, schedules and payments',
              Icons.receipt_long_outlined,
              const LoansPage(),
            ),
            _link(
              'Borrowers',
              'Profiles and contact information',
              Icons.people_alt_outlined,
              const BorrowersPage(),
            ),
            _link(
              'Collections',
              'Due today, upcoming and overdue',
              Icons.calendar_month_outlined,
              const CollectionsPage(),
            ),
            _link(
              'Analytics',
              'Portfolio and monthly collections',
              Icons.bar_chart_rounded,
              const ManagementPage(section: 'dashboard'),
            ),
            if (admin) ...[
              _link(
                'Add staff account',
                'Create a staff login',
                Icons.person_add_outlined,
                const AddStaffPage(),
              ),
              const SizedBox(height: 12),
              const SectionHeading('Administration'),
              _link(
                'Collections report',
                'Review a selected payment period',
                Icons.assessment_outlined,
                const CollectionReportPage(),
              ),
              _link(
                'Discrepancies',
                'Review shortfalls and data warnings',
                Icons.fact_check_outlined,
                const ManagementPage(section: 'discrepancies'),
              ),
              _link(
                'Activity log',
                'See recorded changes and actions',
                Icons.history_rounded,
                const ManagementPage(section: 'activity-logs'),
              ),
              _link(
                'Archive & restore',
                'Manage archived records',
                Icons.inventory_2_outlined,
                const ManagementPage(section: 'records'),
              ),
            ],
            const SizedBox(height: 20),
            const Divider(),
            Text(name, style: const TextStyle(fontWeight: FontWeight.w600)),
            Text(
              '${widget.user['email'] ?? ''}',
              style: const TextStyle(color: lendMuted),
            ),
            Text(
              admin ? 'Administrator account' : 'Staff account',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            if (widget.error != null)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(
                  widget.error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: widget.busy ? null : widget.onLogout,
              icon: const Icon(Icons.logout_rounded),
              label: Text(widget.busy ? 'Signing out…' : 'Log out'),
            ),
          ],
        ),
      ),
    );
  }
}

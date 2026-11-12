import 'dart:ui' as ui;

import 'package:flutter/material.dart';

const lendNavy = Color(0xFF132D46);
const lendTeal = Color(0xFF087F73);
const lendCanvas = Color(0xFFF4F7FA);
const lendMuted = Color(0xFF56677B);

ThemeData lendTheme() {
  final scheme = ColorScheme.fromSeed(seedColor: lendTeal).copyWith(
    primary: lendTeal,
    onPrimary: Colors.white,
    secondary: lendNavy,
    surface: Colors.white,
    onSurface: lendNavy,
    onSurfaceVariant: lendMuted,
    outline: const Color(0xFF8695A5),
    outlineVariant: const Color(0xFFDCE4EC),
    error: const Color(0xFFB42318),
  );
  final base = ThemeData(useMaterial3: true, colorScheme: scheme);
  final shape = RoundedRectangleBorder(borderRadius: BorderRadius.circular(16));
  final border = OutlineInputBorder(
    borderRadius: BorderRadius.circular(14),
    borderSide: const BorderSide(color: Color(0xFFCFD9E3)),
  );
  return base.copyWith(
    scaffoldBackgroundColor: lendCanvas,
    textTheme: base.textTheme.copyWith(
      headlineLarge: const TextStyle(
        fontSize: 32,
        fontWeight: FontWeight.w700,
        letterSpacing: -1,
        color: lendNavy,
      ),
      headlineSmall: const TextStyle(
        fontSize: 25,
        fontWeight: FontWeight.w700,
        letterSpacing: -.5,
        color: lendNavy,
      ),
      titleLarge: const TextStyle(
        fontSize: 21,
        fontWeight: FontWeight.w700,
        color: lendNavy,
      ),
      titleMedium: const TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w600,
        height: 1.35,
        color: lendNavy,
      ),
      bodyLarge: const TextStyle(fontSize: 16, height: 1.45, color: lendNavy),
      bodyMedium: const TextStyle(fontSize: 14, height: 1.45, color: lendNavy),
      bodySmall: const TextStyle(fontSize: 12, height: 1.4, color: lendMuted),
      labelLarge: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
    ),
    appBarTheme: const AppBarTheme(
      backgroundColor: lendCanvas,
      foregroundColor: lendNavy,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      titleTextStyle: TextStyle(
        fontSize: 20,
        fontWeight: FontWeight.w700,
        color: lendNavy,
      ),
    ),
    cardTheme: CardThemeData(
      color: Colors.white,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      margin: const EdgeInsets.only(bottom: 12),
      shape: shape.copyWith(side: const BorderSide(color: Color(0xFFE0E7EF))),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
      labelStyle: const TextStyle(color: lendMuted),
      border: border,
      enabledBorder: border,
      disabledBorder: border,
      focusedBorder: border.copyWith(
        borderSide: const BorderSide(color: lendTeal, width: 2),
      ),
      errorBorder: border.copyWith(borderSide: BorderSide(color: scheme.error)),
      focusedErrorBorder: border.copyWith(
        borderSide: BorderSide(color: scheme.error, width: 2),
      ),
      errorMaxLines: 3,
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size(48, 52),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(48, 48),
        shape: shape,
        side: const BorderSide(color: Color(0xFFCFD9E3)),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
    ),
    floatingActionButtonTheme: const FloatingActionButtonThemeData(
      backgroundColor: lendTeal,
      foregroundColor: Colors.white,
      elevation: 3,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.all(Radius.circular(18)),
      ),
    ),
    dialogTheme: DialogThemeData(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.transparent,
      shape: shape,
    ),
    snackBarTheme: SnackBarThemeData(
      backgroundColor: lendNavy,
      behavior: SnackBarBehavior.floating,
      shape: shape,
      contentTextStyle: const TextStyle(color: Colors.white, fontSize: 14),
    ),
    dividerTheme: const DividerThemeData(
      color: Color(0xFFE0E7EF),
      space: 24,
      thickness: 1,
    ),
    listTileTheme: const ListTileThemeData(
      contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
    ),
  );
}

String formatMoney(dynamic value) {
  final amount = num.tryParse(value?.toString() ?? '');
  if (amount == null || !amount.isFinite) {
    return '—';
  }
  final parts = amount.abs().toStringAsFixed(2).split('.');
  final whole = parts.first.replaceAllMapped(
    RegExp(r'\B(?=(\d{3})+(?!\d))'),
    (_) => ',',
  );
  return '${amount < 0 ? '-' : ''}₱$whole.${parts.last}';
}

class LendRoute<T> extends PageRouteBuilder<T> {
  LendRoute({required WidgetBuilder builder})
    : super(
        transitionDuration: const Duration(milliseconds: 220),
        reverseTransitionDuration: const Duration(milliseconds: 180),
        pageBuilder: (context, animation, secondaryAnimation) =>
            builder(context),
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          if (MediaQuery.disableAnimationsOf(context)) {
            return child;
          }
          final curve = animation.drive(CurveTween(curve: Curves.easeOutCubic));
          return FadeTransition(
            opacity: curve,
            child: SlideTransition(
              position: curve.drive(
                Tween(begin: const Offset(0, .025), end: Offset.zero),
              ),
              child: child,
            ),
          );
        },
      );
}

/// A single entrance, never a timer or a simulated loading delay.
class Reveal extends StatelessWidget {
  final Widget child;
  const Reveal({super.key, required this.child});
  @override
  Widget build(BuildContext context) {
    if (MediaQuery.disableAnimationsOf(context)) {
      return child;
    }
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeOutCubic,
      child: child,
      builder: (_, value, content) => Opacity(
        opacity: value,
        child: Transform.translate(
          offset: Offset(0, 8 * (1 - value)),
          child: content,
        ),
      ),
    );
  }
}

class BrandMark extends StatelessWidget {
  final double size;
  const BrandMark({super.key, this.size = 48});
  @override
  Widget build(BuildContext context) => ExcludeSemantics(
    child: Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: lendTeal,
        borderRadius: BorderRadius.circular(size * .3),
      ),
      child: Icon(
        Icons.account_balance_wallet_rounded,
        color: Colors.white,
        size: size * .52,
      ),
    ),
  );
}

class StatusPill extends StatelessWidget {
  final String status;
  const StatusPill(this.status, {super.key});
  @override
  Widget build(BuildContext context) {
    final text = status.trim().toLowerCase();
    final color = switch (text) {
      'paid' || 'active' => const Color(0xFF086C50),
      'overdue' => const Color(0xFFA52D22),
      'pending' || 'due today' || 'due_today' => const Color(0xFF8A5208),
      _ => const Color(0xFF45586E),
    };
    final label = text.isEmpty
        ? 'Unknown'
        : '${text[0].toUpperCase()}${text.substring(1)}'.replaceAll('_', ' ');
    return Semantics(
      label: 'Status: $label',
      child: ExcludeSemantics(
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
          decoration: BoxDecoration(
            color: color.withValues(alpha: .09),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }
}

class MetricCard extends StatelessWidget {
  final String label;
  final String value;
  final String? caption;
  final IconData icon;
  final bool prominent;
  const MetricCard({
    super.key,
    required this.label,
    required this.value,
    this.caption,
    this.icon = Icons.account_balance_wallet_outlined,
    this.prominent = false,
  });
  @override
  Widget build(BuildContext context) {
    final color = prominent ? Colors.white : lendNavy;
    return Card(
      color: prominent ? lendNavy : Colors.white,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              icon,
              color: prominent ? const Color(0xFF85DCD0) : lendTeal,
              size: 22,
            ),
            const SizedBox(height: 14),
            Text(
              label,
              style: TextStyle(
                color: prominent ? const Color(0xFFD1E0ED) : lendMuted,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              value,
              style: TextStyle(
                color: color,
                fontSize: prominent ? 30 : 23,
                fontWeight: FontWeight.w700,
                letterSpacing: -.5,
                fontFeatures: const [ui.FontFeature.tabularFigures()],
              ),
            ),
            if (caption != null) ...[
              const SizedBox(height: 8),
              Text(
                caption!,
                style: TextStyle(
                  fontSize: 12,
                  color: prominent ? const Color(0xFFD1E0ED) : lendMuted,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class SectionHeading extends StatelessWidget {
  final String title;
  final String? subtitle;
  const SectionHeading(this.title, {super.key, this.subtitle});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 8, bottom: 16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: Theme.of(context).textTheme.titleLarge),
        if (subtitle != null) ...[
          const SizedBox(height: 4),
          Text(subtitle!, style: const TextStyle(color: lendMuted)),
        ],
      ],
    ),
  );
}

class DetailField extends StatelessWidget {
  final String label;
  final dynamic value;
  const DetailField(this.label, this.value, {super.key});
  @override
  Widget build(BuildContext context) {
    final raw = value?.toString().trim();
    final text = raw == null || raw.isEmpty ? '—' : raw;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final compact =
              constraints.maxWidth < 300 ||
              MediaQuery.textScalerOf(context).scale(14) > 20;
          if (compact) {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(color: lendMuted, fontSize: 12),
                ),
                const SizedBox(height: 3),
                Text(text),
              ],
            );
          }
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                flex: 4,
                child: Text(label, style: const TextStyle(color: lendMuted)),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 5,
                child: Text(
                  text,
                  textAlign: TextAlign.end,
                  style: const TextStyle(fontWeight: FontWeight.w500),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class AppLoading extends StatelessWidget {
  final String label;
  const AppLoading({super.key, this.label = 'Loading records…'});
  @override
  Widget build(BuildContext context) => Center(
    child: Semantics(
      liveRegion: true,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (MediaQuery.disableAnimationsOf(context))
            const Icon(Icons.hourglass_empty_rounded, color: lendTeal, size: 30)
          else
            const SizedBox(
              width: 28,
              height: 28,
              child: CircularProgressIndicator(strokeWidth: 3),
            ),
          const SizedBox(height: 16),
          Text(label, style: const TextStyle(color: lendMuted)),
        ],
      ),
    ),
  );
}

class RepaymentProgress extends StatelessWidget {
  final dynamic paid;
  final dynamic total;
  const RepaymentProgress({super.key, required this.paid, required this.total});
  @override
  Widget build(BuildContext context) {
    final paidValue = num.tryParse('$paid');
    final totalValue = num.tryParse('$total');
    if (paidValue == null ||
        totalValue == null ||
        totalValue <= 0 ||
        !paidValue.isFinite ||
        !totalValue.isFinite) {
      return const SizedBox.shrink();
    }
    final progress = (paidValue / totalValue).clamp(0.0, 1.0).toDouble();
    return Semantics(
      label: 'Regular payments: ${formatMoney(paid)} of ${formatMoney(total)}',
      child: ExcludeSemantics(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(
                value: progress,
                minHeight: 6,
                backgroundColor: const Color(0xFFE7EEF2),
                color: lendTeal,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              '${formatMoney(paid)} paid of ${formatMoney(total)}',
              style: const TextStyle(color: lendMuted, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}

class EmptyRecords extends StatelessWidget {
  final String message;
  const EmptyRecords(this.message, {super.key});
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            padding: const EdgeInsets.all(18),
            decoration: const BoxDecoration(
              color: Color(0xFFE8EFF4),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.search_off_rounded,
              color: lendMuted,
              size: 30,
            ),
          ),
          const SizedBox(height: 16),
          Text(
            message,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 6),
          const Text(
            'Try a different search or filter.',
            textAlign: TextAlign.center,
            style: TextStyle(color: lendMuted),
          ),
        ],
      ),
    ),
  );
}

class LookupProgress extends StatelessWidget {
  const LookupProgress({super.key});
  @override
  Widget build(BuildContext context) => MediaQuery.disableAnimationsOf(context)
      ? const Padding(
          padding: EdgeInsets.all(8),
          child: Text('Loading…', style: TextStyle(color: lendMuted)),
        )
      : const LinearProgressIndicator(minHeight: 2);
}

class MonthlyCollections extends StatelessWidget {
  final Map<String, dynamic> values;
  const MonthlyCollections({super.key, required this.values});
  @override
  Widget build(BuildContext context) {
    final amounts = values.values.map((v) => num.tryParse('$v') ?? 0).toList();
    final maximum = amounts.fold<num>(0, (a, b) => b > a ? b : a);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const SectionHeading(
              'Monthly collections',
              subtitle: 'Regular payments and penalties received',
            ),
            if (values.isEmpty) const Text('No monthly data available.'),
            for (final e in values.entries)
              Padding(
                padding: const EdgeInsets.only(bottom: 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Wrap(
                      alignment: WrapAlignment.spaceBetween,
                      spacing: 8,
                      runSpacing: 4,
                      children: [
                        Text(e.key, style: const TextStyle(color: lendMuted)),
                        Text(
                          formatMoney(e.value),
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    ExcludeSemantics(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(4),
                        child: LinearProgressIndicator(
                          minHeight: 6,
                          color: lendTeal,
                          backgroundColor: const Color(0xFFE7EEF2),
                          value: maximum <= 0
                              ? 0
                              : ((num.tryParse('${e.value}') ?? 0) / maximum)
                                    .clamp(0.0, 1.0)
                                    .toDouble(),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

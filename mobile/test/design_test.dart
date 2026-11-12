import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lendtrack_mobile/design/lend_design.dart';

void main() {
  test('Money uses exact decimals, separators and an honest missing value', () {
    expect(formatMoney('1030.00'), '₱1,030.00');
    expect(formatMoney('335491.64'), '₱335,491.64');
    expect(formatMoney('-25.5'), '-₱25.50');
    expect(formatMoney(0), '₱0.00');
    expect(formatMoney(null), '—');
    expect(formatMoney('NaN'), '—');
  });

  testWidgets(
    'Reduced motion displays content immediately and keeps loading static',
    (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: lendTheme(),
          home: const MediaQuery(
            data: MediaQueryData(disableAnimations: true),
            child: Scaffold(
              body: Column(
                children: [
                  Reveal(child: Text('Content ready')),
                  Expanded(child: AppLoading()),
                ],
              ),
            ),
          ),
        ),
      );
      expect(find.text('Content ready'), findsOneWidget);
      expect(find.byType(TweenAnimationBuilder<double>), findsNothing);
      expect(find.byType(CircularProgressIndicator), findsNothing);
      expect(find.byIcon(Icons.hourglass_empty_rounded), findsOneWidget);
    },
  );

  testWidgets('Cards and fields fit a narrow screen with large text', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      MaterialApp(
        theme: lendTheme(),
        home: MediaQuery(
          data: const MediaQueryData(
            size: Size(320, 900),
            textScaler: TextScaler.linear(2),
          ),
          child: Scaffold(
            body: ListView(
              padding: const EdgeInsets.all(20),
              children: const [
                MetricCard(
                  label: 'Outstanding regular balance',
                  value: '₱999,999.99',
                  prominent: true,
                ),
                DetailField(
                  'Address',
                  'A long street address, Quezon City, National Capital Region',
                ),
                StatusPill('overdue'),
                RepaymentProgress(paid: '250.00', total: '1030.00'),
                MonthlyCollections(
                  values: {'2026-10': '1030.00', '2026-11': '0.00'},
                ),
              ],
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('₱999,999.99'), findsOneWidget);
  });

  testWidgets('Page navigation animates and returns to the original screen', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: lendTheme(),
        home: Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              onPressed: () => Navigator.of(context).push<void>(
                LendRoute(
                  builder: (_) => Scaffold(
                    appBar: AppBar(title: const Text('Details')),
                    body: const Text('Loaded record'),
                  ),
                ),
              ),
              child: const Text('Open'),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Open'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 240));
    await tester.pumpAndSettle();
    expect(find.text('Loaded record'), findsOneWidget);
    await tester.pageBack();
    await tester.pumpAndSettle();
    expect(find.text('Open'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

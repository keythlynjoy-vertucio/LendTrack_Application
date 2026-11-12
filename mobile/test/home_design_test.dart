import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:lendtrack_mobile/home_page.dart';
import 'package:lendtrack_mobile/design/lend_design.dart';

void main() {
  setUp(
    () =>
        FlutterSecureStorage.setMockInitialValues({'auth_token': 'test-token'}),
  );

  testWidgets(
    'Home displays server amounts and retains staff-only navigation',
    (tester) async {
      await http.runWithClient(
        () async {
          await tester.pumpWidget(
            MaterialApp(
              theme: lendTheme(),
              home: HomePage(
                user: const {
                  'id': 1,
                  'name': 'Bea Test',
                  'email': 'bea@example.test',
                  'role': 'staff',
                },
                busy: false,
                onLogout: () {},
              ),
            ),
          );
          await tester.pumpAndSettle();
          expect(find.text('₱1,030.00'), findsOneWidget);
          expect(find.text('₱250.00'), findsOneWidget);
          expect(find.text('Hello, Bea.'), findsOneWidget);
          await tester.drag(
            find.byType(ListView).first,
            const Offset(0, -1600),
          );
          await tester.pumpAndSettle();
          expect(find.text('Staff account'), findsOneWidget);
          expect(find.text('Administration'), findsNothing);
          expect(find.text('Archive & restore'), findsNothing);
          expect(tester.takeException(), isNull);
        },
        () => MockClient(
          (request) async => http.Response(
            jsonEncode({
              'as_of': '2026-11-11',
              'portfolio': {
                'outstanding_balance': '1030.00',
                'due_today': '250.00',
                'overdue_amount': '0.00',
              },
            }),
            200,
          ),
        ),
      );
    },
  );

  testWidgets(
    'Failed overview offers retry without displaying invented zero balances',
    (tester) async {
      await http.runWithClient(
        () async {
          await tester.pumpWidget(
            MaterialApp(
              theme: lendTheme(),
              home: HomePage(
                user: const {'id': 1, 'name': 'Test', 'role': 'staff'},
                busy: false,
                onLogout: () {},
              ),
            ),
          );
          await tester.pumpAndSettle();
          expect(find.text('Retry'), findsOneWidget);
          expect(find.text('₱0.00'), findsNothing);
          expect(find.text('Outstanding balance'), findsNothing);
          expect(tester.takeException(), isNull);
        },
        () => MockClient(
          (_) async => http.Response('{"message":"Unavailable"}', 503),
        ),
      );
    },
  );
}

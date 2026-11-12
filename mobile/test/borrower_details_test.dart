import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:lendtrack_mobile/borrowers_page.dart';
import 'package:lendtrack_mobile/design/lend_design.dart';

const borrower = <String, dynamic>{
  'id': 16,
  'first_name': 'Mobile',
  'last_name': 'Test',
  'status': 'active',
  'loans_count': 0,
  'phone': null,
  'email': null,
  'address': 'House 1, Balingayan, City of Calapan, Oriental Mindoro',
  'notes': 'Test borrower',
};
http.Response listResponse() => http.Response(
  jsonEncode({
    'data': [borrower],
    'current_page': 1,
    'last_page': 1,
    'total': 1,
  }),
  200,
);

void main() {
  setUp(
    () =>
        FlutterSecureStorage.setMockInitialValues({'auth_token': 'test-token'}),
  );
  testWidgets(
    'Borrower dialog opens, scrolls, closes, and reopens on a narrow screen',
    (tester) async {
      tester.view.physicalSize = const Size(360, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await http.runWithClient(
        () async {
          await tester.pumpWidget(
            MaterialApp(theme: lendTheme(), home: const BorrowersPage()),
          );
          await tester.pumpAndSettle();
          await tester.tap(find.text('Mobile Test'));
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
          expect(find.text('Borrower details'), findsOneWidget);
          expect(find.text('Close'), findsOneWidget);
          expect(find.text('Edit'), findsOneWidget);
          expect(find.byType(LookupProgress), findsNothing);
          await tester.tap(find.text('Close'));
          await tester.pumpAndSettle();
          await tester.tap(find.text('Mobile Test'));
          await tester.pumpAndSettle();
          expect(find.text('Borrower details'), findsOneWidget);
          expect(tester.takeException(), isNull);
        },
        () => MockClient(
          (request) async => request.url.path.endsWith('/16')
              ? http.Response(jsonEncode(borrower), 200)
              : listResponse(),
        ),
      );
    },
  );

  testWidgets(
    'Loading indicator ends after detail request fails and user can retry',
    (tester) async {
      final response = Completer<http.Response>();
      await http.runWithClient(
        () async {
          await tester.pumpWidget(
            MaterialApp(theme: lendTheme(), home: const BorrowersPage()),
          );
          await tester.pumpAndSettle();
          await tester.tap(find.text('Mobile Test'));
          await tester.pump();
          expect(find.byType(LookupProgress), findsOneWidget);
          response.complete(http.Response('{"message":"Unavailable"}', 503));
          await tester.pumpAndSettle();
          expect(find.byType(LookupProgress), findsNothing);
          expect(find.byType(AlertDialog), findsNothing);
          expect(find.text('Request failed (503).'), findsOneWidget);
          expect(tester.takeException(), isNull);
        },
        () => MockClient(
          (request) => request.url.path.endsWith('/16')
              ? response.future
              : Future.value(listResponse()),
        ),
      );
    },
  );
}

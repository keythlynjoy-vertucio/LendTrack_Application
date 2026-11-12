import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:lendtrack_mobile/main.dart';
import 'package:lendtrack_mobile/services/session.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    FlutterSecureStorage.setMockInitialValues({});
    AppSession.onExpired = null;
  });

  testWidgets('A clean install displays the sign-in form', (tester) async {
    await tester.pumpWidget(const MyApp());
    await tester.pumpAndSettle();
    expect(find.text('Sign in'), findsOneWidget);
    expect(find.byType(TextFormField), findsNWidgets(2));
    await tester.tap(find.text('Sign in'));
    await tester.pump();
    expect(find.text('Enter your email address.'), findsOneWidget);
    expect(find.text('Enter your password.'), findsOneWidget);
  });

  test('Session expiration preserves a pending payment draft', () async {
    FlutterSecureStorage.setMockInitialValues({
      'auth_token': 'current',
      'pending_payment_v1_user_1_loan_26': '{"amount":"250.00"}',
    });
    var expired = 0;
    AppSession.onExpired = () => expired++;
    await AppSession.expire('Bearer current');
    const storage = FlutterSecureStorage();
    expect(await storage.read(key: 'auth_token'), isNull);
    expect(
      await storage.read(key: 'pending_payment_v1_user_1_loan_26'),
      isNotNull,
    );
    expect(expired, 1);
  });

  test('A late response cannot expire a newer session', () async {
    FlutterSecureStorage.setMockInitialValues({'auth_token': 'new-token'});
    var expired = false;
    AppSession.onExpired = () => expired = true;
    await AppSession.expire('Bearer old-token');
    expect(
      await const FlutterSecureStorage().read(key: 'auth_token'),
      'new-token',
    );
    expect(expired, isFalse);
  });
}

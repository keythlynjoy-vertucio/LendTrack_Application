import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as client;
import 'package:http/testing.dart';
import 'package:lendtrack_mobile/services/session.dart';
import 'package:lendtrack_mobile/services/session_http.dart' as http;

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const storage = FlutterSecureStorage();
  setUp(() {
    FlutterSecureStorage.setMockInitialValues({'auth_token': 'saved'});
    AppSession.onExpired = null;
  });
  test('An API 401 clears the matching session', () async {
    var expired = false;
    AppSession.onExpired = () => expired = true;
    await client.runWithClient(
      () => http.get(
        Uri.parse('$apiBaseUrl/loans'),
        headers: {'Authorization': 'Bearer saved'},
      ),
      () => MockClient(
        (_) async => client.Response('{"message":"Unauthenticated."}', 401),
      ),
    );
    expect(expired, isTrue);
    expect(await storage.read(key: 'auth_token'), isNull);
  });
  test('A server error retains the session for retry', () async {
    await client.runWithClient(
      () => http.get(
        Uri.parse('$apiBaseUrl/me'),
        headers: {'Authorization': 'Bearer saved'},
      ),
      () => MockClient((_) async => client.Response('Unavailable', 503)),
    );
    expect(await storage.read(key: 'auth_token'), 'saved');
  });
  test('A network failure retains the session and pending draft', () async {
    await storage.write(
      key: 'pending_payment_v1_user_1_loan_1',
      value: 'draft',
    );
    await expectLater(
      client.runWithClient(
        () => http.get(
          Uri.parse('$apiBaseUrl/me'),
          headers: {'Authorization': 'Bearer saved'},
        ),
        () => MockClient((_) async => throw client.ClientException('Offline')),
      ),
      throwsA(isA<client.ClientException>()),
    );
    expect(await storage.read(key: 'auth_token'), 'saved');
    expect(
      await storage.read(key: 'pending_payment_v1_user_1_loan_1'),
      'draft',
    );
  });
}

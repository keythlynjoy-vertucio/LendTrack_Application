import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import 'package:lendtrack_mobile/services/server_ready.dart';

void main() {
  test('Retries a sleeping health endpoint using GET only', () async {
    var calls = 0;
    final client = MockClient((request) async {
      expect(request.method, 'GET');
      expect(request.url.path, '/up');
      calls++;
      return http.Response('', calls == 1 ? 503 : 200);
    });
    await waitForServer(client: client, retryDelay: Duration.zero);
    expect(calls, 2);
    client.close();
  });
  test('Stops after the time budget without posting credentials', () async {
    final client = MockClient((_) async => http.Response('', 503));
    await expectLater(
      waitForServer(
        client: client,
        budget: const Duration(milliseconds: 10),
        retryDelay: const Duration(milliseconds: 2),
      ),
      throwsA(isA<TimeoutException>()),
    );
    client.close();
  });
  test('Does not repeatedly retry a missing health route', () async {
    var calls = 0;
    final client = MockClient((_) async {
      calls++;
      return http.Response('', 404);
    });
    await expectLater(
      waitForServer(client: client),
      throwsA(isA<StateError>()),
    );
    expect(calls, 1);
    client.close();
  });
}

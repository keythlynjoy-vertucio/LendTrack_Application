import 'dart:async';

import 'package:http/http.dart' as http;

import 'session.dart';

/// Retry only the read-only health check. Never replay login or payment POSTs.
Future<void> waitForServer({
  http.Client? client,
  void Function(String message)? onStatus,
  Duration budget = const Duration(minutes: 3),
  Duration attemptTimeout = const Duration(seconds: 20),
  Duration retryDelay = const Duration(seconds: 2),
}) async {
  onStatus?.call('Connecting to server…');
  final owned = client == null;
  final transport = client ?? http.Client();
  final clock = Stopwatch()..start();
  final uri = Uri.parse(apiBaseUrl).resolve('/up');
  try {
    while (clock.elapsed < budget) {
      final left = budget - clock.elapsed;
      try {
        final response = await transport
            .get(uri)
            .timeout(left < attemptTimeout ? left : attemptTimeout);
        if (response.statusCode == 200) {
          return;
        }
        if (response.statusCode >= 400 && response.statusCode < 500) {
          throw StateError(
            'Server health address is unavailable (${response.statusCode}).',
          );
        }
      } on TimeoutException {
        // A sleeping Render service can take longer than one request to wake.
      } on http.ClientException {
        // Retry transient connection failures within the same bounded budget.
      }
      onStatus?.call('Waiting for server…');
      final remaining = budget - clock.elapsed;
      if (remaining > Duration.zero) {
        await Future<void>.delayed(
          remaining < retryDelay ? remaining : retryDelay,
        );
      }
    }
    throw TimeoutException(
      'Server is taking too long to respond. Check your internet and tap Retry.',
    );
  } finally {
    clock.stop();
    if (owned) {
      transport.close();
    }
  }
}

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

const apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://10.0.2.2:8000/api',
);

class AppSession {
  static final navigatorKey = GlobalKey<NavigatorState>();
  static VoidCallback? onExpired;
  static bool _expiring = false;

  static Future<void> expire(String? authorization) async {
    if (_expiring || authorization == null) return;
    _expiring = true;
    try {
      const storage = FlutterSecureStorage();
      final current = await storage.read(key: 'auth_token');
      // Ignore late responses belonging to an older sign-in.
      if (current == null || authorization != 'Bearer $current') return;
      await storage.delete(key: 'auth_token');
      onExpired?.call();
      navigatorKey.currentState?.popUntil((route) => route.isFirst);
    } finally {
      _expiring = false;
    }
  }
}

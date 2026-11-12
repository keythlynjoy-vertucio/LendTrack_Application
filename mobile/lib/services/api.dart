import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'session.dart';
import 'session_http.dart' as http;

class Api {
  static Future<Map<String, dynamic>> request(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? body,
  }) async {
    final token = await const FlutterSecureStorage().read(key: 'auth_token');
    if (token == null) throw Exception('Please sign in again.');
    final headers = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer $token',
    };
    final uri = Uri.parse('$apiBaseUrl/$path');
    final response =
        await (method == 'POST'
                ? http.post(uri, headers: headers, body: jsonEncode(body ?? {}))
                : method == 'DELETE'
                ? http.delete(uri, headers: headers)
                : http.get(uri, headers: headers))
            .timeout(const Duration(seconds: 30));
    final decoded = jsonDecode(response.body);
    if (decoded is! Map<String, dynamic>) {
      throw const FormatException('Unexpected server response.');
    }
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception(
        decoded['message'] ?? 'Request failed (${response.statusCode}).',
      );
    }
    return decoded;
  }
}

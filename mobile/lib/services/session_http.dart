import 'dart:convert';

import 'package:http/http.dart' as client;

import 'session.dart';

export 'package:http/http.dart' show Response, ClientException;

Future<client.Response> _check(
  Future<client.Response> request,
  Map<String, String>? headers,
) async {
  final response = await request;
  if (response.statusCode == 401) {
    await AppSession.expire(headers?['Authorization']);
  }
  return response;
}

Future<client.Response> get(Uri url, {Map<String, String>? headers}) =>
    _check(client.get(url, headers: headers), headers);
Future<client.Response> post(
  Uri url, {
  Map<String, String>? headers,
  Object? body,
  Encoding? encoding,
}) => _check(
  client.post(url, headers: headers, body: body, encoding: encoding),
  headers,
);
Future<client.Response> patch(
  Uri url, {
  Map<String, String>? headers,
  Object? body,
  Encoding? encoding,
}) => _check(
  client.patch(url, headers: headers, body: body, encoding: encoding),
  headers,
);
Future<client.Response> delete(Uri url, {Map<String, String>? headers}) =>
    _check(client.delete(url, headers: headers), headers);

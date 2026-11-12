import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'services/session_http.dart' as http;
import 'services/session.dart';
import 'design/lend_design.dart';
import 'home_page.dart';
import 'services/server_ready.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      navigatorKey: AppSession.navigatorKey,
      title: 'LendTrack Mobile',
      debugShowCheckedModeBanner: false,
      theme: lendTheme(),
      home: const LoginPage(),
    );
  }
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  static const baseUrl = apiBaseUrl;

  final _storage = const FlutterSecureStorage();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  Map<String, dynamic>? _user;

  bool _busy = false;
  bool _hidePassword = true;
  bool _restoring = true;

  String? _error;
  String? _restoreError;
  String _connectionStatus = 'Connecting to server…';

  void _showConnectionStatus(String message) {
    if (mounted) {
      setState(() => _connectionStatus = message);
    }
  }

  @override
  void initState() {
    super.initState();
    AppSession.onExpired = _sessionExpired;
    _restoreSession();
  }

  void _sessionExpired() {
    if (!mounted) {
      return;
    }
    setState(() {
      _user = null;
      _restoring = false;
      _restoreError = null;
      _error = 'Session expired. Please sign in again.';
    });
  }

  Future<void> _restoreSession() async {
    if (_busy) {
      return;
    }

    setState(() {
      _restoring = true;
      _restoreError = null;
      _error = null;
    });

    try {
      final token = await _storage.read(key: 'auth_token');

      if (token == null || token.isEmpty) {
        if (mounted) {
          setState(() => _user = null);
        }
        return;
      }

      await waitForServer(onStatus: _showConnectionStatus);
      if (!mounted) {
        return;
      }
      _showConnectionStatus('Checking saved sign-in…');
      final response = await http
          .get(
            Uri.parse('$baseUrl/me'),
            headers: {
              'Accept': 'application/json',
              'Authorization': 'Bearer $token',
            },
          )
          .timeout(const Duration(seconds: 20));

      if (response.statusCode == 401) {
        await _storage.delete(key: 'auth_token');

        if (!mounted) {
          return;
        }

        setState(() {
          _user = null;
          _error = 'Session expired. Please sign in again.';
        });
        return;
      }

      if (response.statusCode == 403) {
        throw Exception('Your account does not have access.');
      }

      if (response.statusCode != 200) {
        throw Exception('Unable to check session (${response.statusCode}).');
      }

      final decoded = jsonDecode(response.body);

      if (decoded is! Map<String, dynamic>) {
        throw const FormatException('Invalid session response.');
      }

      final user = decoded['user'];

      if (user is! Map ||
          user['id'] == null ||
          user['name'] is! String ||
          user['email'] is! String) {
        throw const FormatException('Invalid user response.');
      }

      if (!mounted) {
        return;
      }

      setState(() {
        _user = Map<String, dynamic>.from(user);
      });
    } on TimeoutException {
      if (mounted) {
        setState(() {
          _restoreError =
              'Session check timed out. Your saved sign-in '
              'has been kept. Check Laravel and try again.';
        });
      }
    } on http.ClientException {
      if (mounted) {
        setState(() {
          _restoreError =
              'Cannot reach the server. Your saved sign-in '
              'has been kept. Check Laravel and your connection.';
        });
      }
    } on FormatException {
      if (mounted) {
        setState(() {
          _restoreError =
              'Unexpected server response. Your saved sign-in '
              'has been kept. Check the API and try again.';
        });
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _restoreError = error.toString().replaceFirst('Exception: ', '');
        });
      }
    } finally {
      if (mounted) {
        setState(() => _restoring = false);
      }
    }
  }

  @override
  void dispose() {
    AppSession.onExpired = null;
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (_busy || _restoring || _restoreError != null) {
      return;
    }
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    FocusScope.of(context).unfocus();

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await waitForServer(onStatus: _showConnectionStatus);
      if (!mounted) {
        return;
      }
      _showConnectionStatus('Signing in…');
      final response = await http
          .post(
            Uri.parse('$baseUrl/login'),
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
            body: jsonEncode({
              'email': _email.text.trim(),
              'password': _password.text,
              'device_name': 'LendTrack Android',
            }),
          )
          .timeout(const Duration(seconds: 20));

      if (response.statusCode == 422) {
        throw Exception('Incorrect email or password.');
      }

      if (response.statusCode == 429) {
        throw Exception('Too many attempts. Try again after a minute.');
      }

      if (response.statusCode == 403) {
        throw Exception('Your account does not have access.');
      }

      if (response.statusCode != 200) {
        throw Exception('Server error (${response.statusCode}).');
      }

      final decoded = jsonDecode(response.body);

      if (decoded is! Map<String, dynamic>) {
        throw const FormatException('Invalid login response.');
      }

      final token = decoded['token'];
      final user = decoded['user'];

      if (token is! String || token.isEmpty || user is! Map) {
        throw Exception('Unexpected login response from the server.');
      }

      await _storage.write(key: 'auth_token', value: token);
      _password.clear();

      if (!mounted) {
        return;
      }

      setState(() {
        _user = Map<String, dynamic>.from(user);
      });
    } on TimeoutException {
      if (mounted) {
        setState(
          () => _error =
              'Server response timed out. Check your connection and try again.',
        );
      }
    } on http.ClientException {
      if (mounted) {
        setState(() {
          _error =
              'Cannot connect. Check your internet connection and try again.';
        });
      }
    } on FormatException {
      if (mounted) {
        setState(() => _error = 'Unexpected server response.');
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _error = error.toString().replaceFirst('Exception: ', '');
        });
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _logout() async {
    if (_busy) {
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final token = await _storage.read(key: 'auth_token');

      if (token != null && token.isNotEmpty) {
        final response = await http
            .post(
              Uri.parse('$baseUrl/logout'),
              headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer $token',
              },
            )
            .timeout(const Duration(seconds: 20));

        if (response.statusCode != 200 && response.statusCode != 401) {
          throw Exception('Logout failed.');
        }
      }

      await _storage.delete(key: 'auth_token');

      if (!mounted) {
        return;
      }
      _password.clear();
      setState(() {
        _user = null;
        _error = null;
        _restoreError = null;
      });
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = 'Could not complete logout. Check connection and retry.';
        });
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Widget _errorText() {
    if (_error == null) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Text(_error!, style: const TextStyle(color: Colors.red)),
    );
  }

  Widget _sessionStatus() {
    if (_restoring) {
      return SizedBox(height: 160, child: AppLoading(label: _connectionStatus));
    }

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Icon(Icons.cloud_off_outlined, size: 48),
        const SizedBox(height: 16),
        Text(
          _restoreError ?? 'Unable to check your saved sign-in.',
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 20),
        FilledButton.icon(
          onPressed: _restoreSession,
          icon: const Icon(Icons.refresh),
          label: const Text('Retry'),
        ),
      ],
    );
  }

  Widget _loginForm() {
    return Form(
      key: _formKey,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Align(
            alignment: Alignment.centerLeft,
            child: BrandMark(size: 60),
          ),
          const SizedBox(height: 24),
          const Text(
            'LENDTRACK',
            style: TextStyle(
              color: lendTeal,
              fontWeight: FontWeight.w700,
              letterSpacing: 2,
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Welcome back.',
            style: Theme.of(context).textTheme.headlineLarge,
          ),
          const SizedBox(height: 8),
          const Text(
            'Sign in to manage your borrowers, loans and collections.',
            style: TextStyle(color: lendMuted),
          ),
          const SizedBox(height: 32),
          TextFormField(
            controller: _email,
            enabled: !_busy,
            keyboardType: TextInputType.emailAddress,
            autocorrect: false,
            textCapitalization: TextCapitalization.none,
            decoration: const InputDecoration(
              labelText: 'Email',
              prefixIcon: Icon(Icons.alternate_email_rounded),
              border: OutlineInputBorder(),
            ),
            validator: (value) => value == null || !value.contains('@')
                ? 'Enter your email address.'
                : null,
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _password,
            enabled: !_busy,
            obscureText: _hidePassword,
            onFieldSubmitted: (_) => _login(),
            decoration: InputDecoration(
              labelText: 'Password',
              prefixIcon: const Icon(Icons.lock_outline_rounded),
              border: const OutlineInputBorder(),
              suffixIcon: IconButton(
                onPressed: _busy
                    ? null
                    : () {
                        setState(() => _hidePassword = !_hidePassword);
                      },
                icon: Icon(
                  _hidePassword ? Icons.visibility : Icons.visibility_off,
                ),
              ),
            ),
            validator: (value) =>
                value == null || value.isEmpty ? 'Enter your password.' : null,
          ),
          if (_busy)
            Padding(
              padding: const EdgeInsets.only(top: 16),
              child: Text(_connectionStatus),
            ),
          _errorText(),
          const SizedBox(height: 24),
          FilledButton(
            onPressed: _busy ? null : _login,
            child: Text(_busy ? 'Signing in…' : 'Sign in'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = _user;
    if (!_restoring && _restoreError == null && user != null) {
      return HomePage(
        user: user,
        onLogout: _logout,
        busy: _busy,
        error: _error,
      );
    }
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Reveal(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: _restoring || _restoreError != null
                            ? _sessionStatus()
                            : _loginForm(),
                      ),
                    ),
                    const SizedBox(height: 16),
                    const Text(
                      'LendTrack Mobile • Staff & administration',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: lendMuted, fontSize: 12),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

import 'dart:async';

import 'package:flutter/material.dart';

import 'services/api.dart';
import 'services/server_ready.dart';

class AddStaffPage extends StatefulWidget {
  const AddStaffPage({super.key});
  @override
  State<AddStaffPage> createState() => _AddStaffPageState();
}

class _AddStaffPageState extends State<AddStaffPage> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    for (final controller in [_name, _email, _password, _confirm]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (_saving || !(_form.currentState?.validate() ?? false)) {
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() {
      _saving = true;
      _error = null;
    });
    var submitted = false;
    try {
      await waitForServer();
      if (!mounted) {
        return;
      }
      submitted = true;
      await Api.request(
        'staff',
        method: 'POST',
        body: {
          'name': _name.text.trim(),
          'email': _email.text.trim().toLowerCase(),
          'password': _password.text,
          'password_confirmation': _confirm.text,
        },
      );
      if (!mounted) {
        return;
      }
      _password.clear();
      _confirm.clear();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Staff account created. They can now sign in.'),
        ),
      );
      Navigator.of(context).pop();
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(
        () => _error = error is TimeoutException && submitted
            ? 'No confirmation received. The account may have been created. Try signing in with that email before submitting again.'
            : error.toString().replaceFirst('Exception: ', ''),
      );
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Add staff account')),
    body: Form(
      key: _form,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          TextFormField(
            controller: _name,
            enabled: !_saving,
            decoration: const InputDecoration(
              floatingLabelBehavior: FloatingLabelBehavior.always,
              labelText: 'Full name',
            ),
            validator: (v) =>
                v == null || v.trim().isEmpty ? 'Enter a name.' : null,
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _email,
            enabled: !_saving,
            keyboardType: TextInputType.emailAddress,
            autocorrect: false,
            decoration: const InputDecoration(
              floatingLabelBehavior: FloatingLabelBehavior.always,
              labelText: 'Email address',
            ),
            validator: (v) => v == null || !v.contains('@')
                ? 'Enter an email address.'
                : null,
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _password,
            enabled: !_saving,
            obscureText: true,
            decoration: const InputDecoration(
              floatingLabelBehavior: FloatingLabelBehavior.always,
              labelText: 'Password',
            ),
            validator: (v) => v == null || v.length < 12 || v.length > 72
                ? 'Use 12–72 characters.'
                : null,
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _confirm,
            enabled: !_saving,
            obscureText: true,
            decoration: const InputDecoration(
              floatingLabelBehavior: FloatingLabelBehavior.always,
              labelText: 'Confirm password',
            ),
            validator: (v) =>
                v != _password.text ? 'Passwords must match.' : null,
          ),
          const SizedBox(height: 20),
          if (_error != null)
            Text(
              _error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          if (_saving) const Text('Creating account…'),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: _saving ? null : _save,
            child: Text(_saving ? 'Please wait…' : 'Create staff account'),
          ),
        ],
      ),
    ),
  );
}

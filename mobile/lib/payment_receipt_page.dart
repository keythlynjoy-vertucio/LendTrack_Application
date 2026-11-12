import 'design/lend_design.dart';

import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

import 'services/api.dart';

class PaymentReceiptPage extends StatefulWidget {
  final int paymentId;
  const PaymentReceiptPage({super.key, required this.paymentId});
  @override
  State<PaymentReceiptPage> createState() => _PaymentReceiptPageState();
}

class _PaymentReceiptPageState extends State<PaymentReceiptPage> {
  Map<String, dynamic>? _receipt;
  pw.Font? _font;
  String? _error;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _error = null;
      _receipt = null;
    });
    try {
      final data = await Api.request('payments/${widget.paymentId}/receipt');
      final font = await fontFromAssetBundle('assets/fonts/DejaVuSans.ttf');
      if (mounted) {
        setState(() {
          _receipt = data;
          _font = font;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _error = '$e');
      }
    }
  }

  // Called again by the preview/print/share layout request, using the same
  // payment snapshot but a fresh receipt-generation timestamp.
  Future<Uint8List> _buildPdf() async {
    final data = _receipt!;
    final document = pw.Document();
    final issued = DateTime.now().toUtc().add(const Duration(hours: 8));
    String two(int n) => n.toString().padLeft(2, '0');
    final hour = issued.hour % 12 == 0 ? 12 : issued.hour % 12;
    final issuedText =
        '${issued.year}-${two(issued.month)}-${two(issued.day)} '
        '${two(hour)}:${two(issued.minute)} ${issued.hour < 12 ? 'AM' : 'PM'} PHT';
    String value(String key) => data[key]?.toString().trim().isNotEmpty == true
        ? data[key].toString()
        : '—';
    String money(String key) {
      final n = num.tryParse(data[key]?.toString() ?? '');
      return n == null ? 'Not recorded' : 'PHP ${n.toStringAsFixed(2)}';
    }

    pw.Widget row(String label, String text) => pw.Padding(
      padding: const pw.EdgeInsets.symmetric(vertical: 3),
      child: pw.Row(
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          pw.Expanded(
            flex: 2,
            child: pw.Text(
              label,
              style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700),
            ),
          ),
          pw.Expanded(
            flex: 3,
            child: pw.Text(
              text,
              textAlign: pw.TextAlign.right,
              style: const pw.TextStyle(fontSize: 9),
            ),
          ),
        ],
      ),
    );
    document.addPage(
      pw.Page(
        pageFormat: PdfPageFormat.a5,
        margin: const pw.EdgeInsets.all(20),
        theme: pw.ThemeData.withFont(base: _font!, bold: _font!),
        build: (_) => pw.FittedBox(
          fit: pw.BoxFit.scaleDown,
          alignment: pw.Alignment.topCenter,
          child: pw.SizedBox(
            width: PdfPageFormat.a5.width - 40,
            child: pw.Column(
              mainAxisSize: pw.MainAxisSize.min,
              crossAxisAlignment: pw.CrossAxisAlignment.stretch,
              children: [
                pw.Container(
                  width: double.infinity,
                  padding: const pw.EdgeInsets.all(10),
                  color: PdfColor.fromHex('#087F73'),
                  child: pw.Column(
                    crossAxisAlignment: pw.CrossAxisAlignment.start,
                    children: [
                      pw.Text(
                        'LendTrack',
                        style: const pw.TextStyle(
                          fontSize: 17,
                          color: PdfColors.white,
                        ),
                      ),
                      pw.SizedBox(height: 5),
                      pw.Text(
                        'PAYMENT RECEIPT',
                        style: const pw.TextStyle(
                          fontSize: 11,
                          color: PdfColors.white,
                        ),
                      ),
                    ],
                  ),
                ),
                pw.SizedBox(height: 8),
                row('Receipt number', value('receipt_number')),
                row('Loan reference', value('loan_reference')),
                row('Borrower', value('borrower')),
                row('Payment date', value('date')),
                row('Receipt date & time', issuedText),
                pw.Divider(color: PdfColors.grey300),
                row('Regular payment', money('regular_amount')),
                row('Penalty received', money('penalty_paid')),
                pw.Container(
                  padding: const pw.EdgeInsets.symmetric(
                    horizontal: 9,
                    vertical: 7,
                  ),
                  color: PdfColors.grey100,
                  child: pw.Row(
                    children: [
                      pw.Expanded(
                        child: pw.Text(
                          'Total received',
                          style: const pw.TextStyle(fontSize: 10),
                        ),
                      ),
                      pw.Expanded(
                        child: pw.Text(
                          money('total_received'),
                          textAlign: pw.TextAlign.right,
                          style: const pw.TextStyle(fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                ),
                pw.SizedBox(height: 6),
                row('Payment method', value('method').toUpperCase()),
                row('Transaction reference', value('reference_no')),
                row('Received by', value('received_by')),
                pw.Divider(color: PdfColors.grey300),
                pw.Text(
                  'Balance after payment',
                  style: const pw.TextStyle(fontSize: 9),
                ),
                row('Regular balance', money('regular_balance')),
                row('Unpaid penalty', money('penalty_balance')),
                pw.SizedBox(height: 5),
                pw.Text(
                  'Remarks',
                  style: const pw.TextStyle(
                    fontSize: 9,
                    color: PdfColors.grey700,
                  ),
                ),
                pw.SizedBox(height: 3),
                pw.Text(
                  value('remarks'),
                  style: const pw.TextStyle(fontSize: 9),
                ),
                pw.SizedBox(height: 8),
                pw.Text(
                  'Thank you. Keep this receipt for your records.',
                  style: const pw.TextStyle(fontSize: 9),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    return document.save();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Payment receipt')),
    body: _error != null
        ? Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(_error!),
                  FilledButton(onPressed: _load, child: const Text('Retry')),
                ],
              ),
            ),
          )
        : _receipt == null
        ? const AppLoading()
        : PdfPreview(
            build: (_) => _buildPdf(),
            pdfFileName: 'LendTrack-payment-${widget.paymentId}.pdf',
            canChangePageFormat: false,
            canChangeOrientation: false,
            allowPrinting: true,
            allowSharing: true,
          ),
  );
}

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/screen_header.dart';
import 'stock_in_provider.dart';
import 'widgets/stock_in_item_row.dart';

class StockInScreen extends ConsumerStatefulWidget {
  const StockInScreen({super.key});

  @override
  ConsumerState<StockInScreen> createState() => _StockInScreenState();
}

class _StockInScreenState extends ConsumerState<StockInScreen> {
  final _poController = TextEditingController();
  bool _isSubmitting = false;

  @override
  void dispose() {
    _poController.dispose();
    super.dispose();
  }

  Future<void> _openScan() async {
    final catalog = await context.push<Map<String, dynamic>>(
      '/scan?mode=select',
    );
    if (catalog != null && mounted) {
      ref.read(stockInProvider.notifier).addItem(catalog);
    }
  }

  Future<void> _confirmSubmit() async {
    final items = ref.read(stockInProvider);
    if (items.isEmpty) return;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(
          'Konfirmasi Barang Masuk',
          style: AppTextStyles.cardTitle.copyWith(fontSize: 16),
        ),
        content: Text(
          'Kirim ${items.length} item ke gudang pusat?',
          style: AppTextStyles.cardSubtitle.copyWith(fontSize: 13),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('BATAL'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            style: ElevatedButton.styleFrom(
              minimumSize: const Size(80, 40),
            ),
            child: const Text('KIRIM'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;
    await _submit();
  }

  Future<void> _submit() async {
    setState(() => _isSubmitting = true);

    try {
      final dio = ref.read(apiClientProvider).dio;
      final po = _poController.text.trim();
      await ref.read(stockInProvider.notifier).submitTransaction(
            dio,
            transactionDate: DateFormat('yyyy-MM-dd').format(DateTime.now()),
            poReference: po.isEmpty ? null : po,
          );

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Barang masuk berhasil dikirim'),
          backgroundColor: AppColors.success,
        ),
      );
      context.pop();
    } on DioException {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.',
            ),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Terjadi kesalahan. Coba lagi.'),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final items = ref.watch(stockInProvider);
    final totalQty = items.fold<int>(0, (sum, item) => sum + item.qty);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: AppColors.background,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.voidBlack),
          onPressed: () => context.pop(),
        ),
      ),
      body: SafeArea(
        top: false,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const ScreenHeader(
                      backLabel: 'RECEIVE STOCK',
                      title: 'Barang Masuk',
                    ),
                    const SizedBox(height: 20),
                    _PoReferenceCard(controller: _poController),
                    const SizedBox(height: 20),
                    if (items.isEmpty)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 24),
                        child: Text(
                          'Belum ada item. Scan QR Code untuk menambah.',
                          textAlign: TextAlign.center,
                          style: AppTextStyles.cardSubtitle.copyWith(
                            fontSize: 13,
                          ),
                        ),
                      )
                    else
                      ...items.map(
                        (item) => StockInItemRow(
                          item: item,
                          onQtyDelta: (delta) => ref
                              .read(stockInProvider.notifier)
                              .updateQty(item.barcode, delta),
                        ),
                      ),
                    const SizedBox(height: 16),
                    SizedBox(
                      height: 48,
                      child: OutlinedButton.icon(
                        onPressed: _isSubmitting ? null : _openScan,
                        icon: const Icon(
                          Icons.qr_code_scanner,
                          size: 18,
                          color: AppColors.voidBlack,
                        ),
                        label: Text(
                          '+ SCAN ITEM',
                          style: GoogleFonts.inter(
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            letterSpacing: 1.5,
                            color: AppColors.voidBlack,
                          ),
                        ),
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(color: AppColors.border),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 80),
                  ],
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
              child: SizedBox(
                height: 52,
                child: ElevatedButton(
                  onPressed: items.isEmpty || _isSubmitting
                      ? null
                      : _confirmSubmit,
                  child: _isSubmitting
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : Text(
                          '✓ KONFIRMASI $totalQty ITEM',
                          style: AppTextStyles.buttonPrimary,
                        ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PoReferenceCard extends StatelessWidget {
  final TextEditingController controller;

  const _PoReferenceCard({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('PO REFERENCE', style: AppTextStyles.sectionLabel),
          const SizedBox(height: 10),
          TextField(
            controller: controller,
            style: AppTextStyles.monoBold,
            decoration: InputDecoration(
              hintText: 'PO-2026-XXXX',
              hintStyle: AppTextStyles.monoMuted,
              filled: true,
              fillColor: AppColors.card,
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 12,
                vertical: 12,
              ),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
                borderSide: const BorderSide(color: AppColors.border),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
                borderSide: const BorderSide(color: AppColors.border),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
                borderSide: const BorderSide(color: AppColors.voidBlack),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

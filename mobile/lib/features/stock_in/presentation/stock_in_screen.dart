import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/opname/active_opname_provider.dart';
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
  final _imagePicker = ImagePicker();
  bool _isSubmitting = false;
  String? _photoId;
  String? _photoPath;

  @override
  void dispose() {
    _poController.dispose();
    super.dispose();
  }

  String get _photoRelatedId {
    final userId = ref.read(authProvider).userId;
    if (userId != null && userId.isNotEmpty) return userId;
    return '00000000-0000-4000-8000-000000000001';
  }

  Future<void> _openScan() async {
    final catalog = await context.push<Map<String, dynamic>>(
      '/scan?mode=select',
    );
    if (catalog != null && mounted) {
      ref.read(stockInProvider.notifier).addItem(catalog);
    }
  }

  Future<void> _takePhoto() async {
    final image = await _imagePicker.pickImage(
      source: ImageSource.camera,
      imageQuality: 80,
      maxWidth: 1200,
    );
    if (image == null || !mounted) return;

    setState(() {
      _photoPath = image.path;
      _photoId = null;
    });

    try {
      final dio = ref.read(apiClientProvider).dio;
      final photoId = await ref.read(stockInServiceProvider).uploadPhoto(
            dio,
            filePath: image.path,
            relatedId: _photoRelatedId,
          );
      if (!mounted) return;
      setState(() => _photoId = photoId);
      if (photoId == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Upload foto gagal. Coba lagi.'),
            backgroundColor: AppColors.warning,
          ),
        );
      }
    } catch (e) {
      debugPrint('Photo upload failed: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Upload foto gagal. Transaksi tetap bisa dikirim.'),
            backgroundColor: AppColors.warning,
          ),
        );
      }
    }
  }

  void _clearPhoto() {
    setState(() {
      _photoId = null;
      _photoPath = null;
    });
  }

  Future<void> _confirmSubmit() async {
    final items = ref.read(stockInProvider);
    if (items.isEmpty) return;

    if (await isActiveOpnameBlocking(ref.read(apiClientProvider).dio)) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Sesi opname aktif. Selesaikan opname terlebih dahulu.',
          ),
          backgroundColor: AppColors.danger,
        ),
      );
      return;
    }

    final totalQty = items.fold<int>(0, (sum, item) => sum + item.qty);
    final itemCount = items.length;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Konfirmasi Barang Masuk'),
        content: Text(
          'Kirim $totalQty unit ($itemCount jenis item) '
          'ke gudang pusat?\n\n'
          'Harga modal akan diisi via web admin.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('BATAL'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('KIRIM'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;
    await _submitStockIn();
  }

  Future<void> _submitStockIn() async {
    setState(() => _isSubmitting = true);

    try {
      final dio = ref.read(apiClientProvider).dio;
      final po = _poController.text.trim();
      await ref.read(stockInProvider.notifier).submitTransaction(
            dio,
            transactionDate: DateFormat('yyyy-MM-dd').format(DateTime.now()),
            poReference: po.isEmpty ? null : po,
            photoId: _photoId,
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

  Widget _buildPhotoSection() {
    final hasPreview = _photoPath != null && !kIsWeb;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('FOTO QC', style: AppTextStyles.sectionLabel),
          const SizedBox(height: 8),
          if (!hasPreview) ...[
            OutlinedButton.icon(
              onPressed: _isSubmitting ? null : _takePhoto,
              icon: const Icon(Icons.camera_alt_outlined, size: 18),
              label: const Text('AMBIL FOTO'),
              style: OutlinedButton.styleFrom(
                minimumSize: const Size(double.infinity, 44),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
            ),
            if (_photoPath != null && kIsWeb)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  'Foto dipilih (preview tidak tersedia di web)',
                  style: AppTextStyles.cardSubtitle,
                ),
              ),
          ] else ...[
            Stack(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.file(
                    File(_photoPath!),
                    height: 120,
                    width: double.infinity,
                    fit: BoxFit.cover,
                  ),
                ),
                Positioned(
                  top: 4,
                  right: 4,
                  child: GestureDetector(
                    onTap: _isSubmitting ? null : _clearPhoto,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        color: AppColors.danger,
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: const Icon(
                        Icons.close,
                        size: 14,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ),
              ],
            ),
            if (_photoId != null) ...[
              const SizedBox(height: 6),
              Text(
                'Foto terunggah',
                style: AppTextStyles.cardSubtitle.copyWith(
                  color: AppColors.success,
                  fontSize: 10,
                ),
              ),
            ],
          ],
        ],
      ),
    );
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
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (items.isNotEmpty) ...[
                    _buildPhotoSection(),
                    const SizedBox(height: 12),
                  ],
                  SizedBox(
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
                ],
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

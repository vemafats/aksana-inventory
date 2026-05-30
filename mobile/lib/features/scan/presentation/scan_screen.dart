import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/screen_header.dart';
import '../../sales/presentation/sales_provider.dart';
import 'scan_provider.dart';

class ScanScreen extends ConsumerStatefulWidget {
  final bool selectionMode;

  const ScanScreen({super.key, this.selectionMode = false});

  @override
  ConsumerState<ScanScreen> createState() => _ScanScreenState();
}

class _ScanScreenState extends ConsumerState<ScanScreen> {
  MobileScannerController? _controller;
  bool _isProcessing = false;

  @override
  void initState() {
    super.initState();
    if (!kIsWeb) {
      _controller = MobileScannerController(
        detectionSpeed: DetectionSpeed.noDuplicates,
        facing: CameraFacing.back,
        torchEnabled: false,
        formats: const [BarcodeFormat.qrCode],
      );
    }
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  String _locationBackLabel(Map<String, dynamic>? user) {
    final locations = user?['assigned_locations'];
    if (locations is List && locations.isNotEmpty) {
      final first = locations.first;
      if (first is Map) {
        final name = first['name'] ?? first['location_name'];
        if (name != null) {
          return name.toString().trim().replaceAll(' ', '_').toUpperCase();
        }
      }
    }
    return widget.selectionMode ? 'PILIH ITEM' : 'TERMINAL_04';
  }

  String? _extractBarcode(BarcodeCapture capture) {
    for (final barcode in capture.barcodes) {
      final value = barcode.rawValue ?? barcode.displayValue;
      if (value != null && value.trim().isNotEmpty) {
        return value.trim();
      }
    }
    return null;
  }

  Future<void> _onBarcodeDetected(BarcodeCapture capture) async {
    if (_isProcessing) return;

    final barcode = _extractBarcode(capture);
    if (barcode == null) {
      debugPrint('[Scan] onDetect fired but no usable barcode value');
      return;
    }

    debugPrint('[Scan] Barcode detected: $barcode');

    final apiClient = ref.read(apiClientProvider);
    final token = await apiClient.getToken();
    if (token == null || token.isEmpty) {
      debugPrint('[Scan] No auth token');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Sesi berakhir. Silakan login kembali.'),
          backgroundColor: AppColors.danger,
        ),
      );
      context.go('/login');
      return;
    }

    setState(() => _isProcessing = true);
    ref.read(scanProcessingProvider.notifier).state = true;
    await _controller?.stop();

    ref.read(scanLoadingProvider.notifier).state = true;
    ref.read(scanErrorProvider.notifier).state = null;
    ref.read(scanResultProvider.notifier).state = null;

    try {
      final result = await ref
          .read(scanServiceProvider)
          .findByBarcode(barcode, apiClient.dio);

      if (!mounted) return;

      if (result == null) {
        debugPrint('[Scan] Barcode not found: $barcode');
        ref.read(scanErrorProvider.notifier).state =
            'Barcode tidak ditemukan. Buat katalog dulu di web admin.';
        await _resetScan(restartCamera: true);
      } else if (widget.selectionMode) {
        debugPrint('[Scan] Selection mode — returning item');
        context.pop(result);
      } else {
        debugPrint('[Scan] Success — ${result['item_name']}');
        ref.read(scanResultProvider.notifier).state = result;
        setState(() => _isProcessing = false);
        ref.read(scanProcessingProvider.notifier).state = false;
      }
    } on DioException catch (e) {
      debugPrint('[Scan] API error: ${e.response?.statusCode} ${e.message}');
      if (!mounted) return;

      if (e.response?.statusCode == 401) {
        await apiClient.clearToken();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Sesi berakhir. Silakan login kembali.'),
            backgroundColor: AppColors.danger,
          ),
        );
        context.go('/login');
        return;
      }

      ref.read(scanErrorProvider.notifier).state =
          'Gagal memuat data: ${e.message ?? e.toString()}';
      await _resetScan(restartCamera: true);
    } catch (e) {
      debugPrint('[Scan] Unexpected error: $e');
      if (!mounted) return;
      ref.read(scanErrorProvider.notifier).state =
          'Gagal memuat data: ${e.toString()}';
      await _resetScan(restartCamera: true);
    } finally {
      ref.read(scanLoadingProvider.notifier).state = false;
    }
  }

  Future<void> _resetScan({bool restartCamera = true}) async {
    ref.read(scanResultProvider.notifier).state = null;
    ref.read(scanErrorProvider.notifier).state = null;
    ref.read(scanProcessingProvider.notifier).state = false;

    if (mounted) {
      setState(() => _isProcessing = false);
    }

    if (restartCamera && !kIsWeb && _controller != null) {
      await _controller!.start();
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    final scanResult = ref.watch(scanResultProvider);
    final isLoading = ref.watch(scanLoadingProvider);
    final scanError = ref.watch(scanErrorProvider);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: widget.selectionMode
          ? AppBar(
              backgroundColor: AppColors.background,
              elevation: 0,
              leading: IconButton(
                icon: const Icon(Icons.close, color: AppColors.voidBlack),
                onPressed: () => context.pop(),
              ),
            )
          : null,
      body: SafeArea(
        top: !widget.selectionMode,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ScreenHeader(
                  backLabel: _locationBackLabel(auth.user),
                  title: widget.selectionMode ? 'Scan Item' : 'Quick Scan',
                ),
                const SizedBox(height: 16),
                ClipRRect(
                  borderRadius: BorderRadius.circular(16),
                  child: SizedBox(
                    height: 260,
                    width: double.infinity,
                    child: kIsWeb || _controller == null
                        ? ColoredBox(
                            color: AppColors.cameraBg,
                            child: Center(
                              child: Icon(
                                Icons.qr_code_2,
                                size: 64,
                                color: Colors.white.withValues(alpha: 0.3),
                              ),
                            ),
                          )
                        : Stack(
                            fit: StackFit.expand,
                            children: [
                              MobileScanner(
                                controller: _controller!,
                                fit: BoxFit.cover,
                                onDetect: _onBarcodeDetected,
                              ),
                              Positioned(
                                top: 12,
                                left: 12,
                                child: _corner(topLeft: true),
                              ),
                              Positioned(
                                top: 12,
                                right: 12,
                                child: _corner(topRight: true),
                              ),
                              Positioned(
                                bottom: 12,
                                left: 12,
                                child: _corner(bottomLeft: true),
                              ),
                              Positioned(
                                bottom: 12,
                                right: 12,
                                child: _corner(bottomRight: true),
                              ),
                              Positioned(
                                top: 12,
                                left: 36,
                                child: Text(
                                  'CAMERA_ACTIVE',
                                  style: AppTextStyles.monoMuted.copyWith(
                                    color: Colors.white.withValues(alpha: 0.5),
                                    fontSize: 9,
                                  ),
                                ),
                              ),
                              Positioned(
                                bottom: 12,
                                right: 36,
                                child: Text(
                                  'scanning...',
                                  style: AppTextStyles.monoMuted.copyWith(
                                    color: AppColors.success,
                                    fontSize: 9,
                                  ),
                                ),
                              ),
                              if (isLoading)
                                ColoredBox(
                                  color: Colors.black.withValues(alpha: 0.5),
                                  child: const Center(
                                    child: CircularProgressIndicator(
                                      color: Colors.white,
                                    ),
                                  ),
                                ),
                            ],
                          ),
                  ),
                ),
                const SizedBox(height: 16),
                if (scanError != null)
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppColors.danger.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(
                        color: AppColors.danger.withValues(alpha: 0.3),
                      ),
                    ),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.error_outline,
                          color: AppColors.danger,
                          size: 16,
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            scanError,
                            style: AppTextStyles.cardSubtitle.copyWith(
                              color: AppColors.danger,
                            ),
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.close, size: 16),
                          onPressed: () {
                            ref.read(scanErrorProvider.notifier).state = null;
                          },
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(),
                        ),
                      ],
                    ),
                  ),
                if (!widget.selectionMode && scanResult != null) ...[
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: AppColors.card,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.border),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                scanResult['item_name']?.toString() ?? '-',
                                style: AppTextStyles.cardTitle,
                              ),
                            ),
                            Text(
                              'Rp ${_formatPrice(scanResult['latest_base_selling_price'])}',
                              style: AppTextStyles.monoBold,
                            ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'SKU ${scanResult['barcode'] ?? '-'} · STOK: ${_getTotalStock(scanResult)}',
                          style: AppTextStyles.monoMuted,
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: ElevatedButton(
                                onPressed: () {
                                  ref
                                      .read(salesCartProvider.notifier)
                                      .addItem(scanResult);
                                  context.go('/sales');
                                },
                                style: ElevatedButton.styleFrom(
                                  minimumSize: const Size(double.infinity, 52),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                ),
                                child: Text(
                                  'JUAL',
                                  style: AppTextStyles.buttonPrimary,
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: OutlinedButton(
                                onPressed: () => _resetScan(restartCamera: true),
                                style: OutlinedButton.styleFrom(
                                  minimumSize: const Size(double.infinity, 52),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  side: const BorderSide(color: AppColors.border),
                                ),
                                child: Text(
                                  'SCAN LAGI',
                                  style: AppTextStyles.buttonPrimary.copyWith(
                                    color: AppColors.voidBlack,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  String _formatPrice(dynamic price) {
    if (price == null) return '0';
    final num p = num.tryParse(price.toString()) ?? 0;
    if (p >= 1000000) return '${(p / 1000000).toStringAsFixed(1)}M';
    if (p >= 1000) return '${(p / 1000).toStringAsFixed(0)}k';
    return p.toStringAsFixed(0);
  }

  int _getTotalStock(Map<String, dynamic> item) {
    final summary = item['stock_summary'];
    if (summary is Map && summary['total_available'] != null) {
      return int.tryParse(summary['total_available'].toString()) ?? 0;
    }

    final balances = item['stock_balances'] as List? ?? [];
    return balances.fold<int>(0, (sum, balance) {
      if (balance is! Map) return sum;
      final status = balance['stock_status']?.toString();
      if (status == 'available') {
        return sum + (int.tryParse(balance['qty'].toString()) ?? 0);
      }
      return sum;
    });
  }

  Widget _corner({
    bool topLeft = false,
    bool topRight = false,
    bool bottomLeft = false,
    bool bottomRight = false,
  }) {
    return SizedBox(
      width: 20,
      height: 20,
      child: CustomPaint(
        painter: _CornerPainter(
          topLeft: topLeft,
          topRight: topRight,
          bottomLeft: bottomLeft,
          bottomRight: bottomRight,
        ),
      ),
    );
  }
}

class _CornerPainter extends CustomPainter {
  final bool topLeft;
  final bool topRight;
  final bool bottomLeft;
  final bool bottomRight;

  _CornerPainter({
    this.topLeft = false,
    this.topRight = false,
    this.bottomLeft = false,
    this.bottomRight = false,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white.withValues(alpha: 0.5)
      ..strokeWidth = 2
      ..style = PaintingStyle.stroke;

    final path = Path();
    if (topLeft) {
      path.moveTo(0, size.height * 0.6);
      path.lineTo(0, 0);
      path.lineTo(size.width * 0.6, 0);
    }
    if (topRight) {
      path.moveTo(size.width * 0.4, 0);
      path.lineTo(size.width, 0);
      path.lineTo(size.width, size.height * 0.6);
    }
    if (bottomLeft) {
      path.moveTo(0, size.height * 0.4);
      path.lineTo(0, size.height);
      path.lineTo(size.width * 0.6, size.height);
    }
    if (bottomRight) {
      path.moveTo(size.width * 0.4, size.height);
      path.lineTo(size.width, size.height);
      path.lineTo(size.width, size.height * 0.4);
    }
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(_CornerPainter old) => false;
}

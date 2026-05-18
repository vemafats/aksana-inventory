import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/screen_header.dart';
import 'scan_provider.dart';

class ScanScreen extends ConsumerStatefulWidget {
  final bool selectionMode;

  const ScanScreen({super.key, this.selectionMode = false});

  @override
  ConsumerState<ScanScreen> createState() => _ScanScreenState();
}

class _ScanScreenState extends ConsumerState<ScanScreen>
    with SingleTickerProviderStateMixin {
  MobileScannerController? _scannerController;
  late AnimationController _scanLineController;
  bool _scanLocked = false;
  final _manualBarcodeController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _scanLineController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2200),
    )..repeat(reverse: true);

    if (!kIsWeb) {
      _scannerController = MobileScannerController(
        detectionSpeed: DetectionSpeed.normal,
        facing: CameraFacing.back,
      );
    }
  }

  @override
  void dispose() {
    _scanLineController.dispose();
    _scannerController?.dispose();
    _manualBarcodeController.dispose();
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
    return 'TERMINAL_04';
  }

  Future<void> _onBarcodeDetected(String barcode) async {
    if (_scanLocked || ref.read(isLoadingProvider)) return;

    _scanLocked = true;
    ref.read(scanResultProvider.notifier).state = null;
    ref.read(scanErrorProvider.notifier).state = null;
    ref.read(isLoadingProvider.notifier).state = true;

    try {
      final dio = ref.read(apiClientProvider).dio;
      final item = await ref
          .read(scanServiceProvider)
          .findByBarcode(barcode.trim(), dio);

      if (!mounted) return;

      if (item == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Barcode tidak ditemukan. Buat katalog dulu di web admin.',
            ),
            backgroundColor: AppColors.danger,
          ),
        );
      } else if (widget.selectionMode) {
        context.pop(item);
      } else {
        ref.read(scanResultProvider.notifier).state = item;
      }
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
      ref.read(isLoadingProvider.notifier).state = false;
      Future.delayed(const Duration(seconds: 2), () {
        if (mounted) _scanLocked = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    final scanResult = ref.watch(scanResultProvider);
    final isLoading = ref.watch(isLoadingProvider);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: widget.selectionMode
          ? AppBar(
              backgroundColor: AppColors.background,
              leading: IconButton(
                icon: const Icon(Icons.close, color: AppColors.voidBlack),
                onPressed: () => context.pop(),
              ),
            )
          : null,
      body: SafeArea(
        top: !widget.selectionMode,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                backLabel: widget.selectionMode
                    ? 'PILIH ITEM'
                    : _locationBackLabel(auth.user),
                title: widget.selectionMode ? 'Scan Item' : 'Quick Scan',
              ),
              const SizedBox(height: 20),
              _CameraView(
                isLoading: isLoading,
                scanLineAnimation: _scanLineController,
                scannerController: _scannerController,
                onBarcode: _onBarcodeDetected,
              ),
              if (widget.selectionMode && kIsWeb) ...[
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _manualBarcodeController,
                        style: AppTextStyles.mono,
                        decoration: InputDecoration(
                          hintText: 'Masukkan barcode',
                          hintStyle: AppTextStyles.monoMuted,
                          filled: true,
                          fillColor: AppColors.card,
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(8),
                            borderSide:
                                const BorderSide(color: AppColors.border),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    SizedBox(
                      height: 48,
                      child: ElevatedButton(
                        onPressed: isLoading
                            ? null
                            : () => _onBarcodeDetected(
                                  _manualBarcodeController.text,
                                ),
                        style: ElevatedButton.styleFrom(
                          minimumSize: const Size(72, 48),
                        ),
                        child: const Text('CARI'),
                      ),
                    ),
                  ],
                ),
              ],
              if (!widget.selectionMode && scanResult != null) ...[
                const SizedBox(height: 16),
                _ResultCard(item: scanResult),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _CameraView extends StatelessWidget {
  final bool isLoading;
  final AnimationController scanLineAnimation;
  final MobileScannerController? scannerController;
  final ValueChanged<String> onBarcode;

  const _CameraView({
    required this.isLoading,
    required this.scanLineAnimation,
    required this.scannerController,
    required this.onBarcode,
  });

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: SizedBox(
        height: 280,
        width: double.infinity,
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (kIsWeb)
              const _WebCameraPlaceholder()
            else
              MobileScanner(
                controller: scannerController,
                onDetect: (capture) {
                  for (final barcode in capture.barcodes) {
                    final value = barcode.rawValue;
                    if (value != null && value.isNotEmpty) {
                      onBarcode(value);
                      return;
                    }
                  }
                },
              ),
            const _CameraOverlay(),
            _AnimatedScanLine(animation: scanLineAnimation),
            if (isLoading)
              Container(
                color: Colors.black54,
                child: const Center(
                  child: CircularProgressIndicator(
                    color: AppColors.scanLine,
                    strokeWidth: 2,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _WebCameraPlaceholder extends StatelessWidget {
  const _WebCameraPlaceholder();

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: AppColors.cameraBg,
      child: Center(
        child: Icon(
          Icons.qr_code_2,
          size: 64,
          color: Colors.white.withValues(alpha: 0.3),
        ),
      ),
    );
  }
}

class _CameraOverlay extends StatelessWidget {
  const _CameraOverlay();

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Positioned(
          top: 12,
          left: 12,
          child: Text(
            'CAMERA_ACTIVE',
            style: GoogleFonts.ibmPlexMono(
              fontSize: 9,
              fontWeight: FontWeight.w400,
              color: Colors.white.withValues(alpha: 0.4),
            ),
          ),
        ),
        Positioned(
          bottom: 12,
          right: 12,
          child: Text(
            'scanning...',
            style: GoogleFonts.ibmPlexMono(
              fontSize: 9,
              fontWeight: FontWeight.w400,
              color: AppColors.scanLine,
            ),
          ),
        ),
        const _CornerBrackets(),
      ],
    );
  }
}

class _CornerBrackets extends StatelessWidget {
  const _CornerBrackets();

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Positioned(top: 16, left: 16, child: _bracket(true, true)),
        Positioned(top: 16, right: 16, child: _bracket(true, false)),
        Positioned(bottom: 16, left: 16, child: _bracket(false, true)),
        Positioned(bottom: 16, right: 16, child: _bracket(false, false)),
      ],
    );
  }

  Widget _bracket(bool top, bool left) {
    return SizedBox(
      width: 20,
      height: 20,
      child: CustomPaint(
        painter: _BracketPainter(top: top, left: left),
      ),
    );
  }
}

class _BracketPainter extends CustomPainter {
  final bool top;
  final bool left;

  _BracketPainter({required this.top, required this.left});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white.withValues(alpha: 0.3)
      ..strokeWidth = 2
      ..style = PaintingStyle.stroke;

    final path = Path();
    if (top && left) {
      path.moveTo(0, size.height);
      path.lineTo(0, 0);
      path.lineTo(size.width, 0);
    } else if (top && !left) {
      path.moveTo(0, 0);
      path.lineTo(size.width, 0);
      path.lineTo(size.width, size.height);
    } else if (!top && left) {
      path.moveTo(0, 0);
      path.lineTo(0, size.height);
      path.lineTo(size.width, size.height);
    } else {
      path.moveTo(size.width, 0);
      path.lineTo(size.width, size.height);
      path.lineTo(0, size.height);
    }
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _AnimatedScanLine extends StatelessWidget {
  final AnimationController animation;

  const _AnimatedScanLine({required this.animation});

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: animation,
      builder: (context, child) {
        return Align(
          alignment: Alignment(0, -1 + 2 * animation.value),
          child: Container(
            height: 2,
            margin: const EdgeInsets.symmetric(horizontal: 32),
            decoration: BoxDecoration(
              color: AppColors.scanLine,
              boxShadow: [
                BoxShadow(
                  color: AppColors.scanLine.withValues(alpha: 0.55),
                  blurRadius: 10,
                  spreadRadius: 1,
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _ResultCard extends StatelessWidget {
  final Map<String, dynamic> item;

  const _ResultCard({required this.item});

  String get _name => item['item_name']?.toString() ?? '—';
  String get _sku => item['sku']?.toString() ?? '—';

  int get _qty {
    final summary = item['stock_summary'];
    if (summary is Map) {
      return (summary['total_available'] as num?)?.toInt() ?? 0;
    }
    return 0;
  }

  String get _priceLabel {
    final raw = item['bazar_selling_price'] ?? item['latest_base_selling_price'];
    if (raw == null) return '—';
    final price = (raw as num).toDouble();
    if (price >= 1000000) {
      return NumberFormat.compactCurrency(
        locale: 'id_ID',
        symbol: 'Rp ',
        decimalDigits: 1,
      ).format(price);
    }
    if (price >= 1000) {
      final k = price / 1000;
      final text = k == k.roundToDouble()
          ? k.toInt().toString()
          : k.toStringAsFixed(1);
      return 'Rp ${text}k';
    }
    return NumberFormat.currency(
      locale: 'id_ID',
      symbol: 'Rp ',
      decimalDigits: 0,
    ).format(price);
  }

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
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  _name,
                  style: AppTextStyles.cardTitle.copyWith(fontSize: 14),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(width: 12),
              Text(_priceLabel, style: AppTextStyles.monoBold),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            'SKU $_sku · STOK: ${AppColors.formatQty(_qty)}',
            style: AppTextStyles.monoMuted,
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: SizedBox(
                  height: 44,
                  child: ElevatedButton(
                    onPressed: () => context.go('/sales'),
                    style: ElevatedButton.styleFrom(
                      minimumSize: const Size(0, 44),
                    ),
                    child: Text('JUAL', style: AppTextStyles.buttonPrimary),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: SizedBox(
                  height: 44,
                  child: OutlinedButton(
                    onPressed: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Detail — akan diimplementasi'),
                        ),
                      );
                    },
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.voidBlack,
                      side: const BorderSide(color: AppColors.border),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      textStyle: GoogleFonts.inter(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 1.5,
                      ),
                    ),
                    child: const Text('DETAIL'),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

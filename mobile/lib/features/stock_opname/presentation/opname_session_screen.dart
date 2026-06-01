import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../scan/presentation/scan_provider.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/utils/format_utils.dart';
import '../../../core/widgets/screen_header.dart';
import 'opname_session_provider.dart';

class OpnameSessionScreen extends ConsumerStatefulWidget {
  final String sessionId;

  const OpnameSessionScreen({required this.sessionId, super.key});

  @override
  ConsumerState<OpnameSessionScreen> createState() =>
      _OpnameSessionScreenState();
}

class _OpnameSessionScreenState extends ConsumerState<OpnameSessionScreen> {
  Future<void> _startScan() async {
    final result = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) => const _OpnameScannerPage(),
      ),
    );
    if (result == null || !mounted) return;
    await _showQtyInputSheet(result);
  }

  Future<void> _showQtyInputSheet(Map<String, dynamic> item) async {
    final itemId = item['id']?.toString();
    if (itemId == null) return;

    final notifier =
        ref.read(opnameSessionProvider(widget.sessionId).notifier);
    final systemQty = notifier.systemQtyForItem(itemId);

    final physicalController = TextEditingController(
      text: systemQty.toString(),
    );
    final damagedController = TextEditingController(text: '0');

    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.card,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          top: 20,
          bottom: 20 + MediaQuery.of(ctx).viewInsets.bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              item['item_name']?.toString() ?? '—',
              style: AppTextStyles.cardTitle.copyWith(fontSize: 16),
            ),
            const SizedBox(height: 4),
            Text(
              item['barcode']?.toString() ?? '—',
              style: AppTextStyles.monoMuted,
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Text('Stok Sistem', style: AppTextStyles.cardSubtitle),
                const Spacer(),
                Text(
                  '$systemQty unit',
                  style: AppTextStyles.monoBold.copyWith(fontSize: 16),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Text('QTY FISIK', style: AppTextStyles.sectionLabel),
            const SizedBox(height: 6),
            TextField(
              controller: physicalController,
              keyboardType: TextInputType.number,
              autofocus: true,
              style: AppTextStyles.monoBold.copyWith(fontSize: 28),
              textAlign: TextAlign.center,
              decoration: InputDecoration(
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
            ),
            const SizedBox(height: 12),
            Text('QTY RUSAK (opsional)', style: AppTextStyles.sectionLabel),
            const SizedBox(height: 6),
            TextField(
              controller: damagedController,
              keyboardType: TextInputType.number,
              style: AppTextStyles.mono.copyWith(fontSize: 18),
              textAlign: TextAlign.center,
              decoration: InputDecoration(
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
            ),
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.pop(ctx, false),
                    child: const Text('BATAL'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () => Navigator.pop(ctx, true),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.voidBlack,
                    ),
                    child: const Text('SIMPAN ITEM'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );

    final physicalQty =
        int.tryParse(physicalController.text.trim()) ?? systemQty;
    final damagedQty = int.tryParse(damagedController.text.trim()) ?? 0;
    physicalController.dispose();
    damagedController.dispose();

    if (saved != true || !mounted) return;

    final ok = await notifier.saveItem(
      itemId: itemId,
      physicalQty: physicalQty,
      damagedQty: damagedQty,
    );
    if (!mounted) return;
    if (!ok) {
      final err =
          ref.read(opnameSessionProvider(widget.sessionId)).errorMessage;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(err ?? 'Gagal menyimpan item'),
          backgroundColor: AppColors.danger,
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Item tersimpan'),
          backgroundColor: AppColors.success,
          duration: Duration(seconds: 1),
        ),
      );
    }
  }

  String _sessionLabel(Map<String, dynamic>? session) {
    final number = session?['opname_number']?.toString() ?? '';
    if (number.isEmpty) return '—';
    final parts = number.split('-');
    return parts.isNotEmpty ? parts.last : number;
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(opnameSessionProvider(widget.sessionId));
    final items = state.items;
    final scanned = state.scannedCount;
    final total = state.totalItemsAtLocation;
    final progress = total > 0 ? (scanned / total).clamp(0.0, 1.0) : 0.0;

    int match = 0, lost = 0, damaged = 0;
    for (final row in items) {
      final diff = (row['available_difference_qty'] as num?)?.toInt() ?? 0;
      final lostQty = (row['lost_qty'] as num?)?.toInt() ?? 0;
      final damagedQty = (row['damaged_qty'] as num?)?.toInt() ?? 0;
      if (lostQty > 0 || diff < 0) {
        lost++;
      } else if (damagedQty > 0) {
        damaged++;
      } else if (diff == 0) {
        match++;
      }
    }

    // isPending hanya true jika status benar-benar pending_validation
    final status = state.session?['validation_status']?.toString() ?? '';
    final isPending =
        state.awaitingValidation || status == 'pending_validation';
    final isDraft = status == 'draft' || status.isEmpty;

    final canSubmit = items.isNotEmpty && !isPending && !state.isSubmitting;

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
        child: state.isLoading
            ? const Center(child: CircularProgressIndicator())
            : Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Expanded(
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          ScreenHeader(
                            backLabel:
                                'AUDIT SESI #${_sessionLabel(state.session)}',
                            title: 'Stok Opname',
                          ),
                          const SizedBox(height: 20),
                          _ProgressCard(
                            scanned: scanned,
                            total: total,
                            progress: progress,
                          ),
                          const SizedBox(height: 16),

                          // Status banner
                          if (isPending)
                            Container(
                              padding: const EdgeInsets.all(12),
                              margin: const EdgeInsets.only(bottom: 12),
                              decoration: BoxDecoration(
                                color: AppColors.card,
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: AppColors.border),
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.hourglass_top,
                                      size: 16, color: AppColors.warning),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      'Menunggu validasi dari Owner/Admin.',
                                      style:
                                          AppTextStyles.cardSubtitle.copyWith(
                                        fontSize: 12,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),

                          if (isDraft && items.isEmpty)
                            Padding(
                              padding: const EdgeInsets.symmetric(vertical: 32),
                              child: Column(
                                children: [
                                  const Icon(Icons.qr_code_scanner,
                                      size: 48, color: AppColors.muted),
                                  const SizedBox(height: 12),
                                  Text(
                                    'Belum ada item.',
                                    style: AppTextStyles.cardTitle
                                        .copyWith(fontSize: 15),
                                    textAlign: TextAlign.center,
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    'Tap tombol + SCAN ITEM di bawah untuk mulai.',
                                    textAlign: TextAlign.center,
                                    style: AppTextStyles.cardSubtitle
                                        .copyWith(fontSize: 13),
                                  ),
                                ],
                              ),
                            )
                          else
                            ...items.map((row) => _OpnameItemRow(data: row)),

                          const SizedBox(height: 12),
                          _SummaryRow(
                            match: match,
                            lost: lost,
                            damaged: damaged,
                          ),

                          if (state.errorMessage != null) ...[
                            const SizedBox(height: 12),
                            Text(
                              state.errorMessage!,
                              style:
                                  const TextStyle(color: AppColors.danger),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),

                  // Bottom action buttons — tampil selama session masih draft
                  if (!isPending)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          OutlinedButton.icon(
                            onPressed: _startScan,
                            icon: const Icon(Icons.qr_code_scanner, size: 18),
                            label: const Text('+ SCAN ITEM'),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: AppColors.voidBlack,
                              side:
                                  const BorderSide(color: AppColors.border),
                              minimumSize:
                                  const Size(double.infinity, 52),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                          ),
                          const SizedBox(height: 8),
                          SizedBox(
                            height: 52,
                            child: ElevatedButton(
                              onPressed: canSubmit
                                  ? () async {
                                      final ok = await ref
                                          .read(
                                            opnameSessionProvider(
                                              widget.sessionId,
                                            ).notifier,
                                          )
                                          .submitForValidation();
                                      if (!context.mounted) return;
                                      if (ok) {
                                        ScaffoldMessenger.of(context)
                                            .showSnackBar(
                                          const SnackBar(
                                            content: Text(
                                              'Dikirim untuk validasi',
                                            ),
                                            backgroundColor:
                                                AppColors.success,
                                          ),
                                        );
                                      }
                                    }
                                  : null,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.voidBlack,
                                disabledBackgroundColor:
                                    AppColors.voidBlack.withValues(alpha: 0.35),
                              ),
                              child: state.isSubmitting
                                  ? const SizedBox(
                                      width: 22,
                                      height: 22,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: Colors.white,
                                      ),
                                    )
                                  : Text(
                                      'SUBMIT UNTUK VALIDASI',
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

// ─── Progress Card ──────────────────────────────────────────────────────────

class _ProgressCard extends StatelessWidget {
  final int scanned;
  final int total;
  final double progress;

  const _ProgressCard({
    required this.scanned,
    required this.total,
    required this.progress,
  });

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
        children: [
          Row(
            children: [
              Text('PROGRESS', style: AppTextStyles.sectionLabel),
              const Spacer(),
              Text(
                '$scanned / $total',
                style: AppTextStyles.monoBold.copyWith(fontSize: 13),
              ),
            ],
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(3),
            child: LinearProgressIndicator(
              value: total > 0 ? progress : null,
              minHeight: 6,
              color: AppColors.voidBlack,
              backgroundColor: AppColors.border,
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Item Row ────────────────────────────────────────────────────────────────

class _OpnameItemRow extends StatelessWidget {
  final Map<String, dynamic> data;

  const _OpnameItemRow({required this.data});

  @override
  Widget build(BuildContext context) {
    final item = data['item'] is Map
        ? Map<String, dynamic>.from(data['item'] as Map)
        : <String, dynamic>{};
    final name = item['item_name']?.toString() ?? '—';
    final system = (data['system_available_qty'] as num?)?.toInt() ?? 0;
    final counted = (data['physical_available_qty'] as num?)?.toInt() ?? 0;
    final diff = (data['available_difference_qty'] as num?)?.toInt() ?? 0;

    Color diffColor = AppColors.success;
    String diffText = '0';
    if (diff > 0) {
      diffText = '+$diff';
    } else if (diff < 0) {
      diffText = '$diff';
      diffColor = AppColors.danger;
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: GoogleFonts.inter(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppColors.voidBlack,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(
                  'sistem ${FormatUtils.formatQty(system)} · fisik ${FormatUtils.formatQty(counted)}',
                  style: AppTextStyles.monoMuted,
                ),
              ],
            ),
          ),
          Text(
            diffText,
            style: AppTextStyles.monoBold.copyWith(
              fontSize: 14,
              color: diffColor,
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Summary Row ─────────────────────────────────────────────────────────────

class _SummaryRow extends StatelessWidget {
  final int match;
  final int lost;
  final int damaged;

  const _SummaryRow({
    required this.match,
    required this.lost,
    required this.damaged,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(child: _StatCard(label: 'MATCH', value: '$match')),
        const SizedBox(width: 8),
        Expanded(
          child: _StatCard(
            label: 'LOST',
            value: '$lost',
            valueColor: AppColors.danger,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _StatCard(
            label: 'DAMAGED',
            value: '$damaged',
            valueColor: AppColors.warning,
          ),
        ),
      ],
    );
  }
}

// ─── Scanner Page ─────────────────────────────────────────────────────────────

class _OpnameScannerPage extends ConsumerStatefulWidget {
  const _OpnameScannerPage();

  @override
  ConsumerState<_OpnameScannerPage> createState() => _OpnameScannerPageState();
}

class _OpnameScannerPageState extends ConsumerState<_OpnameScannerPage> {
  MobileScannerController? _controller;
  bool _isProcessing = false;

  @override
  void initState() {
    super.initState();
    if (!kIsWeb) {
      _controller = MobileScannerController(
        detectionSpeed: DetectionSpeed.noDuplicates,
        facing: CameraFacing.back,
        formats: const [BarcodeFormat.qrCode],
      );
    }
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
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

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_isProcessing) return;

    final barcode = _extractBarcode(capture);
    if (barcode == null) return;

    final apiClient = ref.read(apiClientProvider);
    final token = await apiClient.getToken();
    if (token == null || token.isEmpty) {
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
    await _controller?.stop();

    try {
      final item = await ref
          .read(scanServiceProvider)
          .findByBarcode(barcode, apiClient.dio);

      if (!mounted) return;

      if (item == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Barcode tidak ditemukan: $barcode'),
            backgroundColor: AppColors.danger,
          ),
        );
        setState(() => _isProcessing = false);
        await _controller?.start();
        return;
      }

      Navigator.of(context).pop(item);
    } on DioException catch (e) {
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

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content:
              Text('Gagal memuat data: ${e.message ?? e.toString()}'),
          backgroundColor: AppColors.danger,
        ),
      );
      setState(() => _isProcessing = false);
      await _controller?.start();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal memuat data: $e'),
          backgroundColor: AppColors.danger,
        ),
      );
      setState(() => _isProcessing = false);
      await _controller?.start();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: const Text(
          'Scan Item Opname',
          style: TextStyle(color: Colors.white, fontSize: 16),
        ),
        leading: IconButton(
          icon: const Icon(Icons.close, color: Colors.white),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: _controller == null
          ? const Center(
              child: Text(
                'Kamera tidak tersedia',
                style: TextStyle(color: Colors.white),
              ),
            )
          : MobileScanner(
              controller: _controller,
              onDetect: _onDetect,
            ),
    );
  }
}

// ─── Stat Card ───────────────────────────────────────────────────────────────

class _StatCard extends StatelessWidget {
  final String label;
  final String value;
  final Color? valueColor;

  const _StatCard({
    required this.label,
    required this.value,
    this.valueColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        children: [
          Text(
            label,
            style: AppTextStyles.tabLabel.copyWith(
              color: AppColors.muted,
              fontSize: 9,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            value,
            style: AppTextStyles.monoBold.copyWith(
              fontSize: 18,
              color: valueColor ?? AppColors.voidBlack,
            ),
          ),
        ],
      ),
    );
  }
}

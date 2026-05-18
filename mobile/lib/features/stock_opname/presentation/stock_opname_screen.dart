import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/utils/location_helpers.dart';
import '../../../core/widgets/screen_header.dart';
import 'stock_opname_provider.dart';

class StockOpnameScreen extends ConsumerStatefulWidget {
  const StockOpnameScreen({super.key});

  @override
  ConsumerState<StockOpnameScreen> createState() => _StockOpnameScreenState();
}

class _StockOpnameScreenState extends ConsumerState<StockOpnameScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(stockOpnameProvider.notifier).load());
  }

  String _sessionLabel(Map<String, dynamic>? session) {
    final number = session?['opname_number']?.toString() ?? '';
    if (number.isEmpty) return '042';
    final parts = number.split('-');
    return parts.isNotEmpty ? parts.last : number;
  }

  Future<void> _scanItem() async {
    final catalog = await context.push<Map<String, dynamic>>(
      '/scan?mode=select',
    );
    if (catalog != null && mounted) {
      await ref.read(stockOpnameProvider.notifier).addScannedItem(catalog);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    final state = ref.watch(stockOpnameProvider);
    final items = state.items;
    final scanned = items.length;
    final total = scanned == 0 ? 1 : scanned;
    final progress = (scanned / total).clamp(0.0, 1.0);

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

    final hasSession = state.session != null;
    final isPending = state.awaitingValidation ||
        state.session?['validation_status'] == 'pending_validation';

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
        child: state.isLoading && !hasSession
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
                            backLabel: 'AUDIT SESI #${_sessionLabel(state.session)}',
                            title: 'Stok Opname',
                          ),
                          const SizedBox(height: 20),
                          if (!hasSession) ...[
                            Text(
                              'Tidak ada sesi aktif. Mulai sesi baru untuk lokasi Anda.',
                              style: AppTextStyles.cardSubtitle.copyWith(
                                fontSize: 13,
                              ),
                            ),
                            const SizedBox(height: 20),
                            SizedBox(
                              height: 52,
                              child: ElevatedButton(
                                onPressed: () async {
                                  final locId =
                                      assignedLocationId(auth.user);
                                  if (locId == null) {
                                    ScaffoldMessenger.of(context)
                                        .showSnackBar(
                                      const SnackBar(
                                        content: Text(
                                          'Lokasi belum tersedia. Login dengan akun yang valid.',
                                        ),
                                      ),
                                    );
                                    return;
                                  }
                                  await ref
                                      .read(stockOpnameProvider.notifier)
                                      .startSession(locId);
                                },
                                child: Text(
                                  'MULAI SESI BARU',
                                  style: AppTextStyles.buttonPrimary,
                                ),
                              ),
                            ),
                          ] else ...[
                            _ProgressCard(
                              scanned: scanned,
                              total: total,
                              progress: progress,
                            ),
                            const SizedBox(height: 16),
                            if (isPending)
                              Container(
                                padding: const EdgeInsets.all(12),
                                margin: const EdgeInsets.only(bottom: 12),
                                decoration: BoxDecoration(
                                  color: AppColors.card,
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: AppColors.border),
                                ),
                                child: Text(
                                  'Menunggu validasi dari Owner/Admin.',
                                  style: AppTextStyles.cardSubtitle.copyWith(
                                    fontSize: 12,
                                  ),
                                ),
                              ),
                            ...items.map((row) => _OpnameItemRow(data: row)),
                            const SizedBox(height: 12),
                            _SummaryRow(
                              match: match,
                              lost: lost,
                              damaged: damaged,
                            ),
                            const SizedBox(height: 16),
                            if (!isPending)
                              OutlinedButton.icon(
                                onPressed: _scanItem,
                                icon: const Icon(Icons.qr_code_scanner, size: 18),
                                label: const Text('+ SCAN ITEM'),
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: AppColors.voidBlack,
                                  side: const BorderSide(color: AppColors.border),
                                  minimumSize: const Size(double.infinity, 48),
                                ),
                              ),
                          ],
                          if (state.errorMessage != null) ...[
                            const SizedBox(height: 12),
                            Text(
                              state.errorMessage!,
                              style: const TextStyle(color: AppColors.danger),
                            ),
                          ],
                          const SizedBox(height: 80),
                        ],
                      ),
                    ),
                  ),
                  if (hasSession && !isPending)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                      child: SizedBox(
                        height: 52,
                        child: ElevatedButton(
                          onPressed: items.isEmpty || state.isSubmitting
                              ? null
                              : () async {
                                  final ok = await ref
                                      .read(stockOpnameProvider.notifier)
                                      .submitForValidation();
                                  if (!context.mounted) return;
                                  if (ok) {
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      const SnackBar(
                                        content: Text(
                                          'Dikirim untuk validasi',
                                        ),
                                        backgroundColor: AppColors.success,
                                      ),
                                    );
                                  }
                                },
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
                    ),
                ],
              ),
      ),
    );
  }
}

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
              value: progress,
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
      diffColor = AppColors.success;
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
                  'system ${AppColors.formatQty(system)} · counted ${AppColors.formatQty(counted)}',
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
          Text(label, style: AppTextStyles.tabLabel.copyWith(
            color: AppColors.muted,
            fontSize: 9,
          )),
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

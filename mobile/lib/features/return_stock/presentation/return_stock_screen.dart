import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/utils/location_helpers.dart';
import '../../../core/widgets/screen_header.dart';
import 'return_stock_provider.dart';

class ReturnStockScreen extends ConsumerStatefulWidget {
  const ReturnStockScreen({super.key});

  @override
  ConsumerState<ReturnStockScreen> createState() => _ReturnStockScreenState();
}

class _ReturnStockScreenState extends ConsumerState<ReturnStockScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      ref
          .read(returnStockProvider.notifier)
          .loadWarehouse(ref.read(apiClientProvider).dio);
    });
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    final state = ref.watch(returnStockProvider);
    final fromName = assignedLocationName(auth.user, fallback: 'Bazar Senayan');
    const warehouseName = 'Gudang Pusat';

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
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const ScreenHeader(
                      backLabel: 'BAZAR → GUDANG',
                      title: 'Return Sisa',
                    ),
                    const SizedBox(height: 20),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: AppColors.card,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppColors.border),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('ASAL', style: AppTextStyles.sectionLabel),
                          const SizedBox(height: 8),
                          Text(
                            fromName,
                            style: GoogleFonts.inter(
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                              color: AppColors.voidBlack,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '→ $warehouseName',
                            style: GoogleFonts.inter(
                              fontSize: 12,
                              color: AppColors.muted,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    if (state.items.isEmpty)
                      Text(
                        'Belum ada item return. Scan untuk menambah.',
                        style: AppTextStyles.cardSubtitle.copyWith(fontSize: 13),
                      )
                    else
                      ...state.items.map(
                        (item) => _ReturnItemRow(
                          item: item,
                          onDelta: (d) => ref
                              .read(returnStockProvider.notifier)
                              .updateQty(item.itemId, d),
                        ),
                      ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: () async {
                        final catalog = await context.push<Map<String, dynamic>>(
                          '/scan?mode=select',
                        );
                        if (catalog != null) {
                          ref
                              .read(returnStockProvider.notifier)
                              .addFromCatalog(catalog);
                        }
                      },
                      icon: const Icon(Icons.qr_code_scanner, size: 18),
                      label: const Text('+ SCAN ITEM'),
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size(double.infinity, 48),
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
                  onPressed: state.items.isEmpty || state.isSubmitting
                      ? null
                      : () async {
                          final fromId = assignedLocationId(auth.user);
                          if (fromId == null) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text('Lokasi asal belum tersedia'),
                              ),
                            );
                            return;
                          }
                          final ok = await ref
                              .read(returnStockProvider.notifier)
                              .submit(
                                dio: ref.read(apiClientProvider).dio,
                                fromLocationId: fromId,
                              );
                          if (!context.mounted) return;
                          if (ok) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: const Text(
                                  'Surat return berhasil dibuat',
                                ),
                                action: SnackBarAction(
                                  label: 'OK',
                                  onPressed: () {},
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
                          'GENERATE SURAT RETURN',
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

class _ReturnItemRow extends StatelessWidget {
  final ReturnLineItem item;
  final ValueChanged<int> onDelta;

  const _ReturnItemRow({required this.item, required this.onDelta});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.keyboard_return, size: 16, color: AppColors.muted),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.itemName,
                  style: GoogleFonts.inter(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                Text(
                  '${item.sku} · sold ${item.soldQty}',
                  style: AppTextStyles.monoMuted,
                ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  _miniBtn(Icons.remove, () => onDelta(-1)),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 8),
                    child: Text(
                      '${item.returnQty}',
                      style: AppTextStyles.monoBold.copyWith(fontSize: 18),
                    ),
                  ),
                  _miniBtn(Icons.add, () => onDelta(1)),
                ],
              ),
              const SizedBox(height: 4),
              Text('RETURN', style: AppTextStyles.tabLabel.copyWith(
                color: AppColors.muted,
                fontSize: 9,
              )),
            ],
          ),
        ],
      ),
    );
  }

  Widget _miniBtn(IconData icon, VoidCallback onTap) {
    return Material(
      color: AppColors.background,
      borderRadius: BorderRadius.circular(6),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(6),
        child: SizedBox(
          width: 28,
          height: 28,
          child: Icon(icon, size: 16, color: AppColors.muted),
        ),
      ),
    );
  }
}

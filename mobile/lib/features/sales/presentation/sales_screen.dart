import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/screen_header.dart';
import 'location_selector.dart';
import 'sales_provider.dart';
import 'widgets/cart_item_row.dart' show CartItemRow, formatSalesPrice;

class SalesScreen extends ConsumerWidget {
  const SalesScreen({super.key});

  String _locationHeaderLabel(WidgetRef ref) {
    final name = resolveLocationName(ref);
    if (name == 'Belum dipilih') return 'LOKASI';
    return name.trim().toUpperCase();
  }

  Future<void> _checkout(BuildContext context, WidgetRef ref) async {
    var locationId = resolveLocationId(ref);

    if (locationId == null || locationId.isEmpty) {
      await showLocationSelector(context, ref);
      locationId = resolveLocationId(ref);
      if (locationId == null || !context.mounted) return;
    }

    final success = await ref.read(salesCartProvider.notifier).checkout(
          ref.read(apiClientProvider).dio,
          locationId: locationId,
        );

    if (!context.mounted) return;

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Transaksi berhasil'),
          backgroundColor: AppColors.success,
        ),
      );
    } else {
      final error = ref.read(salesCartProvider).errorMessage;
      if (error != null && isInsufficientStockError(error)) {
        await showInsufficientStockSheet(context, ref);
      } else if (error != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(error),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cart = ref.watch(salesCartProvider);
    final notifier = ref.read(salesCartProvider.notifier);
    final subtotal = notifier.subtotal;
    final grandTotal = notifier.grandTotal;
    final isEmpty = cart.items.isEmpty;
    final locationLabel = _locationHeaderLabel(ref);

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: Row(
                children: [
                  Expanded(
                    child: ScreenHeader(
                      backLabel: locationLabel,
                      title: 'Transaksi Jual',
                    ),
                  ),
                  TextButton.icon(
                    onPressed: () => showLocationSelector(context, ref),
                    icon: const Icon(Icons.swap_horiz, size: 16),
                    label: Text(
                      'Ganti',
                      style: AppTextStyles.cardSubtitle.copyWith(
                        fontSize: 12,
                        color: AppColors.voidBlack,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Expanded(
              child: isEmpty
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Text(
                          'Belum ada item. Scan dari tab SCAN.',
                          textAlign: TextAlign.center,
                          style: AppTextStyles.cardSubtitle.copyWith(
                            fontSize: 13,
                          ),
                        ),
                      ),
                    )
                  : ListView.builder(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      itemCount: cart.items.length,
                      itemBuilder: (context, index) {
                        final item = cart.items[index];
                        return CartItemRow(item: item);
                      },
                    ),
            ),
            _SalesSummaryCard(
              subtotal: subtotal,
              discount: cart.bazarDiscount,
              grandTotal: grandTotal,
              paymentMethod: cart.paymentMethod,
              isLoading: cart.isLoading,
              isEmpty: isEmpty,
              onPaymentChanged: notifier.setPaymentMethod,
              onCheckout: () => _checkout(context, ref),
            ),
          ],
        ),
      ),
    );
  }
}

class _SalesSummaryCard extends StatelessWidget {
  final double subtotal;
  final double discount;
  final double grandTotal;
  final String paymentMethod;
  final bool isLoading;
  final bool isEmpty;
  final ValueChanged<String> onPaymentChanged;
  final VoidCallback onCheckout;

  const _SalesSummaryCard({
    required this.subtotal,
    required this.discount,
    required this.grandTotal,
    required this.paymentMethod,
    required this.isLoading,
    required this.isEmpty,
    required this.onPaymentChanged,
    required this.onCheckout,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 8, 16, 16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.voidBlack,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _summaryRow('SUBTOTAL', formatSalesPrice(subtotal), muted: true),
          if (discount > 0) ...[
            const SizedBox(height: 8),
            _summaryRow(
              'DISKON BAZAR',
              '-${formatSalesPrice(discount)}',
              valueColor: AppColors.warning,
              muted: true,
            ),
          ],
          const SizedBox(height: 12),
          Divider(color: Colors.white.withValues(alpha: 0.2), height: 1),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                'TOTAL',
                style: GoogleFonts.inter(
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1.5,
                  color: Colors.white.withValues(alpha: 0.6),
                ),
              ),
              const Spacer(),
              Text(
                formatSalesPrice(grandTotal),
                style: AppTextStyles.monoLarge.copyWith(fontSize: 28),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              _PaymentChip(
                label: 'TUNAI',
                value: 'cash',
                groupValue: paymentMethod,
                onSelected: onPaymentChanged,
              ),
              const SizedBox(width: 8),
              _PaymentChip(
                label: 'QRIS',
                value: 'qris',
                groupValue: paymentMethod,
                onSelected: onPaymentChanged,
              ),
              const SizedBox(width: 8),
              _PaymentChip(
                label: 'TRANSFER',
                value: 'transfer',
                groupValue: paymentMethod,
                onSelected: onPaymentChanged,
              ),
            ],
          ),
          const SizedBox(height: 14),
          SizedBox(
            height: 44,
            child: ElevatedButton(
              onPressed: isEmpty || isLoading ? null : onCheckout,
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.white,
                foregroundColor: AppColors.voidBlack,
                disabledBackgroundColor: Colors.white.withValues(alpha: 0.35),
                disabledForegroundColor:
                    AppColors.voidBlack.withValues(alpha: 0.4),
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
                textStyle: GoogleFonts.inter(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1.0,
                ),
              ),
              child: isLoading
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: AppColors.voidBlack,
                      ),
                    )
                  : const Text('BAYAR · CETAK STRUK'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _summaryRow(
    String label,
    String value, {
    bool muted = false,
    Color? valueColor,
  }) {
    final labelStyle = GoogleFonts.inter(
      fontSize: 10,
      fontWeight: FontWeight.w700,
      letterSpacing: 1.5,
      color: Colors.white.withValues(alpha: 0.6),
    );
    final valueStyle = AppTextStyles.mono.copyWith(
      fontSize: 12,
      fontWeight: FontWeight.w400,
      color: valueColor ?? Colors.white.withValues(alpha: muted ? 0.6 : 1),
    );

    return Row(
      children: [
        Text(label, style: labelStyle),
        const Spacer(),
        Text(value, style: valueStyle),
      ],
    );
  }
}

class _PaymentChip extends StatelessWidget {
  final String label;
  final String value;
  final String groupValue;
  final ValueChanged<String> onSelected;

  const _PaymentChip({
    required this.label,
    required this.value,
    required this.groupValue,
    required this.onSelected,
  });

  @override
  Widget build(BuildContext context) {
    final active = value == groupValue;
    return Expanded(
      child: Material(
        color: active
            ? Colors.white
            : Colors.white.withValues(alpha: 0.2),
        borderRadius: BorderRadius.circular(8),
        child: InkWell(
          onTap: () => onSelected(value),
          borderRadius: BorderRadius.circular(8),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 10),
            child: Text(
              label,
              textAlign: TextAlign.center,
              style: GoogleFonts.inter(
                fontSize: 9,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.5,
                color: active
                    ? AppColors.voidBlack
                    : Colors.white.withValues(alpha: 0.6),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

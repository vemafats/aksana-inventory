import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_text_styles.dart';
import '../sales_provider.dart';

String formatSalesPrice(double amount) {
  if (amount >= 1000000) {
    final m = amount / 1000000;
    final text =
        m == m.roundToDouble() ? m.toInt().toString() : m.toStringAsFixed(1);
    return 'Rp ${text}M';
  }
  if (amount >= 1000) {
    final k = amount / 1000;
    final text =
        k == k.roundToDouble() ? k.toInt().toString() : k.toStringAsFixed(1);
    return 'Rp ${text}k';
  }
  return 'Rp ${amount.toInt()}';
}

class CartItemRow extends StatelessWidget {
  final CartItem item;
  final VoidCallback onDelete;

  const CartItemRow({
    super.key,
    required this.item,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onLongPress: () async {
        final confirmed = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: Text(
              'Hapus item?',
              style: AppTextStyles.cardTitle.copyWith(fontSize: 16),
            ),
            content: Text(
              item.itemName,
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
                  minimumSize: const Size(72, 40),
                ),
                child: const Text('HAPUS'),
              ),
            ],
          ),
        );
        if (confirmed == true) onDelete();
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.border),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.itemName,
                    style: GoogleFonts.inter(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AppColors.voidBlack,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'qty ${AppColors.formatQty(item.qty)}',
                    style: AppTextStyles.monoMuted,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            Text(
              formatSalesPrice(item.lineTotal),
              style: AppTextStyles.monoBold.copyWith(fontSize: 14),
            ),
          ],
        ),
      ),
    );
  }
}

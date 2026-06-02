import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_text_styles.dart';
import '../../../../core/utils/format_utils.dart';
import '../sales_provider.dart';

class CartItemRow extends ConsumerWidget {
  final CartItem item;

  const CartItemRow({super.key, required this.item});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
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
        if (confirmed == true) {
          ref.read(salesCartProvider.notifier).removeItem(item.itemId);
        }
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
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(item.itemName, style: AppTextStyles.cardTitle),
                  Text(
                    'qty ${FormatUtils.formatQty(item.qty)}',
                    style: AppTextStyles.monoMuted,
                  ),
                ],
              ),
            ),
            Row(
              children: [
                GestureDetector(
                  onTap: () => ref
                      .read(salesCartProvider.notifier)
                      .updateQty(item.itemId, -1),
                  child: Container(
                    width: 28,
                    height: 28,
                    decoration: BoxDecoration(
                      color: AppColors.border,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: const Icon(Icons.remove, size: 14),
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  item.qty.toString(),
                  style: AppTextStyles.monoBold,
                ),
                const SizedBox(width: 8),
                GestureDetector(
                  onTap: () => ref
                      .read(salesCartProvider.notifier)
                      .updateQty(item.itemId, 1),
                  child: Container(
                    width: 28,
                    height: 28,
                    decoration: BoxDecoration(
                      color: AppColors.voidBlack,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: const Icon(
                      Icons.add,
                      size: 14,
                      color: Colors.white,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Text(
                  FormatUtils.formatPriceFull(item.bazarSellingPrice * item.qty),
                  style: AppTextStyles.monoBold,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

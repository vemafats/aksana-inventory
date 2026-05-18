import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_text_styles.dart';
import '../stock_in_provider.dart';

class StockInItemRow extends StatelessWidget {
  final StockInItem item;
  final ValueChanged<int> onQtyDelta;

  const StockInItemRow({
    super.key,
    required this.item,
    required this.onQtyDelta,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: AppColors.background,
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Icon(
              Icons.inventory_2_outlined,
              size: 18,
              color: AppColors.muted,
            ),
          ),
          const SizedBox(width: 12),
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
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(item.sku, style: AppTextStyles.monoMuted),
              ],
            ),
          ),
          const SizedBox(width: 8),
          _StepperButton(
            icon: Icons.remove,
            background: AppColors.background,
            iconColor: AppColors.muted,
            onTap: () => onQtyDelta(-1),
          ),
          SizedBox(
            width: 32,
            child: Text(
              '${item.qty}',
              textAlign: TextAlign.center,
              style: AppTextStyles.monoBold.copyWith(fontSize: 16),
            ),
          ),
          _StepperButton(
            icon: Icons.add,
            background: AppColors.voidBlack,
            iconColor: Colors.white,
            onTap: () => onQtyDelta(1),
          ),
        ],
      ),
    );
  }
}

class _StepperButton extends StatelessWidget {
  final IconData icon;
  final Color background;
  final Color iconColor;
  final VoidCallback onTap;

  const _StepperButton({
    required this.icon,
    required this.background,
    required this.iconColor,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: background,
      borderRadius: BorderRadius.circular(8),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: SizedBox(
          width: 28,
          height: 28,
          child: Icon(icon, size: 16, color: iconColor),
        ),
      ),
    );
  }
}

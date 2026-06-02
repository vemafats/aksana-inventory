import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

class FormatUtils {
  FormatUtils._();

  static String formatPrice(dynamic price) {
    if (price == null) return 'Rp 0';
    final num p = num.tryParse(price.toString()) ?? 0;
    if (p >= 1000000000) {
      return 'Rp ${_trimTrailingZeros((p / 1000000000).toStringAsFixed(2))}M';
    }
    if (p >= 1000000) {
      return 'Rp ${_trimTrailingZeros((p / 1000000).toStringAsFixed(2))}JT';
    }
    if (p >= 1000) {
      return 'Rp ${_trimTrailingZeros((p / 1000).toStringAsFixed(2))}RB';
    }
    return 'Rp ${p.toStringAsFixed(0)}';
  }

  static String _trimTrailingZeros(String value) {
    if (!value.contains('.')) return value;
    return value.replaceFirst(RegExp(r'\.?0+$'), '');
  }

  static String formatQty(int qty) => qty.toString().padLeft(2, '0');

  static Color qtyColor(int qty) {
    if (qty == 0) return AppColors.danger;
    if (qty == 1) return AppColors.warning;
    return AppColors.voidBlack;
  }
}

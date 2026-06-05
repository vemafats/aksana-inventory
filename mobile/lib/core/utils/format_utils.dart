import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../theme/app_colors.dart';

class FormatUtils {
  FormatUtils._();

  static final NumberFormat _idrFormatter = NumberFormat.decimalPattern('id_ID');

  static String formatPrice(dynamic price) {
    if (price == null) return 'Rp 0';
    final num n = num.tryParse(price.toString()) ?? 0;
    return 'Rp ${_idrFormatter.format(n.round())}';
  }

  static String formatQty(int qty) => qty.toString();

  static Color qtyColor(int qty) {
    if (qty == 0) return AppColors.danger;
    if (qty == 1) return AppColors.warning;
    return AppColors.voidBlack;
  }
}

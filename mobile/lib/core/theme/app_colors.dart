import 'package:flutter/material.dart';

class AppColors {
  AppColors._();

  static const Color background  = Color(0xFFEDF1F3);
  static const Color card        = Color(0xFFFFFFFF);
  static const Color voidBlack   = Color(0xFF070D1E);
  static const Color border      = Color(0xFFD1DAE5);
  static const Color muted       = Color(0xFF49586B);
  static const Color success     = Color(0xFF29A85A);
  static const Color warning     = Color(0xFFF59100);
  static const Color danger      = Color(0xFFF04040);
  static const Color accent      = Color(0xFF1660ED);
  static const Color cameraBg    = Color(0xFF0A0F1E);
  static const Color scanLine    = Color(0xFF29A85A);
  static const Color tabInactive = Color(0xFFB0B8C4);

  static Color qtyColor(int qty) {
    if (qty == 0) return danger;
    if (qty == 1) return warning;
    return voidBlack;
  }

  static String formatQty(int qty) =>
      qty.toString().padLeft(2, '0');
}

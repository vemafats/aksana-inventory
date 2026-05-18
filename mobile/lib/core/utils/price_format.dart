String formatRupiahCompact(double amount) {
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

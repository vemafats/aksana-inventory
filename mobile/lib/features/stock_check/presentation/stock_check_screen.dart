import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/screen_header.dart';

class StockCheckScreen extends StatelessWidget {
  final Map<String, dynamic>? itemData;

  const StockCheckScreen({super.key, this.itemData});

  @override
  Widget build(BuildContext context) {
    final item = itemData ?? {};
    final name = item['item_name']?.toString() ?? '—';
    final sku = item['sku']?.toString() ?? '—';
    final category = item['category'] is Map
        ? (item['category'] as Map)['name']?.toString() ?? '—'
        : item['category_name']?.toString() ?? '—';
    final color = item['color'] is Map ? item['color'] as Map : null;
    final hex = color?['hex_code']?.toString() ?? '#49586B';
    final abbr = _abbreviation(name);

    final locations = _parseLocations(item);
    final total = locations.fold<int>(0, (s, l) => s + l.qty);

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
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const ScreenHeader(
                      backLabel: 'MULTI-LOKASI',
                      title: 'Cek Stok',
                    ),
                    const SizedBox(height: 20),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: AppColors.card,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppColors.border),
                      ),
                      child: Row(
                        children: [
                          Container(
                            width: 42,
                            height: 42,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: _parseColor(hex),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              abbr,
                              style: GoogleFonts.inter(
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                                color: Colors.white,
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  name,
                                  style: GoogleFonts.inter(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.voidBlack,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  '$sku · $category',
                                  style: AppTextStyles.monoMuted,
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text('DISTRIBUSI', style: AppTextStyles.sectionLabel),
                    const SizedBox(height: 10),
                    if (locations.isEmpty)
                      Text(
                        'Tidak ada data distribusi.',
                        style: AppTextStyles.cardSubtitle,
                      )
                    else
                      ...locations.map(
                        (loc) => _LocationRow(
                          name: loc.name,
                          qty: loc.qty,
                        ),
                      ),
                    const SizedBox(height: 72),
                  ],
                ),
              ),
            ),
            Container(
              height: 56,
              margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              padding: const EdgeInsets.symmetric(horizontal: 16),
              decoration: BoxDecoration(
                color: AppColors.voidBlack,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  Text(
                    'TOTAL STOCK',
                    style: GoogleFonts.inter(
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1.5,
                      color: Colors.white.withValues(alpha: 0.6),
                    ),
                  ),
                  const Spacer(),
                  Text(
                    AppColors.formatQty(total),
                    style: AppTextStyles.monoBold.copyWith(
                      fontSize: 24,
                      color: Colors.white,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _abbreviation(String name) {
    final parts = name.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty) return '—';
    if (parts.length == 1) {
      return parts.first.substring(0, parts.first.length.clamp(0, 2)).toUpperCase();
    }
    return '${parts.first[0]}${parts[1][0]}'.toUpperCase();
  }

  Color _parseColor(String hex) {
    var value = hex.replaceFirst('#', '');
    if (value.length == 6) value = 'FF$value';
    final intVal = int.tryParse(value, radix: 16);
    if (intVal == null) return AppColors.muted;
    return Color(intVal);
  }

  List<_LocQty> _parseLocations(Map<String, dynamic> item) {
    final summary = item['stock_summary'];
    if (summary is! Map) return [];
    final perLoc = summary['per_location'];
    if (perLoc is! List) return [];
    return perLoc
        .whereType<Map>()
        .map((m) => _LocQty(
              name: m['location_name']?.toString() ?? '—',
              qty: (m['available'] as num?)?.toInt() ?? 0,
            ))
        .toList();
  }
}

class _LocQty {
  final String name;
  final int qty;
  _LocQty({required this.name, required this.qty});
}

class _LocationRow extends StatelessWidget {
  final String name;
  final int qty;

  const _LocationRow({required this.name, required this.qty});

  @override
  Widget build(BuildContext context) {
    return Container(
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
            child: Text(
              name,
              style: GoogleFonts.inter(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: AppColors.voidBlack,
              ),
            ),
          ),
          Text(
            AppColors.formatQty(qty),
            style: AppTextStyles.monoBold.copyWith(
              fontSize: 14,
              color: AppColors.qtyColor(qty),
            ),
          ),
        ],
      ),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/utils/price_format.dart';
import '../../../core/widgets/screen_header.dart';
import 'reports_provider.dart';

class ReportsScreen extends ConsumerStatefulWidget {
  const ReportsScreen({super.key});

  @override
  ConsumerState<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends ConsumerState<ReportsScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(reportsProvider.notifier).load());
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(reportsProvider);

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: state.isLoading
            ? _LoadingSkeleton()
            : RefreshIndicator(
                onRefresh: () => ref.read(reportsProvider.notifier).load(),
                child: SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const ScreenHeader(
                        backLabel: 'HARI INI',
                        title: 'Laporan Ringkas',
                      ),
                      const SizedBox(height: 20),
                      if (state.isEmpty)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 48),
                          child: Text(
                            state.errorMessage ?? 'Tidak ada data',
                            textAlign: TextAlign.center,
                            style: AppTextStyles.cardSubtitle.copyWith(
                              fontSize: 13,
                            ),
                          ),
                        )
                      else
                        _ReportBody(data: state.data!),
                    ],
                  ),
                ),
              ),
      ),
    );
  }
}

class _ReportBody extends StatelessWidget {
  final Map<String, dynamic> data;

  const _ReportBody({required this.data});

  @override
  Widget build(BuildContext context) {
    final netSales = (data['net_sales'] as num?)?.toDouble() ?? 0;
    final changePct = (data['net_sales_change_pct'] as num?)?.toDouble() ?? 0;
    final itemsSold = (data['items_sold'] as num?)?.toInt() ?? 0;
    final avgBasket = (data['avg_basket'] as num?)?.toDouble() ?? 0;
    final trend = (data['seven_day_trend'] as List?)
            ?.map((e) => (e as num).toDouble())
            .toList() ??
        List<double>.filled(7, 0);
    final topSku = data['top_sku'] is Map
        ? Map<String, dynamic>.from(data['top_sku'] as Map)
        : null;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: AppColors.voidBlack,
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'NET SALES',
                style: GoogleFonts.inter(
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1.5,
                  color: Colors.white.withValues(alpha: 0.6),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                formatRupiahCompact(netSales),
                style: AppTextStyles.monoLarge.copyWith(fontSize: 36),
              ),
              const SizedBox(height: 8),
              Text(
                '▲ +$changePct% vs kemarin',
                style: GoogleFonts.inter(
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                  color: AppColors.success,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _MiniStatCard(
                label: 'ITEMS SOLD',
                value: '$itemsSold',
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _MiniStatCard(
                label: 'AVG BASKET',
                value: formatRupiahCompact(avgBasket),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.card,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            children: [
              Row(
                children: [
                  Text('7-DAY TREND', style: AppTextStyles.sectionLabel),
                  const Spacer(),
                  Text(
                    '▲ trending',
                    style: GoogleFonts.inter(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: AppColors.success,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              SizedBox(
                height: 100,
                child: CustomPaint(
                  painter: _BarChartPainter(values: trend),
                  size: const Size(double.infinity, 100),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.card,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              Text(
                'Top SKU',
                style: GoogleFonts.inter(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: AppColors.voidBlack,
                ),
              ),
              const Spacer(),
              Text(
                topSku == null
                    ? '—'
                    : '${topSku['sku']} · ${topSku['qty_sold']} sold',
                style: AppTextStyles.monoMuted,
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _MiniStatCard extends StatelessWidget {
  final String label;
  final String value;

  const _MiniStatCard({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: AppTextStyles.sectionLabel),
          const SizedBox(height: 8),
          Text(
            value,
            style: AppTextStyles.monoBold.copyWith(fontSize: 24),
          ),
        ],
      ),
    );
  }
}

class _BarChartPainter extends CustomPainter {
  final List<double> values;

  _BarChartPainter({required this.values});

  @override
  void paint(Canvas canvas, Size size) {
    if (values.isEmpty) return;
    final maxVal = values.reduce((a, b) => a > b ? a : b);
    final barCount = values.length;
    const gap = 6.0;
    final barWidth = (size.width - gap * (barCount - 1)) / barCount;

    for (var i = 0; i < barCount; i++) {
      final fraction = maxVal > 0 ? values[i] / maxVal : 0.0;
      final barHeight = (size.height - 4) * fraction;
      final x = i * (barWidth + gap);
      final isLatest = i == barCount - 1;
      final paint = Paint()
        ..color = isLatest
            ? AppColors.voidBlack
            : AppColors.border.withValues(
                alpha: 0.4 + (0.5 * i / barCount),
              );
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTWH(x, size.height - barHeight, barWidth, barHeight),
          const Radius.circular(3),
        ),
        paint,
      );
    }
  }

  @override
  bool shouldRepaint(covariant _BarChartPainter oldDelegate) =>
      oldDelegate.values != values;
}

class _LoadingSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _box(120),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: _box(80)),
              const SizedBox(width: 8),
              Expanded(child: _box(80)),
            ],
          ),
          const SizedBox(height: 12),
          _box(140),
        ],
      ),
    );
  }

  Widget _box(double height) => Container(
        height: height,
        decoration: BoxDecoration(
          color: AppColors.border.withValues(alpha: 0.5),
          borderRadius: BorderRadius.circular(12),
        ),
      );
}

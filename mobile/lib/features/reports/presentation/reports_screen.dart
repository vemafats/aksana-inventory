import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/auth/auth_provider.dart';
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

  void _showLogoutDialog(BuildContext context, WidgetRef ref) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Keluar?'),
        content: const Text('Anda akan keluar dari akun ini.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Batal'),
          ),
          TextButton(
            onPressed: () async {
              Navigator.pop(ctx);
              await ref.read(authProvider.notifier).logout();
            },
            child: Text(
              'Keluar',
              style: TextStyle(
                color: AppColors.danger,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _formatRole(String? role) {
    if (role == null || role.isEmpty) return '—';
    return role.replaceAll('_', ' ').toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(reportsProvider);
    final auth = ref.watch(authProvider);
    final userName = auth.name ?? 'User';
    final userRole = _formatRole(auth.role);
    final locationLabel = auth.locationName ?? 'Lokasi belum dipilih';
    final initial = userName.isNotEmpty ? userName[0].toUpperCase() : 'U';

    final content = <Widget>[
      _UserInfoCard(
        userName: userName,
        userRole: userRole,
        locationName: locationLabel,
        initial: initial,
        onLogout: () => _showLogoutDialog(context, ref),
      ),
      const SizedBox(height: 16),
      const ScreenHeader(
        backLabel: 'HARI INI',
        title: 'Laporan Ringkas',
      ),
      const SizedBox(height: 16),
      if (state.isLoading) ...[
        const _LoadingSkeleton(),
      ] else ...[
        if (state.showRetry && state.errorMessage != null)
          _ErrorRetryCard(
            message: state.errorMessage!,
            onRetry: () => ref.read(reportsProvider.notifier).load(),
          ),
        if (state.showRetry && state.errorMessage != null)
          const SizedBox(height: 12),
        _ReportContent(data: state.data),
      ],
    ];

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => ref.read(reportsProvider.notifier).load(),
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 100),
                sliver: SliverList(
                  delegate: SliverChildListDelegate(content),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _UserInfoCard extends StatelessWidget {
  final String userName;
  final String userRole;
  final String locationName;
  final String initial;
  final VoidCallback onLogout;

  const _UserInfoCard({
    required this.userName,
    required this.userRole,
    required this.locationName,
    required this.initial,
    required this.onLogout,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.voidBlack,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          CircleAvatar(
            backgroundColor: Colors.white.withValues(alpha: 0.24),
            radius: 20,
            child: Text(
              initial,
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.bold,
                fontSize: 16,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  userName,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w600,
                    fontSize: 14,
                  ),
                ),
                Text(
                  userRole,
                  style: AppTextStyles.monoMuted.copyWith(
                    color: Colors.white.withValues(alpha: 0.6),
                    fontSize: 11,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  locationName,
                  style: AppTextStyles.monoMuted.copyWith(
                    color: Colors.white.withValues(alpha: 0.45),
                    fontSize: 10,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          IconButton(
            icon: const Icon(
              Icons.logout,
              color: Colors.white70,
              size: 22,
            ),
            onPressed: onLogout,
            tooltip: 'Keluar',
            padding: const EdgeInsets.all(8),
            constraints: const BoxConstraints(
              minWidth: 40,
              minHeight: 40,
            ),
          ),
        ],
      ),
    );
  }
}

class _ErrorRetryCard extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorRetryCard({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.danger),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            message,
            style: AppTextStyles.cardSubtitle.copyWith(
              color: AppColors.danger,
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 40,
            child: OutlinedButton(
              onPressed: onRetry,
              child: const Text('COBA LAGI'),
            ),
          ),
        ],
      ),
    );
  }
}

class _ReportContent extends StatelessWidget {
  final MobileReportData data;

  const _ReportContent({required this.data});

  @override
  Widget build(BuildContext context) {
    final changePct = data.vsYesterdayPct;
    final isPositive = changePct >= 0;
    final changeColor = isPositive ? AppColors.success : AppColors.danger;
    final changePrefix = isPositive ? '▲ +' : '▼ ';
    final topSku = data.topSkuToday;
    final noSalesToday = data.todaysNetSales <= 0;

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
              if (noSalesToday) ...[
                Text(
                  'Rp 0',
                  style: AppTextStyles.monoLarge.copyWith(fontSize: 32),
                ),
                const SizedBox(height: 8),
                Text(
                  'Belum ada transaksi hari ini',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.38),
                    fontSize: 11,
                  ),
                ),
              ] else ...[
                Text(
                  formatRupiahCompact(data.todaysNetSales),
                  style: AppTextStyles.monoLarge.copyWith(fontSize: 32),
                ),
                const SizedBox(height: 8),
                Text(
                  '$changePrefix${changePct.abs()}% vs kemarin',
                  style: GoogleFonts.inter(
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    color: changeColor,
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _MiniStatCard(
                label: 'ITEMS SOLD',
                value: '${data.itemsSoldToday}',
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _MiniStatCard(
                label: 'AVG BASKET',
                value: formatRupiahCompact(data.avgBasketToday),
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
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Text('7-DAY TREND', style: AppTextStyles.sectionLabel),
                  const Spacer(),
                  Text(
                    isPositive ? '▲ trending' : '▼ trending',
                    style: GoogleFonts.inter(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: changeColor,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              SizedBox(
                height: 80,
                child: _BarChart(data: data.sevenDayTrend),
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
        if (data.lowStockCount > 0) ...[
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.warning.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: AppColors.warning.withValues(alpha: 0.5),
              ),
            ),
            child: Row(
              children: [
                const Icon(
                  Icons.warning_amber_rounded,
                  color: AppColors.warning,
                  size: 20,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    '${data.lowStockCount} item stok kritis',
                    style: GoogleFonts.inter(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AppColors.warning,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
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

class _BarChart extends StatelessWidget {
  final List<Map<String, dynamic>> data;

  const _BarChart({required this.data});

  @override
  Widget build(BuildContext context) {
    if (data.isEmpty) {
      return Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: List.generate(
          7,
          (i) => Expanded(
            child: Container(
              margin: const EdgeInsets.symmetric(horizontal: 2),
              height: 20 + (i * 5.0),
              decoration: BoxDecoration(
                color: i == 6
                    ? AppColors.voidBlack
                    : Colors.grey.withValues(alpha: 0.2 + i * 0.1),
                borderRadius: BorderRadius.circular(3),
              ),
            ),
          ),
        ),
      );
    }

    final maxVal = data
        .map((d) => (d['total_sales'] as num?)?.toDouble() ?? 0.0)
        .reduce((a, b) => a > b ? a : b);

    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: data.asMap().entries.map((entry) {
        final val = (entry.value['total_sales'] as num?)?.toDouble() ?? 0.0;
        final ratio = maxVal > 0 ? val / maxVal : 0.1;
        final isLatest = entry.key == data.length - 1;
        return Expanded(
          child: Container(
            margin: const EdgeInsets.symmetric(horizontal: 2),
            height: 40 * ratio + 10,
            decoration: BoxDecoration(
              color: isLatest
                  ? AppColors.voidBlack
                  : Colors.grey.withValues(alpha: 0.3 + ratio * 0.4),
              borderRadius: BorderRadius.circular(3),
            ),
          ),
        );
      }).toList(),
    );
  }
}

class _LoadingSkeleton extends StatelessWidget {
  const _LoadingSkeleton();

  @override
  Widget build(BuildContext context) {
    return Column(
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
        const SizedBox(height: 12),
        _box(56),
      ],
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

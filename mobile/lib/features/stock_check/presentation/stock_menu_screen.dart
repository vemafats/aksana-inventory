import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';

class StockMenuScreen extends ConsumerWidget {
  const StockMenuScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => ref.read(authProvider.notifier).fetchProfile(),
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'Stok',
                  style: AppTextStyles.screenTitle,
                ),
                const SizedBox(height: 20),
                _MenuTile(
                  icon: Icons.inventory_2_outlined,
                  label: 'Barang Masuk',
                  onTap: () => context.push('/stock/stock-in'),
                ),
                _MenuTile(
                  icon: Icons.view_list_outlined,
                  label: 'Browse Item',
                  onTap: () => context.push('/stock/browse'),
                ),
                _MenuTile(
                  icon: Icons.search,
                  label: 'Cek Stok',
                  onTap: () async {
                    final item = await context.push<Map<String, dynamic>>(
                      '/scan?mode=select',
                    );
                    if (item != null && context.mounted) {
                      context.push('/stock/check', extra: item);
                    }
                  },
                ),
                _MenuTile(
                  icon: Icons.keyboard_return,
                  label: 'Return Sisa',
                  onTap: () => context.push('/stock/return'),
                ),
                _MenuTile(
                  icon: Icons.fact_check_outlined,
                  label: 'Stok Opname',
                  onTap: () => context.push('/stock/stock-opname'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _MenuTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _MenuTile({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: ListTile(
        leading: Icon(icon, color: AppColors.voidBlack),
        title: Text(
          label,
          style: GoogleFonts.inter(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: AppColors.voidBlack,
          ),
        ),
        trailing: const Icon(
          Icons.chevron_right,
          color: AppColors.muted,
        ),
        onTap: onTap,
      ),
    );
  }
}

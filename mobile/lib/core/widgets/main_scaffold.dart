import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../theme/app_colors.dart';
import '../theme/app_text_styles.dart';

class MainScaffold extends StatelessWidget {
  final Widget child;
  const MainScaffold({super.key, required this.child});

  int _currentIndex(BuildContext context) {
    final loc = GoRouterState.of(context).matchedLocation;
    if (loc.startsWith('/scan'))    return 0;
    if (loc.startsWith('/sales'))  return 1;
    if (loc.startsWith('/stock'))  return 2;
    if (loc.startsWith('/reports')) return 3;
    if (loc.startsWith('/profile')) return 4;
    return 0;
  }

  @override
  Widget build(BuildContext context) {
    final idx = _currentIndex(context);
    return Scaffold(
      backgroundColor: AppColors.background,
      body: child,
      bottomNavigationBar: Container(
        decoration: const BoxDecoration(
          color: AppColors.card,
          border: Border(
            top: BorderSide(color: AppColors.border, width: 1),
          ),
        ),
        child: SafeArea(
          child: SizedBox(
            height: 56,
            child: Row(
              children: [
                _Tab(icon: Icons.qr_code_scanner_rounded,
                    label: 'SCAN',  index: 0, current: idx,
                    onTap: () => context.go('/scan')),
                _Tab(icon: Icons.shopping_bag_outlined,
                    label: 'JUAL',  index: 1, current: idx,
                    onTap: () => context.go('/sales')),
                _Tab(icon: Icons.inventory_2_outlined,
                    label: 'STOK',  index: 2, current: idx,
                    onTap: () => context.go('/stock')),
                _Tab(icon: Icons.show_chart_rounded,
                    label: 'STAT',  index: 3, current: idx,
                    onTap: () => context.go('/reports')),
                _Tab(icon: Icons.person_outline_rounded,
                    label: 'AKUN',  index: 4, current: idx,
                    onTap: () => context.go('/profile')),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Tab extends StatelessWidget {
  final IconData icon;
  final String label;
  final int index, current;
  final VoidCallback onTap;
  const _Tab({required this.icon, required this.label,
      required this.index, required this.current, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final active = index == current;
    final color = active ? AppColors.voidBlack : AppColors.tabInactive;
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        behavior: HitTestBehavior.opaque,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 22, color: color),
            const SizedBox(height: 3),
            Text(label,
                style: AppTextStyles.tabLabel.copyWith(color: color)),
          ],
        ),
      ),
    );
  }
}

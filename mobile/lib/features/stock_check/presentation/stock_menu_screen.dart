import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/utils/location_helpers.dart';

class StockMenuScreen extends ConsumerStatefulWidget {
  const StockMenuScreen({super.key});

  @override
  ConsumerState<StockMenuScreen> createState() => _StockMenuScreenState();
}

class _StockMenuScreenState extends ConsumerState<StockMenuScreen> {
  bool _closeConfirmStep = false;

  Future<void> _closeBazar() async {
    final locationId = assignedLocationId(ref.read(authProvider).user);
    if (locationId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Lokasi belum tersedia')),
      );
      return;
    }

    if (!_closeConfirmStep) {
      setState(() => _closeConfirmStep = true);
      final first = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Tutup bazar?'),
          content: const Text(
            'Semua stok di lokasi ini harus sudah 0. Lanjutkan?',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('BATAL'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('LANJUT'),
            ),
          ],
        ),
      );
      if (first != true || !mounted) {
        setState(() => _closeConfirmStep = false);
        return;
      }

      final second = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Konfirmasi tutup bazar'),
          content: const Text('Tap Tutup untuk menutup lokasi ini.'),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('BATAL'),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('TUTUP'),
            ),
          ],
        ),
      );
      setState(() => _closeConfirmStep = false);
      if (second != true) return;
    }

    try {
      await ref.read(apiClientProvider).dio.post(
            '/locations/$locationId/close',
          );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Bazar berhasil ditutup'),
            backgroundColor: AppColors.success,
          ),
        );
      }
    } on DioException catch (e) {
      if (!mounted) return;
      final msg = e.response?.data is Map
          ? e.response?.data['message']?.toString()
          : 'Gagal menutup bazar';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(msg ?? 'Gagal menutup bazar'),
          backgroundColor: AppColors.danger,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final role = ref.watch(authProvider).role;
    final showClose = canCloseBazar(role);

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: Padding(
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
                icon: Icons.fact_check_outlined,
                label: 'Stok Opname',
                onTap: () => context.push('/stock/stock-opname'),
              ),
              _MenuTile(
                icon: Icons.keyboard_return,
                label: 'Return Sisa',
                onTap: () => context.push('/stock/return'),
              ),
              const Spacer(),
              if (showClose)
                SizedBox(
                  height: 48,
                  child: OutlinedButton(
                    onPressed: _closeBazar,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.danger,
                      side: const BorderSide(color: AppColors.danger),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: Text(
                      'TUTUP BAZAR',
                      style: GoogleFonts.inter(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 1.5,
                      ),
                    ),
                  ),
                ),
            ],
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

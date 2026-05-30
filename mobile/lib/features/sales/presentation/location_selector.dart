import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import 'sales_provider.dart';

final selectedLocationProvider =
    StateProvider<Map<String, dynamic>?>((ref) => null);

String resolveLocationName(WidgetRef ref) {
  final selected = ref.watch(selectedLocationProvider);
  if (selected != null) {
    final name = selected['location_name']?.toString();
    if (name != null && name.isNotEmpty) return name;
  }
  final auth = ref.watch(authProvider);
  final fromAuth = auth.locationName ?? auth.user?['location_name']?.toString();
  if (fromAuth != null && fromAuth.isNotEmpty) return fromAuth;
  final cart = ref.watch(salesCartProvider);
  if (cart.selectedLocationName != null &&
      cart.selectedLocationName!.isNotEmpty) {
    return cart.selectedLocationName!;
  }
  return 'Belum dipilih';
}

String? resolveLocationId(WidgetRef ref) {
  final selected = ref.read(selectedLocationProvider);
  if (selected != null) {
    final id = selected['id']?.toString();
    if (id != null && id.isNotEmpty) return id;
  }
  final auth = ref.read(authProvider);
  if (auth.locationId != null && auth.locationId!.isNotEmpty) {
    return auth.locationId;
  }
  final cart = ref.read(salesCartProvider);
  if (cart.selectedLocationId != null && cart.selectedLocationId!.isNotEmpty) {
    return cart.selectedLocationId;
  }
  return null;
}

void applyLocationSelection(WidgetRef ref, Map<String, dynamic> loc) {
  final id = loc['id']?.toString() ?? '';
  final name = loc['location_name']?.toString() ??
      loc['name']?.toString() ??
      '—';
  ref.read(selectedLocationProvider.notifier).state = loc;
  ref.read(salesCartProvider.notifier).setSelectedLocation(id, name);
  ref.read(authProvider.notifier).setActiveLocation(id, name);
}

bool isInsufficientStockError(String? message) {
  if (message == null) return false;
  final lower = message.toLowerCase();
  return lower.contains('stok tidak') || lower.contains('stok tidak mencukupi');
}

Future<void> showLocationSelector(
  BuildContext context,
  WidgetRef ref, {
  VoidCallback? onLocationChanged,
}) async {
  final service = ref.read(salesServiceProvider);
  final dio = ref.read(apiClientProvider).dio;
  List<Map<String, dynamic>> locations;
  try {
    locations = await service.fetchLocations(dio);
  } catch (_) {
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Gagal memuat daftar lokasi'),
          backgroundColor: AppColors.danger,
        ),
      );
    }
    return;
  }

  if (!context.mounted) return;
  if (locations.isEmpty) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Tidak ada lokasi penjualan aktif'),
        backgroundColor: AppColors.warning,
      ),
    );
    return;
  }

  final currentId = resolveLocationId(ref);

  await showModalBottomSheet<void>(
    context: context,
    backgroundColor: AppColors.card,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
    ),
    builder: (ctx) => SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Text('Pilih Lokasi', style: AppTextStyles.cardTitle),
          ),
          ...locations.map((loc) {
            final id = loc['id']?.toString() ?? '';
            final name = loc['location_name']?.toString() ??
                loc['name']?.toString() ??
                '—';
            final type = loc['location_type']?.toString() ?? '';
            return ListTile(
              title: Text(name),
              subtitle: type.isNotEmpty
                  ? Text(type, style: AppTextStyles.monoMuted)
                  : null,
              trailing: currentId == id
                  ? const Icon(Icons.check, color: AppColors.success)
                  : null,
              onTap: () {
                final hadCartItems = ref.read(salesCartProvider).items.isNotEmpty;
                applyLocationSelection(ref, loc);
                Navigator.pop(ctx);
                onLocationChanged?.call();
                if (hadCartItems && context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text(
                        'Perhatian: stok tersedia di lokasi yang dipilih. '
                        'Pastikan item sudah di-transfer ke lokasi ini.',
                      ),
                      backgroundColor: AppColors.warning,
                      duration: Duration(seconds: 4),
                    ),
                  );
                }
              },
            );
          }),
          const SizedBox(height: 8),
        ],
      ),
    ),
  );
}

Future<void> showInsufficientStockSheet(
  BuildContext context,
  WidgetRef ref,
) async {
  await showModalBottomSheet<void>(
    context: context,
    backgroundColor: AppColors.card,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
    ),
    builder: (ctx) => Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.warning_amber_rounded,
            color: AppColors.warning,
            size: 48,
          ),
          const SizedBox(height: 12),
          Text(
            'Stok Tidak Tersedia di Lokasi Ini',
            style: AppTextStyles.cardTitle.copyWith(fontSize: 16),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 8),
          Text(
            'Item belum di-transfer ke lokasi yang dipilih.\n'
            'Lakukan transfer stok dari gudang terlebih dahulu,\n'
            'atau pilih lokasi lain.',
            style: AppTextStyles.cardSubtitle,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            height: 52,
            child: ElevatedButton(
              onPressed: () => Navigator.pop(ctx),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.voidBlack,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: const Text('MENGERTI'),
            ),
          ),
          const SizedBox(height: 8),
          SizedBox(
            width: double.infinity,
            height: 52,
            child: OutlinedButton(
              onPressed: () {
                Navigator.pop(ctx);
                showLocationSelector(context, ref);
              },
              style: OutlinedButton.styleFrom(
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: const Text(
                'GANTI LOKASI',
                style: TextStyle(color: AppColors.voidBlack),
              ),
            ),
          ),
          const SizedBox(height: 8),
        ],
      ),
    ),
  );
}

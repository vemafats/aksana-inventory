import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/event/active_event.dart';
import '../../../core/event/active_event_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import 'sales_provider.dart';

String resolveLocationName(WidgetRef ref) {
  final eventState = ref.watch(activeEventNotifierProvider);
  final fromEvent = eventState.selectedEvent?.locationName;
  if (fromEvent != null && fromEvent.isNotEmpty) return fromEvent;

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
  final eventState = ref.read(activeEventNotifierProvider);
  final fromEvent = eventState.selectedEvent?.locationId;
  if (fromEvent != null && fromEvent.isNotEmpty) return fromEvent;

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

void applyEventSelection(WidgetRef ref, ActiveEvent event) {
  ref.read(activeEventNotifierProvider.notifier).selectEvent(event);
  ref.read(authProvider.notifier).setActiveLocation(
        event.locationId,
        event.locationName,
        type: event.locationType,
      );
  ref.read(salesCartProvider.notifier).setSelectedLocation(
        event.locationId,
        event.locationName,
      );
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
  final dio = ref.read(apiClientProvider).dio;
  var eventState = ref.read(activeEventNotifierProvider);

  if (eventState.events.isEmpty && !eventState.isLoading) {
    await ref.read(activeEventNotifierProvider.notifier).fetchMyActiveEvents(dio);
    eventState = ref.read(activeEventNotifierProvider);
  }

  if (!context.mounted) return;

  final events = eventState.events;
  if (events.isEmpty) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Tidak ada event aktif. Hubungi Owner/Admin.'),
        backgroundColor: AppColors.warning,
      ),
    );
    return;
  }

  final currentId = resolveLocationId(ref);
  final selectedEventId =
      ref.read(activeEventNotifierProvider).selectedEvent?.eventId;

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
            child: Text('Pilih Event', style: AppTextStyles.cardTitle),
          ),
          ...events.map((event) {
            final subtitle = [
              if (event.locationName.isNotEmpty) event.locationName,
              if (event.roleInEvent.isNotEmpty) event.roleInEvent,
            ].join(' · ');
            final isSelected = selectedEventId == event.eventId ||
                (selectedEventId == null && currentId == event.locationId);
            return ListTile(
              title: Text(event.eventName),
              subtitle: subtitle.isNotEmpty
                  ? Text(subtitle, style: AppTextStyles.monoMuted)
                  : null,
              trailing: isSelected
                  ? const Icon(Icons.check, color: AppColors.success)
                  : null,
              onTap: () {
                final hadCartItems =
                    ref.read(salesCartProvider).items.isNotEmpty;
                applyEventSelection(ref, event);
                Navigator.pop(ctx);
                onLocationChanged?.call();
                if (hadCartItems && context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text(
                        'Perhatian: stok tersedia di lokasi event yang dipilih. '
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
                'GANTI EVENT',
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

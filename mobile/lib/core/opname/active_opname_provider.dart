import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/auth_provider.dart';
import '../theme/app_colors.dart';

final activeOpnameProvider = StateProvider<bool>((ref) => false);

Future<void> refreshActiveOpname(
  T Function<T>(ProviderListenable<T> provider) read,
) async {
  try {
    final dio = read(apiClientProvider).dio;
    final res = await dio.get(
      '/stock-opnames/active',
      options: Options(
        receiveTimeout: const Duration(seconds: 5),
        sendTimeout: const Duration(seconds: 5),
      ),
    );
    final data = res.data['data'];
    Map<String, dynamic>? session;
    if (data is Map) {
      session = Map<String, dynamic>.from(data);
    }
    read(activeOpnameProvider.notifier).state =
        _isBlockingActiveSession(session);
  } catch (_) {
    read(activeOpnameProvider.notifier).state = false;
  }
}

bool _isBlockingActiveSession(Map<String, dynamic>? session) {
  if (session == null) return false;
  final status = session['validation_status']?.toString();
  return status == 'draft' || status == 'pending_validation';
}

/// On-demand check before sales/stock-in submit — not called at login/startup.
Future<bool> isActiveOpnameBlocking(Dio dio) async {
  try {
    final res = await dio.get(
      '/stock-opnames/active',
      options: Options(
        receiveTimeout: const Duration(seconds: 5),
        sendTimeout: const Duration(seconds: 5),
      ),
    );
    final data = res.data['data'];
    if (data is Map) {
      return _isBlockingActiveSession(Map<String, dynamic>.from(data));
    }
  } catch (_) {}
  return false;
}

void showOpnameBlockedMessage(BuildContext context) {
  ScaffoldMessenger.of(context).showSnackBar(
    const SnackBar(
      content: Text(
        'Sesi Opname aktif — transaksi dinonaktifkan sementara.',
      ),
      backgroundColor: AppColors.warning,
    ),
  );
}

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/auth/auth_provider.dart';
import '../data/reports_service.dart';

class ReportsState {
  final Map<String, dynamic>? data;
  final bool isLoading;
  final String? errorMessage;

  const ReportsState({
    this.data,
    this.isLoading = false,
    this.errorMessage,
  });

  bool get isEmpty => data == null;
}

final reportsServiceProvider = Provider<ReportsService>((ref) => ReportsService());

class ReportsNotifier extends StateNotifier<ReportsState> {
  ReportsNotifier(this._service, this._dio) : super(const ReportsState());

  final ReportsService _service;
  final Dio _dio;

  Future<void> load() async {
    state = const ReportsState(isLoading: true);
    try {
      final data = await _service.fetchMobileSummary(_dio);
      state = ReportsState(data: data);
    } catch (_) {
      state = const ReportsState(
        errorMessage: 'Gagal memuat laporan',
      );
    }
  }
}

final reportsProvider =
    StateNotifierProvider<ReportsNotifier, ReportsState>((ref) {
  final notifier = ReportsNotifier(
    ref.watch(reportsServiceProvider),
    ref.watch(apiClientProvider).dio,
  );
  return notifier;
});

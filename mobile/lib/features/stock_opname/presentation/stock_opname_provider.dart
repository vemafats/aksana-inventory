import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/auth/auth_provider.dart';
import '../data/stock_opname_service.dart';

class StockOpnameState {
  final Map<String, dynamic>? session;
  final bool isLoading;
  final bool isSubmitting;
  final String? errorMessage;
  final bool awaitingValidation;

  const StockOpnameState({
    this.session,
    this.isLoading = false,
    this.isSubmitting = false,
    this.errorMessage,
    this.awaitingValidation = false,
  });

  List<Map<String, dynamic>> get items {
    final raw = session?['items'];
    if (raw is! List) return [];
    return raw
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }

  StockOpnameState copyWith({
    Map<String, dynamic>? session,
    bool? isLoading,
    bool? isSubmitting,
    String? errorMessage,
    bool? awaitingValidation,
    bool clearError = false,
  }) =>
      StockOpnameState(
        session: session ?? this.session,
        isLoading: isLoading ?? this.isLoading,
        isSubmitting: isSubmitting ?? this.isSubmitting,
        errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
        awaitingValidation: awaitingValidation ?? this.awaitingValidation,
      );
}

final stockOpnameServiceProvider =
    Provider<StockOpnameService>((ref) => StockOpnameService());

class StockOpnameNotifier extends StateNotifier<StockOpnameState> {
  StockOpnameNotifier(this._service, this._dio) : super(const StockOpnameState());

  final StockOpnameService _service;
  final Dio _dio;

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final active = await _service.getActive(_dio);
      if (active != null) {
        final full = await _service.fetchSession(_dio, active['id'].toString());
        state = StockOpnameState(
          session: full,
          awaitingValidation:
              full['validation_status'] == 'pending_validation',
        );
      } else {
        state = const StockOpnameState();
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: 'Gagal memuat sesi opname',
      );
      return;
    }
    state = state.copyWith(isLoading: false);
  }

  Future<bool> startSession(String locationId) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final session = await _service.createSession(
        _dio,
        locationId: locationId,
        opnameDate: DateFormat('yyyy-MM-dd').format(DateTime.now()),
      );
      state = StockOpnameState(session: session);
      return true;
    } on DioException catch (e) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: e.response?.data is Map
            ? e.response?.data['message']?.toString()
            : 'Gagal membuat sesi',
      );
      return false;
    } catch (_) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: 'Gagal membuat sesi',
      );
      return false;
    }
  }

  Future<bool> addScannedItem(Map<String, dynamic> catalog) async {
    final sessionId = state.session?['id']?.toString();
    final itemId = catalog['id']?.toString();
    if (sessionId == null || itemId == null) return false;

    final summary = catalog['stock_summary'];
    int systemQty = 0;
    if (summary is Map) {
      systemQty = (summary['total_available'] as num?)?.toInt() ?? 0;
    }

    try {
      await _service.addItem(
        _dio,
        sessionId,
        itemId: itemId,
        physicalAvailableQty: systemQty,
      );
      final full = await _service.fetchSession(_dio, sessionId);
      state = state.copyWith(session: full, clearError: true);
      return true;
    } catch (_) {
      state = state.copyWith(errorMessage: 'Gagal menambah item');
      return false;
    }
  }

  Future<bool> submitForValidation() async {
    final sessionId = state.session?['id']?.toString();
    if (sessionId == null) return false;

    state = state.copyWith(isSubmitting: true, clearError: true);
    try {
      final updated = await _service.submit(_dio, sessionId);
      state = state.copyWith(
        session: updated,
        isSubmitting: false,
        awaitingValidation: true,
      );
      return true;
    } catch (_) {
      state = state.copyWith(
        isSubmitting: false,
        errorMessage: 'Gagal mengirim untuk validasi',
      );
      return false;
    }
  }
}

final stockOpnameProvider =
    StateNotifierProvider<StockOpnameNotifier, StockOpnameState>((ref) {
  return StockOpnameNotifier(
    ref.watch(stockOpnameServiceProvider),
    ref.watch(apiClientProvider).dio,
  );
});

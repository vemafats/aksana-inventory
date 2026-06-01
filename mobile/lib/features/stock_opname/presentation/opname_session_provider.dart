import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/opname/active_opname_provider.dart';
import '../data/stock_opname_service.dart';

// ─── Provider tunggal (dihapus dari stock_opname_provider.dart) ──────────────
final stockOpnameServiceProvider =
    Provider<StockOpnameService>((ref) => StockOpnameService());

// ─── State ───────────────────────────────────────────────────────────────────

class OpnameSessionState {
  final Map<String, dynamic>? session;
  final List<Map<String, dynamic>> locationStock;
  final bool isLoading;
  final bool isSubmitting;
  final String? errorMessage;
  final bool awaitingValidation;

  const OpnameSessionState({
    this.session,
    this.locationStock = const [],
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

  String? get locationId => session?['location_id']?.toString();

  Set<String> get scannedItemIds {
    return items
        .map((row) => row['item_id']?.toString())
        .whereType<String>()
        .toSet();
  }

  int get totalItemsAtLocation => locationStock.length;
  int get scannedCount => scannedItemIds.length;

  bool get isDraft =>
      session?['validation_status']?.toString() == 'draft';

  OpnameSessionState copyWith({
    Map<String, dynamic>? session,
    List<Map<String, dynamic>>? locationStock,
    bool? isLoading,
    bool? isSubmitting,
    String? errorMessage,
    bool? awaitingValidation,
    bool clearError = false,
  }) =>
      OpnameSessionState(
        session: session ?? this.session,
        locationStock: locationStock ?? this.locationStock,
        isLoading: isLoading ?? this.isLoading,
        isSubmitting: isSubmitting ?? this.isSubmitting,
        errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
        awaitingValidation: awaitingValidation ?? this.awaitingValidation,
      );
}

// ─── Notifier ─────────────────────────────────────────────────────────────────

class OpnameSessionNotifier extends StateNotifier<OpnameSessionState> {
  OpnameSessionNotifier(
    this._service,
    this._dio,
    this._sessionId,
    this._read,
  ) : super(const OpnameSessionState(isLoading: true)) {
    load();
  }

  final StockOpnameService _service;
  final Dio _dio;
  final String _sessionId;
  final T Function<T>(ProviderListenable<T> provider) _read;

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final session = await _service.fetchSession(_dio, _sessionId);
      final stock = await _loadStockForSession(session);
      final status = session['validation_status']?.toString() ?? '';
      state = OpnameSessionState(
        session: session,
        locationStock: stock,
        awaitingValidation: status == 'pending_validation',
      );
      await refreshActiveOpname(_read);
    } catch (_) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: 'Gagal memuat sesi opname',
      );
    }
  }

  Future<List<Map<String, dynamic>>> _loadStockForSession(
    Map<String, dynamic> session,
  ) async {
    final locId = session['location_id']?.toString();
    if (locId == null || locId.isEmpty) return [];
    try {
      return await _service.fetchLocationStock(_dio, locId);
    } catch (_) {
      return [];
    }
  }

  int systemQtyForItem(String itemId) {
    for (final row in state.locationStock) {
      if (row['item_id']?.toString() == itemId) {
        return (row['available'] as num?)?.toInt() ?? 0;
      }
    }
    return 0;
  }

  Future<bool> saveItem({
    required String itemId,
    required int physicalQty,
    int damagedQty = 0,
  }) async {
    try {
      await _service.addItem(
        _dio,
        _sessionId,
        itemId: itemId,
        physicalAvailableQty: physicalQty,
        damagedQty: damagedQty,
      );
      final session = await _service.fetchSession(_dio, _sessionId);
      state = state.copyWith(session: session, clearError: true);
      return true;
    } on DioException catch (e) {
      final msg = e.response?.data is Map
          ? e.response?.data['message']?.toString()
          : null;
      state = state.copyWith(errorMessage: msg ?? 'Gagal menyimpan item');
      return false;
    } catch (_) {
      state = state.copyWith(errorMessage: 'Gagal menyimpan item');
      return false;
    }
  }

  Future<bool> submitForValidation() async {
    state = state.copyWith(isSubmitting: true, clearError: true);
    try {
      final updated = await _service.submit(_dio, _sessionId);
      state = state.copyWith(
        session: updated,
        isSubmitting: false,
        awaitingValidation: true,
      );
      await refreshActiveOpname(_read);
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

// ─── Provider ─────────────────────────────────────────────────────────────────

final opnameSessionProvider = StateNotifierProvider.autoDispose
    .family<OpnameSessionNotifier, OpnameSessionState, String>(
        (ref, sessionId) {
  return OpnameSessionNotifier(
    ref.watch(stockOpnameServiceProvider),
    ref.watch(apiClientProvider).dio,
    sessionId,
    ref.read,
  );
});

// ─── Helper functions ─────────────────────────────────────────────────────────

Future<String?> createOpnameSession(
  T Function<T>(ProviderListenable<T> provider) read,
  String locationId,
) async {
  final service = read(stockOpnameServiceProvider);
  final dio = read(apiClientProvider).dio;
  try {
    final session = await service.createSession(
      dio,
      locationId: locationId,
      opnameDate: DateTime.now().toIso8601String().substring(0, 10),
    );
    await refreshActiveOpname(read);
    return session['id']?.toString();
  } catch (_) {
    return null;
  }
}

Future<String?> fetchActiveOpnameSessionId(
  T Function<T>(ProviderListenable<T> provider) read,
) async {
  final active = await read(stockOpnameServiceProvider)
      .getActive(read(apiClientProvider).dio);
  return active?['id']?.toString();
}

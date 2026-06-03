import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/event/active_event.dart';
import '../data/return_stock_service.dart';

class ReturnItem {
  final String itemId;
  final String itemName;
  final String barcode;
  final int qtyGood;
  final int qtyDamaged;
  final int maxAvailable;

  const ReturnItem({
    required this.itemId,
    required this.itemName,
    required this.barcode,
    this.qtyGood = 0,
    this.qtyDamaged = 0,
    required this.maxAvailable,
  });

  ReturnItem copyWith({
    int? qtyGood,
    int? qtyDamaged,
  }) =>
      ReturnItem(
        itemId: itemId,
        itemName: itemName,
        barcode: barcode,
        qtyGood: qtyGood ?? this.qtyGood,
        qtyDamaged: qtyDamaged ?? this.qtyDamaged,
        maxAvailable: maxAvailable,
      );

  int get totalQty => qtyGood + qtyDamaged;
}

class ReturnStockState {
  final List<ReturnItem> items;
  final String? eventId;
  final String? eventName;
  final String? locationId;
  final String? locationName;
  final bool isLoading;
  final bool isSuccess;
  final String? errorMessage;

  const ReturnStockState({
    this.items = const [],
    this.eventId,
    this.eventName,
    this.locationId,
    this.locationName,
    this.isLoading = false,
    this.isSuccess = false,
    this.errorMessage,
  });

  int get totalItemCount => items.length;
  int get totalQty =>
      items.fold<int>(0, (sum, item) => sum + item.totalQty);

  bool get hasEvent =>
      eventId != null && eventId!.isNotEmpty;

  ReturnStockState copyWith({
    List<ReturnItem>? items,
    String? eventId,
    String? eventName,
    String? locationId,
    String? locationName,
    bool? isLoading,
    bool? isSuccess,
    String? errorMessage,
    bool clearError = false,
    bool clearEvent = false,
  }) =>
      ReturnStockState(
        items: items ?? this.items,
        eventId: clearEvent ? null : (eventId ?? this.eventId),
        eventName: clearEvent ? null : (eventName ?? this.eventName),
        locationId: clearEvent ? null : (locationId ?? this.locationId),
        locationName:
            clearEvent ? null : (locationName ?? this.locationName),
        isLoading: isLoading ?? this.isLoading,
        isSuccess: isSuccess ?? this.isSuccess,
        errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      );
}

final returnStockServiceProvider =
    Provider<ReturnStockService>((ref) => ReturnStockService());

class ReturnStockNotifier extends StateNotifier<ReturnStockState> {
  ReturnStockNotifier(this._service) : super(const ReturnStockState());

  final ReturnStockService _service;

  void setEvent(ActiveEvent event) {
    state = state.copyWith(
      eventId: event.eventId,
      eventName: event.eventName,
      locationId: event.locationId,
      locationName: event.locationName,
      items: [],
      clearError: true,
    );
  }

  void clearEvent() {
    state = state.copyWith(items: [], clearEvent: true, clearError: true);
  }

  void addScannedItem(Map<String, dynamic> scanResult) {
    if (!state.hasEvent) return;

    final id = scanResult['id']?.toString();
    if (id == null || id.isEmpty) return;

    if (state.items.any((i) => i.itemId == id)) return;

    final maxAvailable = _maxAvailableAtLocation(
      scanResult,
      state.locationId!,
      state.locationName ?? '',
    );

    state = state.copyWith(
      items: [
        ...state.items,
        ReturnItem(
          itemId: id,
          itemName: scanResult['item_name']?.toString() ?? '—',
          barcode: scanResult['barcode']?.toString() ?? '—',
          maxAvailable: maxAvailable,
        ),
      ],
      clearError: true,
    );
  }

  void updateQtyGood(String itemId, int qty) {
    _updateQty(itemId, good: qty);
  }

  void updateQtyDamaged(String itemId, int qty) {
    _updateQty(itemId, damaged: qty);
  }

  void _updateQty(String itemId, {int? good, int? damaged}) {
    final index = state.items.indexWhere((i) => i.itemId == itemId);
    if (index < 0) return;

    final item = state.items[index];
    var nextGood = good ?? item.qtyGood;
    var nextDamaged = damaged ?? item.qtyDamaged;

    nextGood = nextGood.clamp(0, item.maxAvailable);
    nextDamaged = nextDamaged.clamp(0, item.maxAvailable);

    if (nextGood + nextDamaged > item.maxAvailable) {
      if (good != null) {
        nextDamaged = (item.maxAvailable - nextGood).clamp(0, item.maxAvailable);
      } else {
        nextGood = (item.maxAvailable - nextDamaged).clamp(0, item.maxAvailable);
      }
    }

    final updated = List<ReturnItem>.from(state.items);
    updated[index] = item.copyWith(
      qtyGood: nextGood,
      qtyDamaged: nextDamaged,
    );
    state = state.copyWith(items: updated, clearError: true);
  }

  void removeItem(String itemId) {
    state = state.copyWith(
      items: state.items.where((i) => i.itemId != itemId).toList(),
      clearError: true,
    );
  }

  Future<bool> submit(Dio dio) async {
    if (!state.hasEvent) {
      state = state.copyWith(errorMessage: 'Pilih event terlebih dahulu');
      return false;
    }

    final payloadItems = state.items
        .where((i) => i.qtyGood + i.qtyDamaged > 0)
        .map(
          (i) => {
            'item_id': i.itemId,
            'qty_good': i.qtyGood,
            'qty_damaged': i.qtyDamaged,
          },
        )
        .toList();

    if (payloadItems.isEmpty) {
      state = state.copyWith(
        errorMessage: 'Minimal satu item dengan qty good atau rusak > 0',
      );
      return false;
    }

    state = state.copyWith(isLoading: true, clearError: true, isSuccess: false);
    try {
      await _service.createReturn({
        'event_id': state.eventId,
        'return_date': DateFormat('yyyy-MM-dd').format(DateTime.now()),
        'note': 'Return sisa event',
        'items': payloadItems,
      }, dio);
      state = const ReturnStockState(isSuccess: true);
      return true;
    } on DioException catch (e) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: e.response?.data is Map
            ? e.response?.data['message']?.toString()
            : 'Gagal membuat return',
      );
      return false;
    } catch (_) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: 'Gagal membuat return',
      );
      return false;
    }
  }

  void clear() {
    state = const ReturnStockState();
  }

  int _maxAvailableAtLocation(
    Map<String, dynamic> scanResult,
    String locationId,
    String locationName,
  ) {
    final summary = scanResult['stock_summary'];
    if (summary is Map) {
      final perLoc = summary['per_location'];
      if (perLoc is List) {
        final targetName = locationName.trim().toLowerCase();
        for (final raw in perLoc) {
          if (raw is! Map) continue;
          final m = Map<String, dynamic>.from(raw);
          final name = m['location_name']?.toString().trim().toLowerCase() ?? '';
          if (targetName.isNotEmpty && name == targetName) {
            return (m['available'] as num?)?.toInt() ?? 0;
          }
        }
      }
    }

    final balances =
        scanResult['stock_balances'] ?? scanResult['stockBalances'];
    if (balances is List) {
      var total = 0;
      for (final raw in balances) {
        if (raw is! Map) continue;
        final b = Map<String, dynamic>.from(raw);
        if (b['location_id']?.toString() == locationId &&
            b['stock_status']?.toString() == 'available') {
          total += (b['qty'] as num?)?.toInt() ?? 0;
        }
      }
      return total;
    }

    return 0;
  }
}

final returnStockProvider =
    StateNotifierProvider<ReturnStockNotifier, ReturnStockState>((ref) {
  return ReturnStockNotifier(ref.watch(returnStockServiceProvider));
});

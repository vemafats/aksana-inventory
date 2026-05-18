import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../data/return_stock_service.dart';

class ReturnLineItem {
  final String itemId;
  final String itemName;
  final String sku;
  final int soldQty;
  final int returnQty;
  final double sellingPrice;

  const ReturnLineItem({
    required this.itemId,
    required this.itemName,
    required this.sku,
    this.soldQty = 0,
    this.returnQty = 1,
    this.sellingPrice = 0,
  });

  ReturnLineItem copyWith({int? returnQty}) => ReturnLineItem(
        itemId: itemId,
        itemName: itemName,
        sku: sku,
        soldQty: soldQty,
        returnQty: returnQty ?? this.returnQty,
        sellingPrice: sellingPrice,
      );
}

class ReturnStockState {
  final List<ReturnLineItem> items;
  final bool isSubmitting;
  final String? errorMessage;
  final String? warehouseLocationId;

  const ReturnStockState({
    this.items = const [],
    this.isSubmitting = false,
    this.errorMessage,
    this.warehouseLocationId,
  });

  ReturnStockState copyWith({
    List<ReturnLineItem>? items,
    bool? isSubmitting,
    String? errorMessage,
    String? warehouseLocationId,
    bool clearError = false,
  }) =>
      ReturnStockState(
        items: items ?? this.items,
        isSubmitting: isSubmitting ?? this.isSubmitting,
        errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
        warehouseLocationId:
            warehouseLocationId ?? this.warehouseLocationId,
      );
}

final returnStockServiceProvider =
    Provider<ReturnStockService>((ref) => ReturnStockService());

class ReturnStockNotifier extends StateNotifier<ReturnStockState> {
  ReturnStockNotifier(this._service) : super(const ReturnStockState());

  final ReturnStockService _service;

  Future<void> loadWarehouse(Dio dio) async {
    final id = await _service.fetchCentralWarehouseId(dio);
    if (id != null) {
      state = state.copyWith(warehouseLocationId: id);
    }
  }

  void addFromCatalog(Map<String, dynamic> catalog) {
    final id = catalog['id']?.toString();
    if (id == null) return;

    final index = state.items.indexWhere((i) => i.itemId == id);
    if (index >= 0) {
      final updated = List<ReturnLineItem>.from(state.items);
      updated[index] = updated[index].copyWith(
        returnQty: updated[index].returnQty + 1,
      );
      state = state.copyWith(items: updated);
      return;
    }

    final priceRaw = catalog['latest_base_selling_price'];
    state = state.copyWith(
      items: [
        ...state.items,
        ReturnLineItem(
          itemId: id,
          itemName: catalog['item_name']?.toString() ?? '—',
          sku: catalog['sku']?.toString() ?? '—',
          sellingPrice:
              priceRaw is num ? priceRaw.toDouble() : 0,
        ),
      ],
    );
  }

  void updateQty(String itemId, int delta) {
    final index = state.items.indexWhere((i) => i.itemId == itemId);
    if (index < 0) return;
    final next = state.items[index].returnQty + delta;
    if (next <= 0) {
      state = state.copyWith(
        items: state.items.where((i) => i.itemId != itemId).toList(),
      );
      return;
    }
    final updated = List<ReturnLineItem>.from(state.items);
    updated[index] = updated[index].copyWith(returnQty: next);
    state = state.copyWith(items: updated);
  }

  Future<bool> submit({
    required Dio dio,
    required String fromLocationId,
  }) async {
    if (state.items.isEmpty) return false;

    final warehouseId = state.warehouseLocationId;
    if (warehouseId == null || warehouseId.isEmpty) {
      state = state.copyWith(
        errorMessage: 'Gudang pusat tidak ditemukan',
      );
      return false;
    }

    state = state.copyWith(isSubmitting: true, clearError: true);
    try {
      await _service.createReturnTransfer({
        'from_location_id': fromLocationId,
        'to_location_id': warehouseId,
        'transfer_date': DateFormat('yyyy-MM-dd').format(DateTime.now()),
        'note': 'Return sisa bazar',
        'items': state.items
            .map(
              (item) => {
                'item_id': item.itemId,
                'qty': item.returnQty,
                'bazar_adjust_type': 'none',
                'bazar_adjust_value': 0,
                'bazar_selling_price': item.sellingPrice,
              },
            )
            .toList(),
      }, dio);
      state = const ReturnStockState();
      return true;
    } on DioException catch (e) {
      state = state.copyWith(
        isSubmitting: false,
        errorMessage: e.response?.data is Map
            ? e.response?.data['message']?.toString()
            : 'Gagal membuat surat return',
      );
      return false;
    } catch (_) {
      state = state.copyWith(
        isSubmitting: false,
        errorMessage: 'Gagal membuat surat return',
      );
      return false;
    }
  }
}

final returnStockProvider =
    StateNotifierProvider<ReturnStockNotifier, ReturnStockState>((ref) {
  return ReturnStockNotifier(ref.watch(returnStockServiceProvider));
});

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/stock_in_service.dart';

class StockInItem {
  final String barcode;
  final String itemName;
  final String sku;
  final int qty;

  const StockInItem({
    required this.barcode,
    required this.itemName,
    required this.sku,
    this.qty = 1,
  });

  StockInItem copyWith({int? qty}) => StockInItem(
        barcode: barcode,
        itemName: itemName,
        sku: sku,
        qty: qty ?? this.qty,
      );
}

final stockInServiceProvider = Provider<StockInService>((ref) => StockInService());

class StockInNotifier extends StateNotifier<List<StockInItem>> {
  StockInNotifier(this._service) : super(const []);

  final StockInService _service;

  void addItem(Map<String, dynamic> catalogItem) {
    final barcode = catalogItem['barcode']?.toString();
    if (barcode == null || barcode.isEmpty) return;

    final index = state.indexWhere((item) => item.barcode == barcode);
    if (index >= 0) {
      final updated = List<StockInItem>.from(state);
      updated[index] = updated[index].copyWith(qty: updated[index].qty + 1);
      state = updated;
      return;
    }

    state = [
      ...state,
      StockInItem(
        barcode: barcode,
        itemName: catalogItem['item_name']?.toString() ?? '—',
        sku: catalogItem['sku']?.toString() ?? '—',
      ),
    ];
  }

  void updateQty(String barcode, int delta) {
    final index = state.indexWhere((item) => item.barcode == barcode);
    if (index < 0) return;

    final nextQty = state[index].qty + delta;
    if (nextQty <= 0) {
      removeItem(barcode);
      return;
    }

    final updated = List<StockInItem>.from(state);
    updated[index] = updated[index].copyWith(qty: nextQty);
    state = updated;
  }

  void removeItem(String barcode) {
    state = state.where((item) => item.barcode != barcode).toList();
  }

  void clear() => state = const [];

  Future<bool> submitTransaction(
    Dio dio, {
    String? supplierName,
    required String transactionDate,
    String? poReference,
  }) async {
    if (state.isEmpty) return false;

    final payload = {
      'supplier_name': supplierName,
      'transaction_date': transactionDate,
      'note': poReference,
      'items': state
          .map(
            (item) => {
              'barcode': item.barcode,
              'qty_received': item.qty,
              'qty_available': item.qty,
              'qty_damaged': 0,
              'supplier_cost': 1,
              'base_margin_type': 'nominal',
              'base_margin_value': 0,
              'base_selling_price': 0,
            },
          )
          .toList(),
    };

    await _service.createTransaction(payload, dio);
    clear();
    return true;
  }
}

final stockInProvider =
    StateNotifierProvider<StockInNotifier, List<StockInItem>>((ref) {
  return StockInNotifier(ref.watch(stockInServiceProvider));
});

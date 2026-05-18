import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../data/sales_service.dart';

class CartItem {
  final String itemId;
  final String itemName;
  final String sku;
  final double bazarSellingPrice;
  final int qty;

  const CartItem({
    required this.itemId,
    required this.itemName,
    required this.sku,
    required this.bazarSellingPrice,
    this.qty = 1,
  });

  CartItem copyWith({int? qty}) => CartItem(
        itemId: itemId,
        itemName: itemName,
        sku: sku,
        bazarSellingPrice: bazarSellingPrice,
        qty: qty ?? this.qty,
      );

  double get lineTotal => bazarSellingPrice * qty;
}

class SalesCartState {
  final List<CartItem> items;
  final String paymentMethod;
  final bool isLoading;
  final String? errorMessage;
  final bool isSuccess;
  final double bazarDiscount;

  const SalesCartState({
    this.items = const [],
    this.paymentMethod = 'cash',
    this.isLoading = false,
    this.errorMessage,
    this.isSuccess = false,
    this.bazarDiscount = 0,
  });

  SalesCartState copyWith({
    List<CartItem>? items,
    String? paymentMethod,
    bool? isLoading,
    String? errorMessage,
    bool? isSuccess,
    double? bazarDiscount,
    bool clearError = false,
  }) =>
      SalesCartState(
        items: items ?? this.items,
        paymentMethod: paymentMethod ?? this.paymentMethod,
        isLoading: isLoading ?? this.isLoading,
        errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
        isSuccess: isSuccess ?? this.isSuccess,
        bazarDiscount: bazarDiscount ?? this.bazarDiscount,
      );
}

final salesServiceProvider = Provider<SalesService>((ref) => SalesService());

class SalesCartNotifier extends StateNotifier<SalesCartState> {
  SalesCartNotifier(this._service) : super(const SalesCartState());

  final SalesService _service;

  double get subtotal =>
      state.items.fold(0, (sum, item) => sum + item.lineTotal);

  double get grandTotal => subtotal - state.bazarDiscount;

  void addItem(Map<String, dynamic> catalogItem) {
    final itemId = catalogItem['id']?.toString();
    if (itemId == null || itemId.isEmpty) return;

    final priceRaw = catalogItem['bazar_selling_price'] ??
        catalogItem['latest_base_selling_price'];
    final price = priceRaw is num ? priceRaw.toDouble() : 0.0;

    final index = state.items.indexWhere((item) => item.itemId == itemId);
    if (index >= 0) {
      final updated = List<CartItem>.from(state.items);
      updated[index] =
          updated[index].copyWith(qty: updated[index].qty + 1);
      state = state.copyWith(items: updated, clearError: true, isSuccess: false);
      return;
    }

    state = state.copyWith(
      items: [
        ...state.items,
        CartItem(
          itemId: itemId,
          itemName: catalogItem['item_name']?.toString() ?? '—',
          sku: catalogItem['sku']?.toString() ?? '—',
          bazarSellingPrice: price,
        ),
      ],
      clearError: true,
      isSuccess: false,
    );
  }

  void removeItem(String itemId) {
    state = state.copyWith(
      items: state.items.where((item) => item.itemId != itemId).toList(),
      clearError: true,
    );
  }

  void updateQty(String itemId, int delta) {
    final index = state.items.indexWhere((item) => item.itemId == itemId);
    if (index < 0) return;

    final nextQty = state.items[index].qty + delta;
    if (nextQty <= 0) {
      removeItem(itemId);
      return;
    }

    final updated = List<CartItem>.from(state.items);
    updated[index] = updated[index].copyWith(qty: nextQty);
    state = state.copyWith(items: updated, clearError: true);
  }

  void setPaymentMethod(String method) {
    state = state.copyWith(paymentMethod: method, clearError: true);
  }

  void clear() => state = const SalesCartState();

  Future<bool> checkout(
    Dio dio, {
    required String locationId,
    required String employeeId,
  }) async {
    if (state.items.isEmpty) return false;

    state = state.copyWith(isLoading: true, clearError: true, isSuccess: false);

    try {
      final payload = {
        'location_id': locationId,
        'employee_id': employeeId,
        'transaction_date': DateFormat("yyyy-MM-dd'T'HH:mm:ss").format(
          DateTime.now(),
        ),
        'payment_method': state.paymentMethod,
        'transaction_discount_type': 'none',
        'transaction_discount_value': 0,
        'items': state.items
            .map(
              (item) => {
                'item_id': item.itemId,
                'qty': item.qty,
                'item_discount_type': 'none',
                'item_discount_value': 0,
              },
            )
            .toList(),
      };

      await _service.createTransaction(payload, dio);
      state = const SalesCartState(isSuccess: true);
      return true;
    } on DioException catch (e) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: _dioMessage(e),
      );
      return false;
    } catch (_) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: 'Terjadi kesalahan. Coba lagi.',
      );
      return false;
    }
  }

  String _dioMessage(DioException e) {
    if (e.response?.statusCode == 401) {
      return 'Sesi berakhir. Silakan login kembali.';
    }
    final message = e.response?.data;
    if (message is Map && message['message'] != null) {
      return message['message'].toString();
    }
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.sendTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.connectionError) {
      return 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.';
    }
    return 'Terjadi kesalahan. Coba lagi.';
  }
}

final salesCartProvider =
    StateNotifierProvider<SalesCartNotifier, SalesCartState>((ref) {
  return SalesCartNotifier(ref.watch(salesServiceProvider));
});

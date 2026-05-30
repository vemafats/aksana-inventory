import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

class ScanService {
  Future<Map<String, dynamic>?> findByBarcode(
    String barcode,
    Dio dio,
  ) async {
    final encoded = Uri.encodeComponent(barcode);
    final path = '/catalogs/by-barcode/$encoded';

    debugPrint('[ScanService] GET $path');

    try {
      final res = await dio.get(path);
      debugPrint('[ScanService] Response status=${res.statusCode}');

      final data = res.data['data'];
      if (data is Map<String, dynamic>) {
        debugPrint('[ScanService] Found item: ${data['item_name']}');
        return data;
      }
      if (data is Map) {
        final item = Map<String, dynamic>.from(data);
        debugPrint('[ScanService] Found item: ${item['item_name']}');
        return item;
      }

      debugPrint('[ScanService] Unexpected response shape: ${res.data}');
      return null;
    } on DioException catch (e) {
      debugPrint(
        '[ScanService] DioException status=${e.response?.statusCode} '
        'message=${e.message}',
      );

      if (e.response?.statusCode == 404) {
        return null;
      }
      rethrow;
    }
  }
}

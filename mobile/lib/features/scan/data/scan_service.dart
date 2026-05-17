import 'package:dio/dio.dart';

class ScanService {
  Future<Map<String, dynamic>?> findByBarcode(
    String barcode,
    Dio dio,
  ) async {
    try {
      final res = await dio.get(
        '/catalogs/by-barcode/${Uri.encodeComponent(barcode)}',
      );
      final data = res.data['data'];
      if (data is Map<String, dynamic>) {
        return data;
      }
      if (data is Map) {
        return Map<String, dynamic>.from(data);
      }
      return null;
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) {
        return null;
      }
      rethrow;
    }
  }
}

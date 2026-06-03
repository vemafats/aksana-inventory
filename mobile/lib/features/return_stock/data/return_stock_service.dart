import 'package:dio/dio.dart';

class ReturnStockService {
  Future<String?> fetchCentralWarehouseId(Dio dio) async {
    try {
      final res = await dio.get('/locations/central-warehouse');
      final data = res.data['data'];
      if (data is Map) return data['id']?.toString();
    } catch (_) {}
    return null;
  }

  Future<Map<String, dynamic>> createReturn(
    Map<String, dynamic> data,
    Dio dio,
  ) async {
    final res = await dio.post('/returns', data: data);
    final body = res.data;
    if (body is Map<String, dynamic>) return body;
    if (body is Map) return Map<String, dynamic>.from(body);
    return {};
  }
}

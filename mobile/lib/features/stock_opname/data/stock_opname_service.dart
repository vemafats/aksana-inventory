import 'package:dio/dio.dart';

class StockOpnameService {
  Future<Map<String, dynamic>?> getActive(Dio dio) async {
    final res = await dio.get('/stock-opnames/active');
    final data = res.data['data'];
    if (data == null) return null;
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return Map<String, dynamic>.from(data);
    return null;
  }

  Future<Map<String, dynamic>> createSession(
    Dio dio, {
    required String locationId,
    required String opnameDate,
  }) async {
    final res = await dio.post('/stock-opnames', data: {
      'location_id': locationId,
      'opname_date': opnameDate,
    });
    return _mapData(res.data);
  }

  Future<Map<String, dynamic>> fetchSession(Dio dio, String id) async {
    final res = await dio.get('/stock-opnames/$id');
    return _mapData(res.data);
  }

  Future<void> addItem(
    Dio dio,
    String sessionId, {
    required String itemId,
    required int physicalAvailableQty,
    int damagedQty = 0,
  }) async {
    await dio.post('/stock-opnames/$sessionId/items', data: {
      'item_id': itemId,
      'physical_available_qty': physicalAvailableQty,
      'damaged_qty': damagedQty,
    });
  }

  Future<Map<String, dynamic>> submit(Dio dio, String sessionId) async {
    final res = await dio.post('/stock-opnames/$sessionId/submit');
    return _mapData(res.data);
  }

  Map<String, dynamic> _mapData(dynamic body) {
    final data = body is Map ? body['data'] : null;
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return Map<String, dynamic>.from(data);
    return {};
  }
}

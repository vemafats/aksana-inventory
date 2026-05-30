import 'package:dio/dio.dart';

class SalesService {
  Future<List<Map<String, dynamic>>> fetchLocations(Dio dio) async {
    final res = await dio.get('/locations');
    final data = res.data['data'];
    if (data is! List) return [];
    return data
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }

  Future<Map<String, dynamic>> createTransaction(
    Map<String, dynamic> data,
    Dio dio,
  ) async {
    final res = await dio.post('/sales', data: data);
    final body = res.data;
    if (body is Map<String, dynamic>) {
      return body;
    }
    if (body is Map) {
      return Map<String, dynamic>.from(body);
    }
    return {};
  }
}

import 'package:dio/dio.dart';

class StockInService {
  Future<Map<String, dynamic>> createTransaction(
    Map<String, dynamic> data,
    Dio dio,
  ) async {
    final res = await dio.post('/stock-in', data: data);
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

import 'package:dio/dio.dart';

class SalesService {
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

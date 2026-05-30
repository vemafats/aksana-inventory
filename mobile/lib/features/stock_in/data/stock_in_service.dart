import 'package:dio/dio.dart';

class StockInService {
  Future<String?> uploadPhoto(
    Dio dio, {
    required String filePath,
    required String relatedId,
    String relatedType = 'stock_in',
  }) async {
    final formData = FormData.fromMap({
      'photo': await MultipartFile.fromFile(
        filePath,
        filename:
            'stock_in_${DateTime.now().millisecondsSinceEpoch}.jpg',
      ),
      'related_type': relatedType,
      'related_id': relatedId,
    });

    final res = await dio.post('/photos', data: formData);
    final data = res.data;
    if (data is Map && data['data'] is Map) {
      return data['data']['id']?.toString();
    }
    return null;
  }

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

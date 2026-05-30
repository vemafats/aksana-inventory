import 'package:dio/dio.dart';

class ReportsService {
  Future<Map<String, dynamic>> fetchMobileSummary(Dio dio) async {
    final res = await dio.get('/reports/mobile-summary');
    final body = res.data;
    if (body is Map && body['data'] != null) {
      final data = body['data'];
      if (data is Map<String, dynamic>) return data;
      if (data is Map) return Map<String, dynamic>.from(data);
    }
    throw DioException(
      requestOptions: res.requestOptions,
      response: res,
      message: 'Data laporan tidak valid',
    );
  }
}

import 'package:dio/dio.dart';

class ReportsService {
  Future<Map<String, dynamic>?> fetchMobileSummary(Dio dio) async {
    try {
      final res = await dio.get('/reports/mobile-summary');
      final data = res.data['data'];
      if (data is Map<String, dynamic>) return data;
      if (data is Map) return Map<String, dynamic>.from(data);
      return null;
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) return null;
      rethrow;
    }
  }
}

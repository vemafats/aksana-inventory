import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class SalesPhotoService {
  Future<Map<String, dynamic>?> uploadSalesPhoto(
    Dio dio, {
    required String filePath,
    required String relatedId,
  }) async {
    final formData = FormData.fromMap({
      'photo': await MultipartFile.fromFile(
        filePath,
        filename: 'sales_${DateTime.now().millisecondsSinceEpoch}.jpg',
      ),
      'related_type': 'sales',
      'related_id': relatedId,
    });

    final res = await dio.post('/photos', data: formData);
    final data = res.data;
    if (data is Map && data['data'] is Map) {
      final photo = Map<String, dynamic>.from(data['data'] as Map);
      final id = photo['id']?.toString();
      if (id == null || id.isEmpty) return null;
      return {
        'id': id,
        'photo_url': photo['photo_url']?.toString(),
      };
    }
    return null;
  }
}

final salesPhotoServiceProvider =
    Provider<SalesPhotoService>((ref) => SalesPhotoService());

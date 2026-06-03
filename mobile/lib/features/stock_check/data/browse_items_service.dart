import 'package:dio/dio.dart';

class BrowseItemsPageResult {
  final List<Map<String, dynamic>> items;
  final int currentPage;
  final int lastPage;

  const BrowseItemsPageResult({
    required this.items,
    required this.currentPage,
    required this.lastPage,
  });

  bool get hasMore => currentPage < lastPage;
}

class BrowseItemsService {
  Future<BrowseItemsPageResult> fetchItems(
    Dio dio, {
    int page = 1,
    String? search,
  }) async {
    final query = <String, dynamic>{'page': page};
    final trimmed = search?.trim();
    if (trimmed != null && trimmed.isNotEmpty) {
      query['search'] = trimmed;
    }

    final res = await dio.get('/catalogs', queryParameters: query);
    final root = res.data['data'];
    if (root is! Map) {
      return const BrowseItemsPageResult(
        items: [],
        currentPage: 1,
        lastPage: 1,
      );
    }

    final pageData = root['data'];
    final items = <Map<String, dynamic>>[];
    if (pageData is List) {
      for (final item in pageData) {
        if (item is Map) {
          items.add(Map<String, dynamic>.from(item));
        }
      }
    }

    final currentPage = (root['current_page'] as num?)?.toInt() ?? page;
    final lastPage = (root['last_page'] as num?)?.toInt() ?? currentPage;

    return BrowseItemsPageResult(
      items: items,
      currentPage: currentPage,
      lastPage: lastPage,
    );
  }

  /// Ensures [stock_summary] exists for [StockCheckScreen] (index may omit it).
  static Map<String, dynamic> itemForStockCheck(Map<String, dynamic> item) {
    final copy = Map<String, dynamic>.from(item);
    if (copy['stock_summary'] is Map) {
      return copy;
    }

    final balances = copy['stock_balances'] ?? copy['stockBalances'];
    if (balances is! List) {
      copy['stock_summary'] = {
        'total_available': 0,
        'per_location': <Map<String, dynamic>>[],
      };
      return copy;
    }

    var totalAvailable = 0;
    final byLocation = <String, Map<String, dynamic>>{};

    for (final raw in balances) {
      if (raw is! Map) continue;
      final b = Map<String, dynamic>.from(raw);
      final status = b['stock_status']?.toString() ?? '';
      final qty = (b['qty'] as num?)?.toInt() ?? 0;
      final locId = b['location_id']?.toString() ?? '';
      final loc = b['location'];
      var locName = '—';
      if (loc is Map) {
        locName = loc['location_name']?.toString() ?? '—';
      }

      final entry = byLocation.putIfAbsent(
        locId,
        () => {
          'location_name': locName,
          'available': 0,
          'damaged': 0,
          'lost': 0,
        },
      );

      if (status == 'available') {
        entry['available'] = (entry['available'] as int) + qty;
        totalAvailable += qty;
      } else if (status == 'damaged') {
        entry['damaged'] = (entry['damaged'] as int) + qty;
      } else if (status == 'lost') {
        entry['lost'] = (entry['lost'] as int) + qty;
      }
    }

    copy['stock_summary'] = {
      'total_available': totalAvailable,
      'per_location': byLocation.values.toList(),
    };
    return copy;
  }

  static int totalAvailableQty(Map<String, dynamic> item) {
    final summary = item['stock_summary'];
    if (summary is Map) {
      return (summary['total_available'] as num?)?.toInt() ?? 0;
    }
    final enriched = itemForStockCheck(item);
    final built = enriched['stock_summary'];
    if (built is Map) {
      return (built['total_available'] as num?)?.toInt() ?? 0;
    }
    return 0;
  }
}

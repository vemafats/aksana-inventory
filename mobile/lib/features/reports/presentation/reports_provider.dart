import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/auth/auth_provider.dart';
import '../data/reports_service.dart';

class MobileReportData {
  final double todaysNetSales;
  final int todaysTransactions;
  final int itemsSoldToday;
  final double avgBasketToday;
  final double vsYesterdayPct;
  final List<Map<String, dynamic>> sevenDayTrend;
  final Map<String, dynamic>? topSkuToday;
  final int lowStockCount;

  const MobileReportData({
    required this.todaysNetSales,
    required this.todaysTransactions,
    required this.itemsSoldToday,
    required this.avgBasketToday,
    required this.vsYesterdayPct,
    required this.sevenDayTrend,
    this.topSkuToday,
    required this.lowStockCount,
  });

  factory MobileReportData.placeholder() => const MobileReportData(
        todaysNetSales: 0,
        todaysTransactions: 0,
        itemsSoldToday: 0,
        avgBasketToday: 0,
        vsYesterdayPct: 0,
        sevenDayTrend: [],
        topSkuToday: null,
        lowStockCount: 0,
      );

  factory MobileReportData.fromJson(Map<String, dynamic>? json) {
    if (json == null || json.isEmpty) {
      return MobileReportData.placeholder();
    }

    final trendRaw = json['seven_day_trend'];
    final trend = <Map<String, dynamic>>[];
    if (trendRaw is List) {
      for (final entry in trendRaw) {
        if (entry is Map) {
          trend.add(Map<String, dynamic>.from(entry));
        } else if (entry is num) {
          trend.add({'total_sales': entry});
        }
      }
    }

    Map<String, dynamic>? topSku;
    final topRaw = json['top_sku_today'] ?? json['top_sku'];
    if (topRaw is Map) {
      topSku = Map<String, dynamic>.from(topRaw);
    }

    return MobileReportData(
      todaysNetSales: _toDouble(
        json['todays_net_sales'] ?? json['net_sales'],
      ),
      todaysTransactions: _toInt(
        json['todays_transactions'] ?? json['transactions'],
      ),
      itemsSoldToday: _toInt(
        json['items_sold_today'] ?? json['items_sold'],
      ),
      avgBasketToday: _toDouble(
        json['avg_basket_today'] ?? json['avg_basket'],
      ),
      vsYesterdayPct: _toDouble(
        json['vs_yesterday_pct'] ?? json['net_sales_change_pct'],
      ),
      sevenDayTrend: trend,
      topSkuToday: topSku,
      lowStockCount: _toInt(json['low_stock_count']),
    );
  }

  static double _toDouble(dynamic v) {
    if (v is num) return v.toDouble();
    return double.tryParse(v?.toString() ?? '0') ?? 0;
  }

  static int _toInt(dynamic v) {
    if (v is num) return v.toInt();
    return int.tryParse(v?.toString() ?? '0') ?? 0;
  }
}

class ReportsState {
  final MobileReportData data;
  final bool isLoading;
  final String? errorMessage;
  final bool showRetry;

  const ReportsState({
    this.data = const MobileReportData(
      todaysNetSales: 0,
      todaysTransactions: 0,
      itemsSoldToday: 0,
      avgBasketToday: 0,
      vsYesterdayPct: 0,
      sevenDayTrend: [],
      lowStockCount: 0,
    ),
    this.isLoading = false,
    this.errorMessage,
    this.showRetry = false,
  });

  ReportsState copyWith({
    MobileReportData? data,
    bool? isLoading,
    String? errorMessage,
    bool? showRetry,
    bool clearError = false,
  }) =>
      ReportsState(
        data: data ?? this.data,
        isLoading: isLoading ?? this.isLoading,
        errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
        showRetry: showRetry ?? this.showRetry,
      );
}

final reportsServiceProvider = Provider<ReportsService>((ref) => ReportsService());

class ReportsNotifier extends StateNotifier<ReportsState> {
  ReportsNotifier(this._service, this._dio) : super(const ReportsState());

  final ReportsService _service;
  final Dio _dio;

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true, showRetry: false);
    try {
      final raw = await _service.fetchMobileSummary(_dio);
      state = ReportsState(
        data: MobileReportData.fromJson(raw),
        isLoading: false,
      );
    } catch (_) {
      state = ReportsState(
        data: MobileReportData.placeholder(),
        isLoading: false,
        errorMessage: 'Gagal memuat laporan. Periksa koneksi internet Anda.',
        showRetry: true,
      );
    }
  }
}

final reportsProvider =
    StateNotifierProvider<ReportsNotifier, ReportsState>((ref) {
  return ReportsNotifier(
    ref.watch(reportsServiceProvider),
    ref.watch(apiClientProvider).dio,
  );
});

import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../data/scan_service.dart';

final scanServiceProvider = Provider<ScanService>((ref) => ScanService());

final scanResultProvider =
    StateProvider<Map<String, dynamic>?>((ref) => null);

final isLoadingProvider = StateProvider<bool>((ref) => false);

final scanErrorProvider = StateProvider<String?>((ref) => null);

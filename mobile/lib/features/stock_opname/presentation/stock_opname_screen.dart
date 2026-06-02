import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/screen_header.dart';
import 'opname_session_provider.dart';

/// Entry screen: cek sesi aktif, lalu buat sesi baru khusus Gudang Pusat.
class StockOpnameScreen extends ConsumerStatefulWidget {
  const StockOpnameScreen({super.key});

  @override
  ConsumerState<StockOpnameScreen> createState() => _StockOpnameScreenState();
}

class _StockOpnameScreenState extends ConsumerState<StockOpnameScreen> {
  bool _isLoading = true;
  String? _errorMessage;
  String? _warehouseId;
  String _warehouseName = 'Gudang Pusat';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _bootstrap());
  }

  Future<void> _bootstrap() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final sessionId = await fetchActiveOpnameSessionId(ref.read);
    if (!mounted) return;

    if (sessionId != null) {
      context.pushReplacement('/stock/stock-opname/session/$sessionId');
      return;
    }

    await _loadCentralWarehouse();
  }

  Future<void> _loadCentralWarehouse() async {
    try {
      final dio = ref.read(apiClientProvider).dio;
      final res = await dio.get('/locations/central-warehouse');
      final data = res.data['data'];
      if (data is! Map) {
        throw Exception('Gudang pusat tidak ditemukan');
      }
      if (!mounted) return;
      setState(() {
        _warehouseId = data['id']?.toString();
        _warehouseName = data['location_name']?.toString() ?? 'Gudang Pusat';
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _warehouseId = null;
        _errorMessage = 'Gagal memuat data gudang pusat.';
      });
    }
  }

  Future<void> _startCentralWarehouseSession() async {
    if (_warehouseId == null || _warehouseId!.isEmpty) {
      setState(() => _errorMessage = 'Gudang pusat tidak ditemukan.');
      return;
    }
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final sessionId = await createOpnameSession(ref.read, _warehouseId!);
    if (!mounted) return;

    if (sessionId == null) {
      setState(() {
        _isLoading = false;
        _errorMessage = 'Gagal membuat sesi opname. Coba lagi.';
      });
      return;
    }

    context.pushReplacement('/stock/stock-opname/session/$sessionId');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: AppColors.background,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.voidBlack),
          onPressed: () => context.pop(),
        ),
      ),
      body: SafeArea(
        top: false,
        child: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const ScreenHeader(
                      backLabel: 'STOK',
                      title: 'Stok Opname',
                    ),
                    const SizedBox(height: 24),
                    Text(
                      'Mulai sesi audit stok gudang pusat, '
                      'lalu scan item satu per satu.',
                      style: AppTextStyles.cardSubtitle.copyWith(fontSize: 13),
                    ),
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.card,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: AppColors.border),
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.warehouse_outlined,
                            color: AppColors.voidBlack,
                            size: 20,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'LOKASI OPNAME',
                                  style: AppTextStyles.tabLabel.copyWith(
                                    color: AppColors.muted,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  _warehouseName,
                                  style: AppTextStyles.cardTitle.copyWith(
                                    fontSize: 14,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 32),
                    SizedBox(
                      height: 52,
                      child: ElevatedButton(
                        onPressed: _startCentralWarehouseSession,
                        child: Text(
                          'MULAI SESI BARU',
                          style: AppTextStyles.buttonPrimary,
                        ),
                      ),
                    ),
                    if (_errorMessage != null) ...[
                      const SizedBox(height: 16),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.danger.withValues(alpha: 0.08),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                              color: AppColors.danger.withValues(alpha: 0.3)),
                        ),
                        child: Text(
                          _errorMessage!,
                          style: const TextStyle(
                              color: AppColors.danger, fontSize: 13),
                        ),
                      ),
                      const SizedBox(height: 8),
                      OutlinedButton(
                        onPressed: _bootstrap,
                        child: const Text('COBA LAGI'),
                      ),
                    ],
                  ],
                ),
              ),
      ),
    );
  }
}

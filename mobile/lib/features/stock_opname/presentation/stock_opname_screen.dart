import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/screen_header.dart';
import 'opname_session_provider.dart';

/// Entry screen: cek sesi aktif, lalu tampilkan dialog pilih lokasi
/// sebelum membuat sesi baru.
class StockOpnameScreen extends ConsumerStatefulWidget {
  const StockOpnameScreen({super.key});

  @override
  ConsumerState<StockOpnameScreen> createState() => _StockOpnameScreenState();
}

class _StockOpnameScreenState extends ConsumerState<StockOpnameScreen> {
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _bootstrap());
  }

  // ── Bootstrap: cek sesi aktif dulu ─────────────────────────────────────────
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

    setState(() => _isLoading = false);
  }

  // ── Tampilkan dialog pilih lokasi ───────────────────────────────────────────
  Future<void> _pickLocationAndStart() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    // Fetch daftar lokasi sesuai role
    final auth = ref.read(authProvider);
    final role = auth.role ?? '';
    List<Map<String, dynamic>> locations = [];

    try {
      locations = await _fetchLocations(role);
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = 'Gagal memuat daftar lokasi';
      });
      return;
    }

    if (!mounted) return;
    setState(() => _isLoading = false);

    if (locations.isEmpty) {
      setState(() {
        _errorMessage = role == 'sales'
            ? 'Role Sales tidak dapat melakukan stok opname'
            : 'Tidak ada lokasi tersedia. Hubungi Owner/Admin.';
      });
      return;
    }

    // Langsung buat sesi jika hanya 1 lokasi
    if (locations.length == 1) {
      await _createSession(locations.first['id'].toString());
      return;
    }

    // Tampilkan dialog pilih lokasi jika > 1
    if (!mounted) return;
    final selected = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.card,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) => _LocationPickerSheet(locations: locations),
    );

    if (selected == null || !mounted) return;
    await _createSession(selected);
  }

  // ── Fetch lokasi sesuai role ────────────────────────────────────────────────
  Future<List<Map<String, dynamic>>> _fetchLocations(String role) async {
    final dio = ref.read(apiClientProvider).dio;

    // Owner & Admin → semua lokasi aktif
    if (role == 'owner' || role == 'admin') {
      final res = await dio.get('/locations');
      final data = res.data['data'];
      if (data is List) {
        return data
            .whereType<Map>()
            .map((e) => {
                  'id': e['id']?.toString() ?? '',
                  'name': e['location_name']?.toString() ?? '',
                })
            .where((e) => e['id']!.isNotEmpty)
            .toList();
      }
      return [];
    }

    // Admin Gudang, PIC Bazar → lokasi dari assigned_locations
    final user = ref.read(authProvider).user;
    final assigned = user?['assigned_locations'];
    if (assigned is List && assigned.isNotEmpty) {
      return assigned
          .whereType<Map>()
          .map((e) => {
                'id': e['id']?.toString() ?? '',
                'name': (e['name'] ?? e['location_name'])?.toString() ?? '',
              })
          .where((e) => e['id']!.isNotEmpty)
          .toList();
    }

    return [];
  }

  // ── Buat sesi dengan lokasi terpilih ───────────────────────────────────────
  Future<void> _createSession(String locationId) async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final sessionId = await createOpnameSession(ref.read, locationId);
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
                      'Mulai sesi audit stok. Pilih lokasi terlebih dahulu, '
                      'lalu scan item satu per satu.',
                      style: AppTextStyles.cardSubtitle.copyWith(fontSize: 13),
                    ),
                    const SizedBox(height: 32),
                    SizedBox(
                      height: 52,
                      child: ElevatedButton.icon(
                        onPressed: _pickLocationAndStart,
                        icon: const Icon(Icons.add_location_alt_outlined,
                            size: 18),
                        label: Text(
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
                    ],
                  ],
                ),
              ),
      ),
    );
  }
}

// ─── Bottom sheet pilih lokasi ────────────────────────────────────────────────

class _LocationPickerSheet extends StatefulWidget {
  final List<Map<String, dynamic>> locations;

  const _LocationPickerSheet({required this.locations});

  @override
  State<_LocationPickerSheet> createState() => _LocationPickerSheetState();
}

class _LocationPickerSheetState extends State<_LocationPickerSheet> {
  String? _selected;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: 20 + MediaQuery.of(context).viewInsets.bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Header
          Row(
            children: [
              Text(
                'PILIH LOKASI OPNAME',
                style: AppTextStyles.sectionLabel,
              ),
              const Spacer(),
              IconButton(
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.close, size: 20),
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            'Pilih lokasi yang akan diaudit stoknya',
            style: AppTextStyles.cardSubtitle.copyWith(fontSize: 12),
          ),
          const SizedBox(height: 16),

          // List lokasi
          ConstrainedBox(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.of(context).size.height * 0.45,
            ),
            child: ListView.separated(
              shrinkWrap: true,
              itemCount: widget.locations.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (ctx, i) {
                final loc = widget.locations[i];
                final id = loc['id'].toString();
                final name = loc['name']?.toString() ?? '—';
                final isSelected = _selected == id;

                return GestureDetector(
                  onTap: () => setState(() => _selected = id),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 150),
                    padding: const EdgeInsets.symmetric(
                        horizontal: 14, vertical: 14),
                    decoration: BoxDecoration(
                      color: isSelected
                          ? AppColors.voidBlack
                          : AppColors.background,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: isSelected
                            ? AppColors.voidBlack
                            : AppColors.border,
                      ),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          Icons.location_on_outlined,
                          size: 18,
                          color: isSelected ? Colors.white : AppColors.muted,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            name,
                            style: AppTextStyles.cardTitle.copyWith(
                              fontSize: 14,
                              color: isSelected
                                  ? Colors.white
                                  : AppColors.voidBlack,
                            ),
                          ),
                        ),
                        if (isSelected)
                          const Icon(Icons.check_circle,
                              size: 18, color: Colors.white),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),

          const SizedBox(height: 16),

          // Tombol konfirmasi
          SizedBox(
            height: 52,
            child: ElevatedButton(
              onPressed: _selected != null
                  ? () => Navigator.pop(context, _selected)
                  : null,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.voidBlack,
                disabledBackgroundColor:
                    AppColors.voidBlack.withValues(alpha: 0.3),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: Text(
                'MULAI OPNAME',
                style: AppTextStyles.buttonPrimary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

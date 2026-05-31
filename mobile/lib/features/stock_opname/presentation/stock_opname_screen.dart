import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/utils/location_helpers.dart';
import '../../../core/widgets/screen_header.dart';
import 'opname_session_provider.dart';

/// Entry screen: resume active session or start a new one, then open [OpnameSessionScreen].
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

  Future<void> _startNewSession() async {
    final locId = assignedLocationId(ref.read(authProvider).user);
    if (locId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Lokasi belum tersedia. Login dengan akun yang valid.',
          ),
        ),
      );
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final sessionId = await createOpnameSession(ref.read, locId);
    if (!mounted) return;

    if (sessionId == null) {
      setState(() {
        _isLoading = false;
        _errorMessage = 'Gagal membuat sesi opname';
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
                      'Mulai sesi audit stok untuk lokasi Anda. '
                      'Setelah sesi dibuat, scan item satu per satu.',
                      style: AppTextStyles.cardSubtitle.copyWith(fontSize: 13),
                    ),
                    const SizedBox(height: 32),
                    SizedBox(
                      height: 52,
                      child: ElevatedButton(
                        onPressed: _startNewSession,
                        child: Text(
                          'MULAI SESI BARU',
                          style: AppTextStyles.buttonPrimary,
                        ),
                      ),
                    ),
                    if (_errorMessage != null) ...[
                      const SizedBox(height: 16),
                      Text(
                        _errorMessage!,
                        style: const TextStyle(color: AppColors.danger),
                      ),
                    ],
                  ],
                ),
              ),
      ),
    );
  }
}

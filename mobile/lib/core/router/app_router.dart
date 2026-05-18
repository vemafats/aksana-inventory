import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
// import '../auth/auth_provider.dart'; // TODO: re-enable with redirect
import '../widgets/main_scaffold.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/scan/presentation/scan_screen.dart';
import '../../features/stock_in/presentation/stock_in_screen.dart';

final routerProvider = Provider<GoRouter>((ref) {
  // final auth = ref.watch(authProvider); // TODO: re-enable with redirect
  return GoRouter(
    initialLocation: '/scan',
    redirect: (context, state) {
      // TODO: re-enable auth redirect after backend connection
      // final loggedIn = auth.isAuthenticated;
      // final goingLogin = state.matchedLocation == '/login';
      // if (!loggedIn && !goingLogin) return '/login';
      // if (loggedIn && goingLogin) return '/scan';
      return null;
    },
    routes: [
      GoRoute(
        path: '/login',
        builder: (_, __) => const LoginScreen(),
      ),
      ShellRoute(
        builder: (_, __, child) => MainScaffold(child: child),
        routes: [
          GoRoute(
            path: '/scan',
            builder: (context, state) {
              final selectionMode = state.uri.queryParameters['mode'] == 'select';
              return ScanScreen(selectionMode: selectionMode);
            },
          ),
          GoRoute(
            path: '/sales',
            builder: (_, __) => const _Placeholder('JUAL · M5-T5'),
          ),
          GoRoute(
            path: '/stock',
            builder: (_, __) => const _StockHubScreen(),
            routes: [
              GoRoute(
                path: 'stock-in',
                builder: (_, __) => const StockInScreen(),
              ),
            ],
          ),
          GoRoute(
            path: '/reports',
            builder: (_, __) => const _Placeholder('STAT · M5-T6'),
          ),
        ],
      ),
    ],
  );
});

class _StockHubScreen extends StatelessWidget {
  const _StockHubScreen();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFEDF1F3),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Stok',
                style: TextStyle(
                  fontFamily: GoogleFonts.inter().fontFamily,
                  fontSize: 24,
                  fontWeight: FontWeight.w700,
                  color: const Color(0xFF070D1E),
                ),
              ),
              const SizedBox(height: 24),
              SizedBox(
                height: 52,
                child: ElevatedButton(
                  onPressed: () => context.push('/stock/stock-in'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF070D1E),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: Text(
                    'Barang Masuk',
                    style: GoogleFonts.inter(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1.5,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Placeholder extends StatelessWidget {
  final String label;
  const _Placeholder(this.label);
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFFEDF1F3),
    body: Center(child: Text(label,
      style: TextStyle(
          fontFamily: GoogleFonts.inter().fontFamily,
          fontSize: 16,
          color: const Color(0xFF49586B)))),
  );
}

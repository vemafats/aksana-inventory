import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import '../auth/auth_provider.dart';
import '../widgets/main_scaffold.dart';
import '../../features/auth/presentation/login_screen.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final auth = ref.watch(authProvider);
  return GoRouter(
    initialLocation: '/scan',
    redirect: (context, state) {
      final loggedIn = auth.isAuthenticated;
      final goingLogin = state.matchedLocation == '/login';
      if (!loggedIn && !goingLogin) return '/login';
      if (loggedIn && goingLogin) return '/scan';
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
          GoRoute(path: '/scan',
            builder: (_, __) => const _Placeholder('SCAN · M5-T3')),
          GoRoute(path: '/sales',
            builder: (_, __) => const _Placeholder('JUAL · M5-T5')),
          GoRoute(path: '/stock',
            builder: (_, __) => const _Placeholder('STOK · M5-T6')),
          GoRoute(path: '/reports',
            builder: (_, __) => const _Placeholder('STAT · M5-T6')),
        ],
      ),
    ],
  );
});

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

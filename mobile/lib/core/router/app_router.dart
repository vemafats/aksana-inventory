import 'package:go_router/go_router.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../auth/auth_provider.dart';
import '../widgets/main_scaffold.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/scan/presentation/scan_screen.dart';
import '../../features/sales/presentation/sales_screen.dart';
import '../../features/stock_in/presentation/stock_in_screen.dart';
import '../../features/stock_check/presentation/stock_check_screen.dart';
import '../../features/stock_check/presentation/stock_menu_screen.dart';
import '../../features/stock_opname/presentation/stock_opname_screen.dart';
import '../../features/return_stock/presentation/return_stock_screen.dart';
import '../../features/reports/presentation/reports_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';

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
          GoRoute(
            path: '/scan',
            builder: (context, state) {
              final selectionMode =
                  state.uri.queryParameters['mode'] == 'select';
              return ScanScreen(selectionMode: selectionMode);
            },
          ),
          GoRoute(
            path: '/sales',
            builder: (_, __) => const SalesScreen(),
          ),
          GoRoute(
            path: '/stock',
            builder: (_, __) => const StockMenuScreen(),
            routes: [
              GoRoute(
                path: 'stock-in',
                builder: (_, __) => const StockInScreen(),
              ),
              GoRoute(
                path: 'stock-opname',
                builder: (_, __) => const StockOpnameScreen(),
              ),
              GoRoute(
                path: 'return',
                builder: (_, __) => const ReturnStockScreen(),
              ),
              GoRoute(
                path: 'check',
                builder: (context, state) => StockCheckScreen(
                  itemData: state.extra is Map<String, dynamic>
                      ? state.extra as Map<String, dynamic>
                      : null,
                ),
              ),
            ],
          ),
          GoRoute(
            path: '/reports',
            builder: (_, __) => const ReportsScreen(),
          ),
          GoRoute(
            path: '/profile',
            builder: (_, __) => const ProfileScreen(),
          ),
        ],
      ),
    ],
  );
});

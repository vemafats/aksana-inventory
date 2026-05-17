import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../api/api_client.dart';

final apiClientProvider = Provider<ApiClient>((ref) => ApiClient());

class AuthState {
  final bool isAuthenticated;
  final String? token;
  final Map<String, dynamic>? user;
  final bool isLoading;
  final String? errorMessage;

  const AuthState({
    this.isAuthenticated = false,
    this.token,
    this.user,
    this.isLoading = false,
    this.errorMessage,
  });

  String? get role => user?['role'] as String?;
  String? get name => user?['name'] as String?;
  String? get userId => user?['id'] as String?;

  AuthState copyWith({
    bool? isAuthenticated,
    String? token,
    Map<String, dynamic>? user,
    bool? isLoading,
    String? errorMessage,
  }) => AuthState(
    isAuthenticated: isAuthenticated ?? this.isAuthenticated,
    token: token ?? this.token,
    user: user ?? this.user,
    isLoading: isLoading ?? this.isLoading,
    errorMessage: errorMessage,
  );
}

class AuthNotifier extends StateNotifier<AuthState> {
  final ApiClient _api;
  AuthNotifier(this._api) : super(const AuthState()) {
    _init();
  }

  Future<void> _init() async {
    final token = await _api.getToken();
    if (token != null) {
      state = state.copyWith(isAuthenticated: true, token: token);
    }
  }

  Future<bool> login(String email, String password) async {
    state = state.copyWith(isLoading: true, errorMessage: null);
    try {
      final res = await _api.dio.post('/login',
          data: {'email': email, 'password': password});
      final data = res.data['data'];
      await _api.setToken(data['token']);
      state = state.copyWith(
        isAuthenticated: true,
        token: data['token'],
        user: Map<String, dynamic>.from(data['user']),
        isLoading: false,
      );
      return true;
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: 'Email atau password salah',
      );
      return false;
    }
  }

  Future<void> logout() async {
    try { await _api.dio.post('/logout'); } catch (_) {}
    await _api.clearToken();
    state = const AuthState();
  }
}

final authProvider =
    StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  return AuthNotifier(ref.watch(apiClientProvider));
});

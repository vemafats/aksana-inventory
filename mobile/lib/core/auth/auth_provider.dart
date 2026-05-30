import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../api/api_client.dart';

final apiClientProvider = Provider<ApiClient>((ref) => ApiClient());

class AuthState {
  final bool isAuthenticated;
  final String? token;
  final Map<String, dynamic>? user;
  final bool isLoading;
  final String? errorMessage;
  final String? locationId;
  final String? locationName;

  const AuthState({
    this.isAuthenticated = false,
    this.token,
    this.user,
    this.isLoading = false,
    this.errorMessage,
    this.locationId,
    this.locationName,
  });

  String? get role => user?['role'] as String?;
  String? get name => user?['name'] as String?;
  String? get email => user?['email'] as String?;
  String? get userId => user?['id'] as String?;
  String? get nik => user?['nik'] as String?;
  String? get position => user?['position'] as String?;

  AuthState copyWith({
    bool? isAuthenticated,
    String? token,
    Map<String, dynamic>? user,
    bool? isLoading,
    String? errorMessage,
    String? locationId,
    String? locationName,
  }) => AuthState(
    isAuthenticated: isAuthenticated ?? this.isAuthenticated,
    token: token ?? this.token,
    user: user ?? this.user,
    isLoading: isLoading ?? this.isLoading,
    errorMessage: errorMessage,
    locationId: locationId ?? this.locationId,
    locationName: locationName ?? this.locationName,
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
      await fetchProfile();
    }
  }

  Future<void> fetchProfile() async {
    try {
      final res = await _api.dio.get('/me');
      final data = res.data['data'];
      if (data is! Map) return;
      final profile = Map<String, dynamic>.from(data);
      state = state.copyWith(
        user: profile,
        isAuthenticated: true,
        locationId: profile['location_id']?.toString(),
        locationName: profile['location_name']?.toString(),
      );
    } catch (_) {}
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
      await fetchProfile();
      return true;
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: _loginErrorMessage(e),
      );
      return false;
    }
  }

  String _loginErrorMessage(Object e) {
    if (e is DioException) {
      if (e.response?.statusCode == 401) {
        return 'Email atau password salah';
      }
      if (_isNetworkError(e)) {
        return 'Tidak dapat terhubung ke server. '
            'Periksa koneksi internet Anda.';
      }
    }
    return 'Terjadi kesalahan. Coba lagi.';
  }

  bool _isNetworkError(DioException e) {
    return e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.sendTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.connectionError;
  }

  void setActiveLocation(String? id, String? name) {
    state = state.copyWith(locationId: id, locationName: name);
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

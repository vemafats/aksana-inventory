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
  final String? locationType;
  final String? nik;
  final String? position;

  const AuthState({
    this.isAuthenticated = false,
    this.token,
    this.user,
    this.isLoading = false,
    this.errorMessage,
    this.locationId,
    this.locationName,
    this.locationType,
    this.nik,
    this.position,
  });

  String? get role => user?['role'] as String?;
  String? get name => user?['name'] as String?;
  String? get email => user?['email'] as String?;
  String? get userId => user?['id'] as String?;

  AuthState copyWith({
    bool? isAuthenticated,
    String? token,
    Map<String, dynamic>? user,
    bool? isLoading,
    String? errorMessage,
    String? locationId,
    String? locationName,
    String? locationType,
    String? nik,
    String? position,
  }) => AuthState(
        isAuthenticated: isAuthenticated ?? this.isAuthenticated,
        token: token ?? this.token,
        user: user ?? this.user,
        isLoading: isLoading ?? this.isLoading,
        errorMessage: errorMessage,
        locationId: locationId ?? this.locationId,
        locationName: locationName ?? this.locationName,
        locationType: locationType ?? this.locationType,
        nik: nik ?? this.nik,
        position: position ?? this.position,
      );
}

class AuthNotifier extends StateNotifier<AuthState> {
  final ApiClient _api;
  AuthNotifier(this._api) : super(const AuthState()) {
    _init();
  }

  Future<void> _init() async {
    final token = await _api.getToken();
    if (token == null) return;

    try {
      final meRes = await _api.dio.get('/me');
      final meData = meRes.data['data'];
      if (meData is! Map) {
        state = state.copyWith(isAuthenticated: true, token: token);
        return;
      }
      _applyMeData(
        Map<String, dynamic>.from(meData),
        token: token,
      );
    } catch (_) {
      state = state.copyWith(isAuthenticated: true, token: token);
    }
  }

  void _applyMeData(Map<String, dynamic> meData, {String? token}) {
    state = state.copyWith(
      isAuthenticated: true,
      token: token ?? state.token,
      user: meData,
      locationId: meData['location_id']?.toString(),
      locationName: meData['location_name']?.toString(),
      locationType: meData['location_type']?.toString(),
      nik: meData['nik']?.toString(),
      position: meData['position']?.toString(),
    );
  }

  Future<void> fetchProfile() async {
    try {
      final res = await _api.dio.get('/me');
      final data = res.data['data'];
      if (data is! Map) return;
      _applyMeData(Map<String, dynamic>.from(data));
    } catch (_) {}
  }

  Future<bool> login(String email, String password) async {
    state = state.copyWith(isLoading: true, errorMessage: null);
    try {
      final res = await _api.dio.post('/login',
          data: {'email': email, 'password': password});
      final data = res.data['data'];
      await _api.setToken(data['token']);

      final meRes = await _api.dio.get('/me');
      final meData = meRes.data['data'];
      if (meData is! Map) {
        state = state.copyWith(
          isAuthenticated: true,
          token: data['token'],
          user: Map<String, dynamic>.from(data['user']),
          isLoading: false,
        );
        return true;
      }

      final profile = Map<String, dynamic>.from(meData);
      state = state.copyWith(
        isAuthenticated: true,
        token: data['token'],
        user: profile,
        locationId: profile['location_id']?.toString(),
        locationName: profile['location_name']?.toString(),
        locationType: profile['location_type']?.toString(),
        nik: profile['nik']?.toString(),
        position: profile['position']?.toString(),
        isLoading: false,
      );
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

  void setActiveLocation(String? id, String? name, {String? type}) {
    state = state.copyWith(
      locationId: id,
      locationName: name,
      locationType: type,
    );
  }

  Future<void> logout() async {
    try {
      await _api.dio.post('/logout');
    } catch (_) {}
    await _api.clearToken();
    state = const AuthState();
  }
}

final authProvider =
    StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  return AuthNotifier(ref.watch(apiClientProvider));
});

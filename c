[1mdiff --git a/mobile/lib/core/auth/auth_provider.dart b/mobile/lib/core/auth/auth_provider.dart[m
[1mindex 729f5db5..4d9afcd9 100644[m
[1m--- a/mobile/lib/core/auth/auth_provider.dart[m
[1m+++ b/mobile/lib/core/auth/auth_provider.dart[m
[36m@@ -1,9 +1,12 @@[m
 import 'package:dio/dio.dart';[m
 import 'package:flutter_riverpod/flutter_riverpod.dart';[m
 import '../api/api_client.dart';[m
[32m+[m[32mimport '../opname/active_opname_provider.dart';[m
 [m
 final apiClientProvider = Provider<ApiClient>((ref) => ApiClient());[m
 [m
[32m+[m[32mtypedef AuthSideEffect = Future<void> Function();[m
[32m+[m
 class AuthState {[m
   final bool isAuthenticated;[m
   final String? token;[m
[36m@@ -61,7 +64,11 @@[m [mclass AuthState {[m
 [m
 class AuthNotifier extends StateNotifier<AuthState> {[m
   final ApiClient _api;[m
[31m-  AuthNotifier(this._api) : super(const AuthState()) {[m
[32m+[m[32m  final AuthSideEffect? _onAuthenticated;[m
[32m+[m
[32m+[m[32m  AuthNotifier(this._api, {AuthSideEffect? onAuthenticated})[m
[32m+[m[32m      : _onAuthenticated = onAuthenticated,[m
[32m+[m[32m        super(const AuthState()) {[m
     _init();[m
   }[m
 [m
[36m@@ -80,6 +87,7 @@[m [mclass AuthNotifier extends StateNotifier<AuthState> {[m
         Map<String, dynamic>.from(meData),[m
         token: token,[m
       );[m
[32m+[m[32m      await _onAuthenticated?.call();[m
     } catch (_) {[m
       state = state.copyWith(isAuthenticated: true, token: token);[m
     }[m
[36m@@ -124,6 +132,7 @@[m [mclass AuthNotifier extends StateNotifier<AuthState> {[m
           user: Map<String, dynamic>.from(data['user']),[m
           isLoading: false,[m
         );[m
[32m+[m[32m        await _onAuthenticated?.call();[m
         return true;[m
       }[m
 [m
[36m@@ -139,6 +148,7 @@[m [mclass AuthNotifier extends StateNotifier<AuthState> {[m
         position: profile['position']?.toString(),[m
         isLoading: false,[m
       );[m
[32m+[m[32m      await _onAuthenticated?.call();[m
       return true;[m
     } catch (e) {[m
       state = state.copyWith([m
[36m@@ -188,5 +198,10 @@[m [mclass AuthNotifier extends StateNotifier<AuthState> {[m
 [m
 final authProvider =[m
     StateNotifierProvider<AuthNotifier, AuthState>((ref) {[m
[31m-  return AuthNotifier(ref.watch(apiClientProvider));[m
[32m+[m[32m  return AuthNotifier([m
[32m+[m[32m    ref.watch(apiClientProvider),[m
[32m+[m[32m    onAuthenticated: () async {[m
[32m+[m[32m      await refreshActiveOpname(ref.read);[m
[32m+[m[32m    },[m
[32m+[m[32m  );[m
 });[m

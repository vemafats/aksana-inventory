import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/auth_provider.dart';
import 'active_event.dart';

class ActiveEventNotifier extends StateNotifier<ActiveEventState> {
  ActiveEventNotifier(this._ref) : super(const ActiveEventState());

  final Ref _ref;

  Future<void> fetchMyActiveEvents(Dio dio) async {
    state = state.copyWith(isLoading: true);
    try {
      final res = await dio.get(
        '/events/my-active',
        options: Options(
          receiveTimeout: const Duration(seconds: 10),
          sendTimeout: const Duration(seconds: 10),
        ),
      );
      final raw = res.data['data'];
      final events = <ActiveEvent>[];
      if (raw is List) {
        for (final item in raw) {
          if (item is Map) {
            events.add(ActiveEvent.fromJson(Map<String, dynamic>.from(item)));
          }
        }
      }

      state = ActiveEventState(events: events, isLoading: false);

      if (events.length == 1) {
        selectEvent(events.first);
      }
    } on DioException catch (e) {
      state = state.copyWith(isLoading: false);
      if (e.response?.statusCode == 401) {
        await _ref.read(apiClientProvider).clearToken();
      }
    } catch (_) {
      state = state.copyWith(isLoading: false);
    }
  }

  void selectEvent(ActiveEvent event) {
    state = state.copyWith(selectedEvent: event);
    _ref.read(authProvider.notifier).setActiveLocationFromEvent(event);
  }

  void clearEvents() {
    state = const ActiveEventState();
  }
}

final activeEventNotifierProvider =
    StateNotifierProvider<ActiveEventNotifier, ActiveEventState>((ref) {
  return ActiveEventNotifier(ref);
});

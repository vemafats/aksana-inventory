import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'active_event.dart';

/// Prevents re-fetching events on every tab switch in [MainScaffold].
final eventsFetchInitiatedProvider = StateProvider<bool>((ref) => false);

class ActiveEventNotifier extends StateNotifier<ActiveEventState> {
  ActiveEventNotifier() : super(const ActiveEventState());

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
    } on DioException catch (_) {
      state = state.copyWith(isLoading: false);
    } catch (_) {
      state = state.copyWith(isLoading: false);
    }
  }

  void selectEvent(ActiveEvent event) {
    state = state.copyWith(selectedEvent: event);
  }

  void clearEvents() {
    state = const ActiveEventState();
  }
}

final activeEventNotifierProvider =
    StateNotifierProvider<ActiveEventNotifier, ActiveEventState>((ref) {
  return ActiveEventNotifier();
});

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/auth/auth_provider.dart';
import '../data/browse_items_service.dart';

class BrowseItemsState {
  final List<Map<String, dynamic>> items;
  final int currentPage;
  final int lastPage;
  final bool isLoading;
  final bool isLoadingMore;
  final String? errorMessage;
  final String search;

  const BrowseItemsState({
    this.items = const [],
    this.currentPage = 0,
    this.lastPage = 1,
    this.isLoading = false,
    this.isLoadingMore = false,
    this.errorMessage,
    this.search = '',
  });

  bool get hasMore => currentPage < lastPage;

  BrowseItemsState copyWith({
    List<Map<String, dynamic>>? items,
    int? currentPage,
    int? lastPage,
    bool? isLoading,
    bool? isLoadingMore,
    String? errorMessage,
    String? search,
    bool clearError = false,
  }) =>
      BrowseItemsState(
        items: items ?? this.items,
        currentPage: currentPage ?? this.currentPage,
        lastPage: lastPage ?? this.lastPage,
        isLoading: isLoading ?? this.isLoading,
        isLoadingMore: isLoadingMore ?? this.isLoadingMore,
        errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
        search: search ?? this.search,
      );
}

class BrowseItemsNotifier extends StateNotifier<BrowseItemsState> {
  BrowseItemsNotifier(this._ref) : super(const BrowseItemsState());

  final Ref _ref;
  final _service = BrowseItemsService();

  Future<void> loadItems() async {
    state = state.copyWith(
      isLoading: true,
      clearError: true,
      items: [],
      currentPage: 0,
    );
    try {
      final dio = _ref.read(apiClientProvider).dio;
      final result = await _service.fetchItems(
        dio,
        page: 1,
        search: state.search.isEmpty ? null : state.search,
      );
      state = state.copyWith(
        items: result.items,
        currentPage: result.currentPage,
        lastPage: result.lastPage,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: 'Gagal memuat katalog',
      );
    }
  }

  Future<void> loadMore() async {
    if (state.isLoadingMore || state.isLoading || !state.hasMore) return;

    state = state.copyWith(isLoadingMore: true, clearError: true);
    try {
      final dio = _ref.read(apiClientProvider).dio;
      final nextPage = state.currentPage + 1;
      final result = await _service.fetchItems(
        dio,
        page: nextPage,
        search: state.search.isEmpty ? null : state.search,
      );
      state = state.copyWith(
        items: [...state.items, ...result.items],
        currentPage: result.currentPage,
        lastPage: result.lastPage,
        isLoadingMore: false,
      );
    } catch (e) {
      state = state.copyWith(
        isLoadingMore: false,
        errorMessage: 'Gagal memuat halaman berikutnya',
      );
    }
  }

  Future<void> setSearch(String query) async {
    state = state.copyWith(search: query);
    await loadItems();
  }
}

final browseItemsProvider =
    StateNotifierProvider<BrowseItemsNotifier, BrowseItemsState>((ref) {
  return BrowseItemsNotifier(ref);
});

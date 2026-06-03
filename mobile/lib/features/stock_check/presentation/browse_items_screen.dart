import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../data/browse_items_service.dart';
import 'browse_items_provider.dart';

class BrowseItemsScreen extends ConsumerStatefulWidget {
  const BrowseItemsScreen({super.key});

  @override
  ConsumerState<BrowseItemsScreen> createState() => _BrowseItemsScreenState();
}

class _BrowseItemsScreenState extends ConsumerState<BrowseItemsScreen> {
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();
  Timer? _searchDebounce;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(browseItemsProvider.notifier).loadItems();
    });
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (!_scrollController.hasClients) return;
    final max = _scrollController.position.maxScrollExtent;
    if (_scrollController.position.pixels >= max - 200) {
      ref.read(browseItemsProvider.notifier).loadMore();
    }
  }

  void _onSearchChanged(String value) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 400), () {
      ref.read(browseItemsProvider.notifier).setSearch(value.trim());
    });
  }

  String _abbreviation(String name) {
    final parts = name.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty) return '—';
    if (parts.length == 1) {
      return parts.first
          .substring(0, parts.first.length.clamp(0, 2))
          .toUpperCase();
    }
    return '${parts.first[0]}${parts[1][0]}'.toUpperCase();
  }

  Color _parseColor(String hex) {
    var value = hex.replaceFirst('#', '');
    if (value.length == 6) value = 'FF$value';
    final intVal = int.tryParse(value, radix: 16);
    if (intVal == null) return AppColors.muted;
    return Color(intVal);
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(browseItemsProvider);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: AppColors.background,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.voidBlack),
          onPressed: () => context.pop(),
        ),
        title: Text(
          'Browse Item',
          style: GoogleFonts.inter(
            fontSize: 16,
            fontWeight: FontWeight.w700,
            color: AppColors.voidBlack,
          ),
        ),
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              decoration: InputDecoration(
                hintText: 'Cari nama atau barcode...',
                hintStyle: GoogleFonts.inter(
                  fontSize: 14,
                  color: AppColors.muted,
                ),
                prefixIcon: const Icon(Icons.search, color: AppColors.muted),
                filled: true,
                fillColor: AppColors.card,
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                  borderSide: const BorderSide(color: AppColors.border),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                  borderSide: const BorderSide(color: AppColors.border),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                  borderSide:
                      const BorderSide(color: AppColors.voidBlack, width: 1),
                ),
              ),
            ),
          ),
          if (state.errorMessage != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Text(
                state.errorMessage!,
                style: GoogleFonts.inter(
                  fontSize: 12,
                  color: AppColors.danger,
                ),
              ),
            ),
          Expanded(
            child: state.isLoading && state.items.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : state.items.isEmpty
                    ? Center(
                        child: Text(
                          'Tidak ada item ditemukan',
                          style: AppTextStyles.cardSubtitle,
                        ),
                      )
                    : ListView.builder(
                        controller: _scrollController,
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                        itemCount: state.items.length +
                            (state.isLoadingMore ? 1 : 0),
                        itemBuilder: (context, index) {
                          if (index >= state.items.length) {
                            return const Padding(
                              padding: EdgeInsets.all(16),
                              child: Center(
                                child: CircularProgressIndicator(),
                              ),
                            );
                          }

                          final item = state.items[index];
                          final name =
                              item['item_name']?.toString() ?? '—';
                          final barcode =
                              item['barcode']?.toString() ?? '—';
                          final category = item['category'] is Map
                              ? (item['category'] as Map)['name']
                                      ?.toString() ??
                                  '—'
                              : '—';
                          final color = item['color'] is Map
                              ? item['color'] as Map
                              : null;
                          final hex =
                              color?['hex_code']?.toString() ?? '#49586B';
                          final qty =
                              BrowseItemsService.totalAvailableQty(item);

                          return _BrowseItemTile(
                            abbreviation: _abbreviation(name),
                            color: _parseColor(hex),
                            name: name,
                            subtitle: '$barcode · $category',
                            qty: qty,
                            onTap: () {
                              final payload =
                                  BrowseItemsService.itemForStockCheck(item);
                              context.push('/stock/check', extra: payload);
                            },
                          );
                        },
                      ),
          ),
        ],
      ),
    );
  }
}

class _BrowseItemTile extends StatelessWidget {
  final String abbreviation;
  final Color color;
  final String name;
  final String subtitle;
  final int qty;
  final VoidCallback onTap;

  const _BrowseItemTile({
    required this.abbreviation,
    required this.color,
    required this.name,
    required this.subtitle,
    required this.qty,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: ListTile(
        onTap: onTap,
        leading: Container(
          width: 42,
          height: 42,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Text(
            abbreviation,
            style: GoogleFonts.inter(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: Colors.white,
            ),
          ),
        ),
        title: Text(
          name,
          style: GoogleFonts.inter(
            fontSize: 14,
            fontWeight: FontWeight.w700,
            color: AppColors.voidBlack,
          ),
        ),
        subtitle: Text(subtitle, style: AppTextStyles.monoMuted),
        trailing: Text(
          AppColors.formatQty(qty),
          style: AppTextStyles.monoBold.copyWith(
            fontSize: 14,
            color: AppColors.qtyColor(qty),
          ),
        ),
      ),
    );
  }
}

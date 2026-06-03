import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/auth/auth_provider.dart';
import '../../../core/event/active_event_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/screen_header.dart';
import 'return_stock_provider.dart';

class ReturnStockScreen extends ConsumerWidget {
  const ReturnStockScreen({super.key});

  Future<void> _showEventPicker(BuildContext context, WidgetRef ref) async {
    final dio = ref.read(apiClientProvider).dio;
    var eventState = ref.read(activeEventNotifierProvider);

    if (eventState.events.isEmpty && !eventState.isLoading) {
      await ref
          .read(activeEventNotifierProvider.notifier)
          .fetchMyActiveEvents(dio);
      eventState = ref.read(activeEventNotifierProvider);
    }

    if (!context.mounted) return;

    if (eventState.events.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Tidak ada event aktif. Hubungi Owner/Admin.'),
          backgroundColor: AppColors.warning,
        ),
      );
      return;
    }

    final selectedId = ref.read(returnStockProvider).eventId;

    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: AppColors.card,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: Text('Pilih Event', style: AppTextStyles.cardTitle),
            ),
            ...eventState.events.map((event) {
              final subtitle = [
                if (event.locationName.isNotEmpty) event.locationName,
                if (event.roleInEvent.isNotEmpty) event.roleInEvent,
              ].join(' · ');
              final isSelected = selectedId == event.eventId;
              return ListTile(
                title: Text(event.eventName),
                subtitle: subtitle.isNotEmpty
                    ? Text(subtitle, style: AppTextStyles.monoMuted)
                    : null,
                trailing: isSelected
                    ? const Icon(Icons.check, color: AppColors.success)
                    : null,
                onTap: () {
                  ref.read(returnStockProvider.notifier).setEvent(event);
                  Navigator.pop(ctx);
                },
              );
            }),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(returnStockProvider);
    final notifier = ref.read(returnStockProvider.notifier);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: AppColors.background,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.voidBlack),
          onPressed: () => context.pop(),
        ),
      ),
      body: SafeArea(
        top: false,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const ScreenHeader(
                      backLabel: 'STOK',
                      title: 'Return Sisa',
                    ),
                    const SizedBox(height: 16),
                    _EventCard(
                      hasEvent: state.hasEvent,
                      eventName: state.eventName,
                      locationName: state.locationName,
                      onPick: () => _showEventPicker(context, ref),
                      onChange: () => _showEventPicker(context, ref),
                    ),
                    if (state.errorMessage != null) ...[
                      const SizedBox(height: 8),
                      Text(
                        state.errorMessage!,
                        style: GoogleFonts.inter(
                          fontSize: 12,
                          color: AppColors.danger,
                        ),
                      ),
                    ],
                    const SizedBox(height: 16),
                    if (!state.hasEvent)
                      Text(
                        'Pilih event terlebih dahulu, lalu scan item return.',
                        style: AppTextStyles.cardSubtitle,
                      )
                    else if (state.items.isEmpty)
                      Text(
                        'Belum ada item. Tap + SCAN ITEM untuk menambah.',
                        style: AppTextStyles.cardSubtitle,
                      )
                    else
                      ...state.items.map(
                        (item) => _ReturnItemCard(
                          item: item,
                          onGoodChanged: (q) =>
                              notifier.updateQtyGood(item.itemId, q),
                          onDamagedChanged: (q) =>
                              notifier.updateQtyDamaged(item.itemId, q),
                          onDelete: () => notifier.removeItem(item.itemId),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            Container(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
              decoration: const BoxDecoration(
                color: AppColors.card,
                border: Border(top: BorderSide(color: AppColors.border)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (state.hasEvent && state.items.isNotEmpty)
                    Text(
                      '${state.totalItemCount} item · ${state.totalQty} qty total',
                      style: AppTextStyles.monoMuted,
                      textAlign: TextAlign.center,
                    ),
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    onPressed: !state.hasEvent
                        ? null
                        : () async {
                            final catalog =
                                await context.push<Map<String, dynamic>>(
                              '/scan?mode=select',
                            );
                            if (catalog != null) {
                              notifier.addScannedItem(catalog);
                            }
                          },
                    icon: const Icon(Icons.qr_code_scanner, size: 18),
                    label: const Text('+ SCAN ITEM'),
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size(double.infinity, 48),
                    ),
                  ),
                  const SizedBox(height: 8),
                  SizedBox(
                    height: 52,
                    child: ElevatedButton(
                      onPressed: !state.hasEvent ||
                              state.items.isEmpty ||
                              state.isLoading
                          ? null
                          : () async {
                              final ok = await notifier.submit(
                                ref.read(apiClientProvider).dio,
                              );
                              if (!context.mounted) return;
                              if (ok) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(
                                    content: Text('Return berhasil'),
                                    backgroundColor: AppColors.success,
                                  ),
                                );
                                context.pop();
                              }
                            },
                      child: state.isLoading
                          ? const SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : Text(
                              'KIRIM RETURN',
                              style: AppTextStyles.buttonPrimary,
                            ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EventCard extends StatelessWidget {
  final bool hasEvent;
  final String? eventName;
  final String? locationName;
  final VoidCallback onPick;
  final VoidCallback onChange;

  const _EventCard({
    required this.hasEvent,
    this.eventName,
    this.locationName,
    required this.onPick,
    required this.onChange,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: hasEvent
          ? Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('EVENT', style: AppTextStyles.sectionLabel),
                      const SizedBox(height: 6),
                      Text(
                        eventName ?? '—',
                        style: GoogleFonts.inter(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: AppColors.voidBlack,
                        ),
                      ),
                      if (locationName != null &&
                          locationName!.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          locationName!,
                          style: AppTextStyles.monoMuted,
                        ),
                      ],
                    ],
                  ),
                ),
                TextButton(
                  onPressed: onChange,
                  child: const Text('GANTI'),
                ),
              ],
            )
          : SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: onPick,
                child: const Text('PILIH EVENT'),
              ),
            ),
    );
  }
}

class _ReturnItemCard extends StatelessWidget {
  final ReturnItem item;
  final ValueChanged<int> onGoodChanged;
  final ValueChanged<int> onDamagedChanged;
  final VoidCallback onDelete;

  const _ReturnItemCard({
    required this.item,
    required this.onGoodChanged,
    required this.onDamagedChanged,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    return Dismissible(
      key: ValueKey(item.itemId),
      direction: DismissDirection.endToStart,
      onDismissed: (_) => onDelete(),
      background: Container(
        margin: const EdgeInsets.only(bottom: 10),
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 20),
        decoration: BoxDecoration(
          color: AppColors.danger,
          borderRadius: BorderRadius.circular(12),
        ),
        child: const Icon(Icons.delete_outline, color: Colors.white),
      ),
      child: GestureDetector(
        onLongPress: onDelete,
        child: Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.card,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                item.itemName,
                style: GoogleFonts.inter(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: AppColors.voidBlack,
                ),
              ),
              const SizedBox(height: 4),
              Text(item.barcode, style: AppTextStyles.monoMuted),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _QtyField(
                      label: 'GOOD',
                      labelColor: AppColors.success,
                      value: item.qtyGood,
                      onChanged: onGoodChanged,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _QtyField(
                      label: 'RUSAK',
                      labelColor: AppColors.warning,
                      value: item.qtyDamaged,
                      onChanged: onDamagedChanged,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                'Maks: ${AppColors.formatQty(item.maxAvailable)}',
                style: AppTextStyles.monoMuted.copyWith(fontSize: 11),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _QtyField extends StatefulWidget {
  final String label;
  final Color labelColor;
  final int value;
  final ValueChanged<int> onChanged;

  const _QtyField({
    required this.label,
    required this.labelColor,
    required this.value,
    required this.onChanged,
  });

  @override
  State<_QtyField> createState() => _QtyFieldState();
}

class _QtyFieldState extends State<_QtyField> {
  late final TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: '${widget.value}');
  }

  @override
  void didUpdateWidget(_QtyField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.value != widget.value &&
        _controller.text != '${widget.value}') {
      _controller.text = '${widget.value}';
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          widget.label,
          style: GoogleFonts.inter(
            fontSize: 10,
            fontWeight: FontWeight.w700,
            letterSpacing: 1,
            color: widget.labelColor,
          ),
        ),
        const SizedBox(height: 4),
        TextField(
          controller: _controller,
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          style: AppTextStyles.monoBold.copyWith(fontSize: 16),
          decoration: InputDecoration(
            filled: true,
            fillColor: AppColors.background,
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide: const BorderSide(color: AppColors.border),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide: const BorderSide(color: AppColors.border),
            ),
          ),
          onChanged: (v) {
            final parsed = int.tryParse(v) ?? 0;
            widget.onChanged(parsed);
          },
        ),
      ],
    );
  }
}

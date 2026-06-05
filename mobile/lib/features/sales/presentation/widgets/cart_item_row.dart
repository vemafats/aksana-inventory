import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';

import '../../../../core/auth/auth_provider.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_text_styles.dart';
import '../../../../core/utils/format_utils.dart';
import '../../data/photo_service.dart';
import '../sales_provider.dart';

class CartItemRow extends ConsumerStatefulWidget {
  final CartItem item;

  const CartItemRow({super.key, required this.item});

  @override
  ConsumerState<CartItemRow> createState() => _CartItemRowState();
}

class _CartItemRowState extends ConsumerState<CartItemRow> {
  final _imagePicker = ImagePicker();
  bool _isUploadingPhoto = false;

  bool get _hasPhoto =>
      (widget.item.localPhotoPath != null &&
          widget.item.localPhotoPath!.isNotEmpty) ||
      (widget.item.photoUrl != null && widget.item.photoUrl!.isNotEmpty);

  Future<void> _capturePhoto({bool replace = false}) async {
    if (_isUploadingPhoto) return;

    final image = await _imagePicker.pickImage(
      source: ImageSource.camera,
      imageQuality: 80,
      maxWidth: 1200,
    );
    if (image == null || !mounted) return;

    setState(() => _isUploadingPhoto = true);

    ref.read(salesCartProvider.notifier).setItemPhoto(
          widget.item.itemId,
          localPath: image.path,
          photoId: replace ? null : widget.item.photoId,
          photoUrl: replace ? null : widget.item.photoUrl,
        );

    try {
      final dio = ref.read(apiClientProvider).dio;
      final result = await ref.read(salesPhotoServiceProvider).uploadSalesPhoto(
            dio,
            filePath: image.path,
            relatedId: widget.item.itemId,
          );

      if (!mounted) return;

      if (result == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Upload foto gagal. Coba lagi.'),
            backgroundColor: AppColors.danger,
          ),
        );
        return;
      }

      ref.read(salesCartProvider.notifier).setItemPhoto(
            widget.item.itemId,
            photoId: result['id']?.toString(),
            photoUrl: result['photo_url']?.toString(),
            localPath: image.path,
          );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Upload foto gagal. Coba lagi.'),
          backgroundColor: AppColors.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _isUploadingPhoto = false);
    }
  }

  Future<void> _onPhotoTap() async {
    if (!_hasPhoto) {
      await _capturePhoto();
      return;
    }

    final action = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: AppColors.card,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt_outlined),
              title: const Text('Ambil ulang foto'),
              onTap: () => Navigator.pop(ctx, 'retake'),
            ),
          ],
        ),
      ),
    );

    if (action == 'retake' && mounted) {
      await _capturePhoto(replace: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final item = widget.item;

    return GestureDetector(
      onLongPress: () async {
        final confirmed = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: Text(
              'Hapus item?',
              style: AppTextStyles.cardTitle.copyWith(fontSize: 16),
            ),
            content: Text(
              item.itemName,
              style: AppTextStyles.cardSubtitle.copyWith(fontSize: 13),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(ctx).pop(false),
                child: const Text('BATAL'),
              ),
              ElevatedButton(
                onPressed: () => Navigator.of(ctx).pop(true),
                style: ElevatedButton.styleFrom(
                  minimumSize: const Size(72, 40),
                ),
                child: const Text('HAPUS'),
              ),
            ],
          ),
        );
        if (confirmed == true) {
          ref.read(salesCartProvider.notifier).removeItem(item.itemId);
        }
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(item.itemName, style: AppTextStyles.cardTitle),
                      Text(
                        'qty ${FormatUtils.formatQty(item.qty)}',
                        style: AppTextStyles.monoMuted,
                      ),
                    ],
                  ),
                ),
                Row(
                  children: [
                    GestureDetector(
                      onTap: () => ref
                          .read(salesCartProvider.notifier)
                          .updateQty(item.itemId, -1),
                      child: Container(
                        width: 28,
                        height: 28,
                        decoration: BoxDecoration(
                          color: AppColors.border,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: const Icon(Icons.remove, size: 14),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      item.qty.toString(),
                      style: AppTextStyles.monoBold,
                    ),
                    const SizedBox(width: 8),
                    GestureDetector(
                      onTap: () => ref
                          .read(salesCartProvider.notifier)
                          .updateQty(item.itemId, 1),
                      child: Container(
                        width: 28,
                        height: 28,
                        decoration: BoxDecoration(
                          color: AppColors.voidBlack,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: const Icon(
                          Icons.add,
                          size: 14,
                          color: Colors.white,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Text(
                      FormatUtils.formatPrice(
                        item.bazarSellingPrice * item.qty,
                      ),
                      style: AppTextStyles.monoBold,
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 8),
            if (_isUploadingPhoto)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 12),
                child: Center(
                  child: SizedBox(
                    width: 24,
                    height: 24,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                ),
              )
            else if (_hasPhoto)
              GestureDetector(
                onTap: _onPhotoTap,
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: AspectRatio(
                    aspectRatio: 16 / 9,
                    child: _buildThumbnail(item),
                  ),
                ),
              )
            else
              SizedBox(
                height: 36,
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: TextButton.icon(
                    onPressed: _capturePhoto,
                    icon: const Icon(
                      Icons.camera_alt_outlined,
                      size: 18,
                      color: AppColors.muted,
                    ),
                    label: Text(
                      'Tambah Foto',
                      style: GoogleFonts.inter(
                        fontSize: 12,
                        fontWeight: FontWeight.w500,
                        color: AppColors.muted,
                      ),
                    ),
                    style: TextButton.styleFrom(
                      alignment: Alignment.centerLeft,
                      padding: EdgeInsets.zero,
                      minimumSize: const Size(0, 36),
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildThumbnail(CartItem item) {
    final localPath = item.localPhotoPath;
    if (localPath != null && localPath.isNotEmpty) {
      final file = File(localPath);
      if (file.existsSync()) {
        return Image.file(
          file,
          fit: BoxFit.cover,
          width: double.infinity,
          height: double.infinity,
        );
      }
    }

    final url = item.photoUrl;
    if (url != null && url.isNotEmpty) {
      return Image.network(
        url,
        fit: BoxFit.cover,
        width: double.infinity,
        height: double.infinity,
        errorBuilder: (_, __, ___) => _photoPlaceholder(),
      );
    }

    return _photoPlaceholder();
  }

  Widget _photoPlaceholder() {
    return Container(
      width: double.infinity,
      height: double.infinity,
      color: AppColors.background,
      alignment: Alignment.center,
      child: const Icon(Icons.image_not_supported_outlined,
          color: AppColors.muted),
    );
  }
}

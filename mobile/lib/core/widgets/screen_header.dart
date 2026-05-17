import 'package:flutter/material.dart';
import '../theme/app_text_styles.dart';

class ScreenHeader extends StatelessWidget {
  final String backLabel;
  final String title;

  const ScreenHeader({
    super.key,
    required this.backLabel,
    required this.title,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          '← ${backLabel.toUpperCase()}',
          style: AppTextStyles.backLabel,
        ),
        const SizedBox(height: 4),
        Text(title, style: AppTextStyles.screenTitle),
      ],
    );
  }
}

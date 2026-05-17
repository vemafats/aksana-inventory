import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:aksana_mobile/main.dart';

void main() {
  testWidgets('shows login screen when unauthenticated', (WidgetTester tester) async {
    await tester.pumpWidget(
      const ProviderScope(child: AksanaApp()),
    );
    await tester.pumpAndSettle();

    expect(find.text('Masuk'), findsOneWidget);
    expect(find.text('MASUK'), findsOneWidget);
  });
}

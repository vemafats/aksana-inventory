import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:aksana_mobile/main.dart';

void main() {
  testWidgets('shows login when unauthenticated', (WidgetTester tester) async {
    await tester.pumpWidget(
      const ProviderScope(child: AksanaApp()),
    );
    await tester.pumpAndSettle();

    expect(
      find.text('Login — akan diimplementasi di M5-T2'),
      findsOneWidget,
    );
  });
}

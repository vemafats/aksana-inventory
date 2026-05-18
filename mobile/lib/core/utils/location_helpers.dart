String? assignedLocationId(Map<String, dynamic>? user) {
  final locations = user?['assigned_locations'];
  if (locations is List && locations.isNotEmpty) {
    final first = locations.first;
    if (first is Map) return first['id']?.toString();
  }
  return null;
}

String assignedLocationName(Map<String, dynamic>? user, {String fallback = ''}) {
  final locations = user?['assigned_locations'];
  if (locations is List && locations.isNotEmpty) {
    final first = locations.first;
    if (first is Map) {
      final name = first['name'] ?? first['location_name'];
      if (name != null) return name.toString();
    }
  }
  return fallback;
}

bool canCloseBazar(String? role) {
  if (role == null) return true;
  if (role == 'sales') return false;
  return const {'owner', 'admin', 'admin_gudang', 'pic_bazar'}.contains(role);
}

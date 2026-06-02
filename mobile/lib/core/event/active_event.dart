class ActiveEvent {
  final String eventId;
  final String eventName;
  final String locationId;
  final String locationName;
  final String locationType;
  final String roleInEvent;
  final String startDate;
  final String endDate;

  const ActiveEvent({
    required this.eventId,
    required this.eventName,
    required this.locationId,
    required this.locationName,
    required this.locationType,
    required this.roleInEvent,
    required this.startDate,
    required this.endDate,
  });

  factory ActiveEvent.fromJson(Map<String, dynamic> json) {
    final loc = json['location'];
    Map<String, dynamic>? locationMap;
    if (loc is Map) {
      locationMap = Map<String, dynamic>.from(loc);
    }

    return ActiveEvent(
      eventId: json['id']?.toString() ?? '',
      eventName: json['name']?.toString() ?? '',
      locationId: json['location_id']?.toString() ??
          locationMap?['id']?.toString() ??
          '',
      locationName: locationMap?['location_name']?.toString() ?? '',
      locationType: locationMap?['location_type']?.toString() ?? '',
      roleInEvent: json['role_in_event']?.toString() ?? '',
      startDate: json['start_date']?.toString() ?? '',
      endDate: json['end_date']?.toString() ?? '',
    );
  }
}

class ActiveEventState {
  final List<ActiveEvent> events;
  final ActiveEvent? selectedEvent;
  final bool isLoading;

  const ActiveEventState({
    this.events = const [],
    this.selectedEvent,
    this.isLoading = false,
  });

  bool get hasEvents => events.isNotEmpty;
  bool get hasMultipleEvents => events.length > 1;
  String? get currentLocationId => selectedEvent?.locationId;
  String? get currentLocationName => selectedEvent?.locationName;

  ActiveEventState copyWith({
    List<ActiveEvent>? events,
    ActiveEvent? selectedEvent,
    bool? isLoading,
    bool clearSelectedEvent = false,
  }) =>
      ActiveEventState(
        events: events ?? this.events,
        selectedEvent:
            clearSelectedEvent ? null : (selectedEvent ?? this.selectedEvent),
        isLoading: isLoading ?? this.isLoading,
      );
}

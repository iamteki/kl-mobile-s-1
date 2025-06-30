{{-- resources/views/livewire/booking-calendar.blade.php --}}
<div class="booking-calendar-component">
    <div class="calendar-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <button wire:click="previousMonth" class="btn btn-outline-secondary">
                <i class="fas fa-chevron-left"></i>
            </button>
            
            <h3 class="mb-0">{{ $monthName }}</h3>
            
            <div class="d-flex gap-2">
                <button wire:click="goToToday" class="btn btn-outline-primary">Today</button>
                <button wire:click="nextMonth" class="btn btn-outline-secondary">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
    
    <div class="calendar-legend mb-3">
        <div class="d-flex justify-content-center gap-4 flex-wrap">
            <span class="legend-item">
                <span class="legend-dot booking"></span> Event Day
            </span>
            <span class="legend-item">
                <span class="legend-dot delivery"></span> Delivery
            </span>
            <span class="legend-item">
                <span class="legend-dot pickup"></span> Pickup
            </span>
        </div>
    </div>
    
    <div class="calendar-grid">
        <div class="calendar-weekdays">
            @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                <div class="weekday">{{ $day }}</div>
            @endforeach
        </div>
        
        <div class="calendar-days">
            @foreach($calendarDays as $day)
                <div 
                    class="calendar-day {{ !$day['isCurrentMonth'] ? 'other-month' : '' }} {{ $day['isToday'] ? 'today' : '' }} {{ $day['bookingCount'] > 0 ? 'has-bookings' : '' }}"
                    wire:click="selectDate('{{ $day['dateStr'] }}')"
                >
                    <div class="day-number">{{ $day['day'] }}</div>
                    
                    @if($day['bookingCount'] > 0)
                        <div class="day-events">
                            <span class="event-count">{{ $day['bookingCount'] }}</span>
                        </div>
                    @endif
                    
                    <div class="day-indicators">
                        @if($day['hasDelivery'])
                            <span class="indicator delivery" title="Delivery scheduled"></span>
                        @endif
                        @if($day['hasPickup'])
                            <span class="indicator pickup" title="Pickup scheduled"></span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    
    <!-- Day Details Modal -->
    @if($showDayDetails && $selectedDate)
        <div class="day-details-overlay" wire:click.self="closeDayDetails">
            <div class="day-details-modal">
                <div class="modal-header">
                    <h5>Bookings for {{ Carbon\Carbon::parse($selectedDate)->format('l, F j, Y') }}</h5>
                    <button wire:click="closeDayDetails" class="btn-close"></button>
                </div>
                
                <div class="modal-body">
                    @if($dayBookings->count() > 0)
                        <div class="bookings-list">
                            @foreach($dayBookings as $booking)
                                <div class="booking-item">
                                    <div class="booking-time">
                                        <i class="fas fa-clock"></i>
                                        {{ $booking->installation_time }}
                                    </div>
                                    
                                    <div class="booking-details">
                                        <h6 class="mb-1">
                                            <a href="{{ route('admin.bookings.show', $booking) }}" target="_blank">
                                                #{{ $booking->booking_number }}
                                            </a>
                                            <span class="badge bg-{{ $booking->booking_status === 'confirmed' ? 'success' : 'warning' }} ms-2">
                                                {{ ucfirst($booking->booking_status) }}
                                            </span>
                                        </h6>
                                        
                                        <p class="mb-1">
                                            <strong>Customer:</strong> {{ $booking->customer->name }}
                                            <br>
                                            <strong>Venue:</strong> {{ $booking->venue }}
                                            <br>
                                            <strong>Event Type:</strong> {{ ucfirst($booking->event_type) }}
                                        </p>
                                        
                                        <div class="booking-items">
                                            <small class="text-muted">
                                                Items: {{ $booking->items->count() }} | 
                                                Total: LKR {{ number_format($booking->total_amount, 2) }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-center text-muted py-4">No bookings scheduled for this date.</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

<style>
.booking-calendar-component {
    background-color: var(--bg-card);
    border-radius: 12px;
    padding: 25px;
}

.calendar-legend {
    font-size: 0.875rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--text-gray);
}

.legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.legend-dot.booking {
    background-color: var(--primary-purple);
}

.legend-dot.delivery {
    background-color: var(--success);
}

.legend-dot.pickup {
    background-color: var(--warning);
}

.calendar-grid {
    background-color: var(--bg-darker);
    border-radius: 8px;
    overflow: hidden;
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background-color: var(--bg-dark);
    border-bottom: 1px solid var(--border-dark);
}

.weekday {
    padding: 15px;
    text-align: center;
    font-weight: 600;
    color: var(--text-gray);
    font-size: 0.875rem;
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}

.calendar-day {
    min-height: 100px;
    padding: 10px;
    border: 1px solid var(--border-dark);
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
}

.calendar-day:hover {
    background-color: var(--bg-card);
}

.calendar-day.other-month {
    opacity: 0.4;
}

.calendar-day.today {
    background-color: rgba(147, 51, 234, 0.1);
}

.calendar-day.today .day-number {
    color: var(--primary-purple);
    font-weight: bold;
}

.calendar-day.has-bookings {
    background-color: rgba(147, 51, 234, 0.05);
}

.day-number {
    font-size: 1rem;
    font-weight: 500;
    margin-bottom: 5px;
}

.day-events {
    position: absolute;
    top: 10px;
    right: 10px;
}

.event-count {
    background-color: var(--primary-purple);
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: bold;
}

.day-indicators {
    position: absolute;
    bottom: 10px;
    left: 10px;
    display: flex;
    gap: 5px;
}

.indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.indicator.delivery {
    background-color: var(--success);
}

.indicator.pickup {
    background-color: var(--warning);
}

/* Day Details Modal */
.day-details-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
}

.day-details-modal {
    background-color: var(--bg-card);
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.day-details-modal .modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--border-dark);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.day-details-modal .modal-body {
    padding: 20px;
    overflow-y: auto;
}

.bookings-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.booking-item {
    background-color: var(--bg-darker);
    border-radius: 8px;
    padding: 15px;
    display: flex;
    gap: 15px;
}

.booking-time {
    color: var(--primary-purple);
    font-weight: 600;
    white-space: nowrap;
}

.booking-details {
    flex-grow: 1;
}

.booking-details h6 {
    margin-bottom: 0.5rem;
}

.booking-details a {
    color: var(--primary-purple);
    text-decoration: none;
}

.booking-details a:hover {
    color: var(--secondary-purple);
}
</style>
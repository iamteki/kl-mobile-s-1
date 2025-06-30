<?php
// app/Livewire/BookingCalendar.php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Booking;
use Carbon\Carbon;

class BookingCalendar extends Component
{
    public $currentMonth;
    public $currentYear;
    public $selectedDate = null;
    public $bookings = [];
    public $dayBookings = [];
    public $showDayDetails = false;
    
    public function mount()
    {
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
        $this->loadBookings();
    }
    
    public function previousMonth()
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->subMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
        $this->loadBookings();
    }
    
    public function nextMonth()
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->addMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
        $this->loadBookings();
    }
    
    public function goToToday()
    {
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
        $this->loadBookings();
    }
    
    public function selectDate($date)
    {
        $this->selectedDate = $date;
        $this->showDayDetails = true;
        $this->loadDayBookings($date);
    }
    
    public function closeDayDetails()
    {
        $this->showDayDetails = false;
        $this->selectedDate = null;
        $this->dayBookings = [];
    }
    
    protected function loadBookings()
    {
        $startOfMonth = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->startOfMonth();
        $endOfMonth = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->endOfMonth();
        
        $bookings = Booking::with(['customer', 'items'])
            ->whereBetween('event_date', [$startOfMonth, $endOfMonth])
            ->whereNotIn('booking_status', ['cancelled'])
            ->get();
        
        $this->bookings = $bookings->groupBy(function ($booking) {
            return $booking->event_date->format('Y-m-d');
        })->toArray();
    }
    
    protected function loadDayBookings($date)
    {
        $this->dayBookings = Booking::with(['customer', 'items.product'])
            ->whereDate('event_date', $date)
            ->whereNotIn('booking_status', ['cancelled'])
            ->orderBy('installation_time')
            ->get();
    }
    
    public function getCalendarDays()
    {
        $firstDay = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1);
        $lastDay = $firstDay->copy()->endOfMonth();
        
        $startDate = $firstDay->copy()->startOfWeek(Carbon::SUNDAY);
        $endDate = $lastDay->copy()->endOfWeek(Carbon::SATURDAY);
        
        $days = [];
        $date = $startDate->copy();
        
        while ($date <= $endDate) {
            $dateStr = $date->format('Y-m-d');
            $bookingCount = isset($this->bookings[$dateStr]) ? count($this->bookings[$dateStr]) : 0;
            
            $days[] = [
                'date' => $date->copy(),
                'dateStr' => $dateStr,
                'day' => $date->day,
                'isCurrentMonth' => $date->month == $this->currentMonth,
                'isToday' => $date->isToday(),
                'isPast' => $date->isPast(),
                'bookingCount' => $bookingCount,
                'hasDelivery' => $this->hasDelivery($dateStr),
                'hasPickup' => $this->hasPickup($dateStr),
            ];
            
            $date->addDay();
        }
        
        return $days;
    }
    
    protected function hasDelivery($date)
    {
        return Booking::whereDate('installation_date', $date)
            ->whereNotIn('booking_status', ['cancelled'])
            ->exists();
    }
    
    protected function hasPickup($date)
    {
        return Booking::whereDate('dismantle_date', $date)
            ->whereNotIn('booking_status', ['cancelled'])
            ->exists();
    }
    
    public function render()
    {
        return view('livewire.booking-calendar', [
            'calendarDays' => $this->getCalendarDays(),
            'monthName' => Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->format('F Y')
        ]);
    }
}
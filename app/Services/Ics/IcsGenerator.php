<?php

namespace App\Services\Ics;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class IcsGenerator
{
    /**
     * Build an iCalendar document for a booking.
     */
    public function forBooking(Booking $booking): string
    {
        $method = $booking->status === BookingStatus::Confirmed ? 'REQUEST' : 'CANCEL';
        $organizer = $booking->host;

        $lines = new Collection([
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.config('app.name').'//Scheduling//EN',
            'CALSCALE:GREGORIAN',
            "METHOD:{$method}",
            'BEGIN:VEVENT',
            'UID:'.$booking->uid,
            'SEQUENCE:'.($booking->status === BookingStatus::Confirmed ? 0 : 1),
            'DTSTAMP:'.$this->stamp($booking->updated_at ?? now()),
            'DTSTART:'.$this->stamp($booking->starts_at),
            'DTEND:'.$this->stamp($booking->ends_at),
            'SUMMARY:'.$this->escape($this->title($booking)),
            'DESCRIPTION:'.$this->escape($this->description($booking)),
            'STATUS:'.($booking->status === BookingStatus::Confirmed ? 'CONFIRMED' : 'CANCELLED'),
        ]);

        if (filled($location = $booking->meeting_url ?: $booking->location_detail)) {
            $lines->push('LOCATION:'.$this->escape($location));
        }

        $lines->push('ORGANIZER;CN='.$this->escape($organizer->name).':mailto:'.$organizer->email);

        $lines->push('ATTENDEE;CN='.$this->escape($booking->name).';RSVP=TRUE:mailto:'.$booking->email);

        foreach ($booking->guests as $guest) {
            $lines->push('ATTENDEE;RSVP=TRUE:mailto:'.$guest->email);
        }

        $lines->push('END:VEVENT', 'END:VCALENDAR');

        return $lines->map(fn (string $line) => $this->fold($line))->implode("\r\n")."\r\n";
    }

    /**
     * Get the calendar title for a booking.
     */
    public function title(Booking $booking): string
    {
        return $booking->eventType->name.' between '
            .$booking->host->name.' and '.$booking->name;
    }

    /**
     * Build the calendar body for a booking.
     */
    public function description(Booking $booking): string
    {
        $parts = new Collection([$booking->eventType->description]);

        if (filled($booking->notes)) {
            $parts->push('Notes: '.$booking->notes);
        }

        foreach ($booking->answers as $answer) {
            $parts->push($answer->label.': '.$answer->answer);
        }

        return $parts->filter()->implode("\n\n");
    }

    /**
     * Format a date as a UTC iCalendar stamp.
     */
    protected function stamp(mixed $date): string
    {
        return Carbon::parse($date)->utc()->format('Ymd\THis\Z');
    }

    /**
     * Escape the characters iCalendar treats as syntax.
     */
    protected function escape(string $value): string
    {
        return str_replace(
            ['\\', "\n", ',', ';'],
            ['\\\\', '\n', '\,', '\;'],
            trim($value),
        );
    }

    /**
     * Fold a line to the 75 octet limit the format requires.
     */
    protected function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = substr($line, 0, 75);
        $rest = substr($line, 75);

        foreach (str_split($rest, 74) as $chunk) {
            $folded .= "\r\n ".$chunk;
        }

        return $folded;
    }
}

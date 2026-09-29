<?php

use App\Support\BookingStateMachine;
use App\Support\InvalidBookingTransitionException;

it('allows every legal transition in the booking lifecycle', function () {
    expect(BookingStateMachine::canTransition('pending', 'confirmed'))->toBeTrue()
        ->and(BookingStateMachine::canTransition('pending', 'cancelled'))->toBeTrue()
        ->and(BookingStateMachine::canTransition('confirmed', 'checked_in'))->toBeTrue()
        ->and(BookingStateMachine::canTransition('confirmed', 'cancelled'))->toBeTrue()
        ->and(BookingStateMachine::canTransition('confirmed', 'no_show'))->toBeTrue()
        ->and(BookingStateMachine::canTransition('checked_in', 'completed'))->toBeTrue()
        ->and(BookingStateMachine::canTransition('checked_in', 'cancelled'))->toBeTrue();
});

it('rejects illegal transitions, including from every terminal status', function () {
    expect(BookingStateMachine::canTransition('pending', 'checked_in'))->toBeFalse()
        ->and(BookingStateMachine::canTransition('pending', 'completed'))->toBeFalse()
        ->and(BookingStateMachine::canTransition('completed', 'pending'))->toBeFalse()
        ->and(BookingStateMachine::canTransition('cancelled', 'confirmed'))->toBeFalse()
        ->and(BookingStateMachine::canTransition('no_show', 'completed'))->toBeFalse();
});

it('throws a descriptive exception for an illegal transition', function () {
    expect(fn () => BookingStateMachine::assertTransition('cancelled', 'confirmed'))
        ->toThrow(InvalidBookingTransitionException::class, 'A booking cannot move from "cancelled" to "confirmed".');
});

it('does not throw for a legal transition', function () {
    BookingStateMachine::assertTransition('pending', 'confirmed');
})->throwsNoExceptions();

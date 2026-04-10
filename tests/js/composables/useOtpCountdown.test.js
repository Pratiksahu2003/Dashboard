import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { useOtpCountdown } from '../../../resources/js/composables/useOtpCountdown';

// Mock vue to prevent onUnmounted warnings
vi.mock('vue', async () => {
    const actual = await vi.importActual('vue');
    return {
        ...actual,
        onUnmounted: vi.fn(),
    };
});

describe('useOtpCountdown', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('should parse the exact backend message and set the countdown', () => {
        const { countdownMessage, isCountingDown, parseAndStartCountdown } = useOtpCountdown();
        
        const backendMessage = 'Too many OTP requests. Please try again in 00:04:06.';
        const result = parseAndStartCountdown(backendMessage);

        expect(result).toBe(true);
        expect(isCountingDown.value).toBe(true);
        // The original message is parsed and initially rendered verbatim
        expect(countdownMessage.value).toBe('Too many OTP requests. Please try again in 00:04:06.');
    });

    it('should update the countdown dynamically every second', () => {
        const { countdownMessage, isCountingDown, parseAndStartCountdown } = useOtpCountdown();
        
        parseAndStartCountdown('Too many OTP requests. Please try again in 00:04:06.');
        
        // Fast forward 1 second
        vi.advanceTimersByTime(1000);
        expect(countdownMessage.value).toBe('Too many OTP requests. Please try again in 00:04:05.');
        
        // Fast forward another 5 seconds
        vi.advanceTimersByTime(5000);
        expect(countdownMessage.value).toBe('Too many OTP requests. Please try again in 00:04:00.');
        
        // Fast forward 4 minutes (240 seconds)
        vi.advanceTimersByTime(240000);
        expect(countdownMessage.value).toBe('Too many OTP requests. Please try again in 00:00:00.');
        expect(isCountingDown.value).toBe(true); // Still true until it hits 0 and stops
        
        // Fast forward 1 more second to hit < 0
        vi.advanceTimersByTime(1000);
        expect(isCountingDown.value).toBe(false);
    });

    it('should return false for messages without a time string and avoid client-side fallback', () => {
        const { isCountingDown, parseAndStartCountdown } = useOtpCountdown();
        
        const result = parseAndStartCountdown('Invalid credentials.');
        expect(result).toBe(false);
        expect(isCountingDown.value).toBe(false);
    });
});

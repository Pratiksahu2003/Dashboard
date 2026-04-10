import { ref, onUnmounted } from 'vue';

export function useOtpCountdown() {
    const countdownMessage = ref('');
    const isCountingDown = ref(false);
    let timer = null;

    const parseAndStartCountdown = (message) => {
        if (!message) return false;

        // Match HH:MM:SS pattern inside the message
        const match = message.match(/(.*?)(\d{2}:\d{2}:\d{2})(.*)/);
        if (match) {
            const prefix = match[1];
            const timeStr = match[2];
            const suffix = match[3];

            const parts = timeStr.split(':').map(Number);
            let totalSeconds = parts[0] * 3600 + parts[1] * 60 + parts[2];

            if (totalSeconds > 0) {
                isCountingDown.value = true;
                updateMessage(prefix, totalSeconds, suffix);
                
                timer = setInterval(() => {
                    totalSeconds--;
                    updateMessage(prefix, Math.max(0, totalSeconds), suffix);
                    if (totalSeconds <= 0) {
                        stopCountdown();
                    }
                }, 1000);
                return true;
            }
        }
        
        return false;
    };

    const updateMessage = (prefix, totalSeconds, suffix) => {
        const h = Math.floor(totalSeconds / 3600).toString().padStart(2, '0');
        const m = Math.floor((totalSeconds % 3600) / 60).toString().padStart(2, '0');
        const s = (totalSeconds % 60).toString().padStart(2, '0');
        countdownMessage.value = `${prefix}${h}:${m}:${s}${suffix}`;
    };

    const stopCountdown = () => {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
        isCountingDown.value = false;
    };

    onUnmounted(() => {
        stopCountdown();
    });

    return {
        countdownMessage,
        isCountingDown,
        parseAndStartCountdown,
        stopCountdown
    };
}

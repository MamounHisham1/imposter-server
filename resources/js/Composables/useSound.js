import { ref } from 'vue'

const audioCache = {}
const enabled = ref(true)

function getAudioContext() {
    if (!window._audioCtx) {
        window._audioCtx = new (window.AudioContext || window.webkitAudioContext)()
    }
    return window._audioCtx
}

function playTone(frequency, duration, type = 'sine', volume = 0.15) {
    if (!enabled.value) return
    try {
        const ctx = getAudioContext()
        const osc = ctx.createOscillator()
        const gain = ctx.createGain()
        osc.type = type
        osc.frequency.setValueAtTime(frequency, ctx.currentTime)
        gain.gain.setValueAtTime(volume, ctx.currentTime)
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration)
        osc.connect(gain)
        gain.connect(ctx.destination)
        osc.start(ctx.currentTime)
        osc.stop(ctx.currentTime + duration)
    } catch {}
}

function playSequence(notes) {
    if (!enabled.value) return
    try {
        const ctx = getAudioContext()
        let time = ctx.currentTime
        notes.forEach(({ freq, dur, type = 'sine', vol = 0.12 }) => {
            const osc = ctx.createOscillator()
            const gain = ctx.createGain()
            osc.type = type
            osc.frequency.setValueAtTime(freq, time)
            gain.gain.setValueAtTime(vol, time)
            gain.gain.exponentialRampToValueAtTime(0.001, time + dur)
            osc.connect(gain)
            gain.connect(ctx.destination)
            osc.start(time)
            osc.stop(time + dur)
            time += dur
        })
    } catch {}
}

export function useSound() {
    function playTurnNotification() {
        playSequence([
            { freq: 523, dur: 0.12, type: 'triangle' },
            { freq: 659, dur: 0.15, type: 'triangle' },
        ])
    }

    function playHintSubmitted() {
        playTone(880, 0.08, 'sine', 0.08)
    }

    function playTimerLow() {
        playTone(220, 0.15, 'square', 0.1)
    }

    function playTimerExpired() {
        playSequence([
            { freq: 440, dur: 0.1, type: 'square', vol: 0.1 },
            { freq: 330, dur: 0.15, type: 'square', vol: 0.1 },
            { freq: 220, dur: 0.2, type: 'square', vol: 0.1 },
        ])
    }

    function playVotingStarted() {
        playSequence([
            { freq: 523, dur: 0.1, type: 'triangle' },
            { freq: 659, dur: 0.1, type: 'triangle' },
            { freq: 784, dur: 0.15, type: 'triangle' },
        ])
    }

    function playVoteSubmitted() {
        playTone(660, 0.1, 'sine', 0.08)
    }

    function playImposterRevealed() {
        playSequence([
            { freq: 440, dur: 0.2, type: 'sawtooth', vol: 0.08 },
            { freq: 370, dur: 0.2, type: 'sawtooth', vol: 0.08 },
            { freq: 311, dur: 0.3, type: 'sawtooth', vol: 0.08 },
            { freq: 262, dur: 0.5, type: 'sawtooth', vol: 0.1 },
        ])
    }

    function playCrewWins() {
        playSequence([
            { freq: 523, dur: 0.12, type: 'triangle' },
            { freq: 659, dur: 0.12, type: 'triangle' },
            { freq: 784, dur: 0.12, type: 'triangle' },
            { freq: 1047, dur: 0.25, type: 'triangle', vol: 0.15 },
        ])
    }

    function playImposterWins() {
        playSequence([
            { freq: 392, dur: 0.2, type: 'sawtooth', vol: 0.08 },
            { freq: 349, dur: 0.2, type: 'sawtooth', vol: 0.08 },
            { freq: 330, dur: 0.3, type: 'sawtooth', vol: 0.1 },
        ])
    }

    function playChatMessage() {
        playTone(1200, 0.05, 'sine', 0.05)
    }

    function playNewRound() {
        playSequence([
            { freq: 659, dur: 0.1, type: 'triangle' },
            { freq: 784, dur: 0.1, type: 'triangle' },
            { freq: 659, dur: 0.1, type: 'triangle' },
            { freq: 784, dur: 0.15, type: 'triangle' },
        ])
    }

    function toggleSound() {
        enabled.value = !enabled.value
    }

    return {
        enabled,
        playTurnNotification,
        playHintSubmitted,
        playTimerLow,
        playTimerExpired,
        playVotingStarted,
        playVoteSubmitted,
        playImposterRevealed,
        playCrewWins,
        playImposterWins,
        playChatMessage,
        playNewRound,
        toggleSound,
    }
}

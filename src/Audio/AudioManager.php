<?php

namespace VISU\Audio;

use FFI;
use VISU\SDL3\Exception\SDLException;
use VISU\SDL3\SDL;

class AudioManager
{
    private AudioStream $stream;

    public function __construct(
        private SDL $sdl,
        int $sampleRate = 44100,
        int $channels = 2,
    ) {
        // SDL_AUDIO_S16 = 0x8010
        $spec = $sdl->ffi->new('SDL_AudioSpec');
        $spec->format   = 0x8010;
        $spec->channels = $channels;
        $spec->freq     = $sampleRate;

        $nativeStream = $sdl->ffi->SDL_OpenAudioDeviceStream(
            SDL::AUDIO_DEVICE_DEFAULT_PLAYBACK,
            FFI::addr($spec),
            null,
            null
        );

        if ($nativeStream === null) {
            throw new SDLException('SDL_OpenAudioDeviceStream failed: ' . $sdl->getError());
        }

        $this->stream = new AudioStream($sdl, $nativeStream);
        $this->stream->resume();
    }

    /**
     * Load a WAV file and return an AudioClip.
     */
    public function loadClip(string $path): AudioClip
    {
        $spec      = $this->sdl->ffi->new('SDL_AudioSpec');
        $audioBuf  = $this->sdl->ffi->new('uint8_t*');
        $audioLen  = $this->sdl->ffi->new('uint32_t');

        $ok = $this->sdl->ffi->SDL_LoadWAV(
            $path,
            FFI::addr($spec),
            FFI::addr($audioBuf),
            FFI::addr($audioLen)
        );

        if (!$ok) {
            throw new SDLException("SDL_LoadWAV failed for '{$path}': " . $this->sdl->getError());
        }

        return new AudioClip($spec, $audioBuf, (int) $audioLen->cdata, $path);
    }

    /**
     * Queue an AudioClip for playback.
     * volume is currently informational; SDL3 stream volume can be set via SDL_SetAudioStreamGain (not yet wired).
     */
    public function play(AudioClip $clip, float $volume = 1.0): void
    {
        $this->stream->putData($clip->buffer, $clip->length);
    }

    /**
     * Call once per game loop tick to keep the audio stream healthy.
     * Currently a no-op; reserved for future buffering / gain logic.
     */
    public function update(): void
    {
    }

    public function getStream(): AudioStream
    {
        return $this->stream;
    }

    public function __destruct()
    {
        $this->stream->destroy();
    }
}

<?php

namespace VISU\Audio;

use FFI;
use VISU\SDL3\Exception\SDLException;
use VISU\SDL3\SDL;

class AudioManager
{
    private AudioStream $stream;

    /**
     * Clip cache (path -> AudioClip).
     *
     * @var array<string, AudioClip>
     */
    private array $clipCache = [];

    /**
     * Per-channel volume (0.0 to 1.0).
     *
     * @var array<string, float>
     */
    private array $channelVolumes = [];

    /**
     * Currently playing music clip (for looping).
     */
    private ?AudioClip $currentMusic = null;

    /**
     * Whether music is currently playing.
     */
    private bool $musicPlaying = false;

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

        // Default volumes
        foreach (AudioChannel::cases() as $channel) {
            $this->channelVolumes[$channel->value] = 1.0;
        }
    }

    /**
     * Load a WAV file and return an AudioClip. Results are cached.
     */
    public function loadClip(string $path): AudioClip
    {
        if (isset($this->clipCache[$path])) {
            return $this->clipCache[$path];
        }

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

        $clip = new AudioClip($spec, $audioBuf, (int) $audioLen->cdata, $path);
        $this->clipCache[$path] = $clip;
        return $clip;
    }

    /**
     * Play a sound effect (one-shot).
     */
    public function playSound(string $path, AudioChannel $channel = AudioChannel::SFX): void
    {
        $clip = $this->loadClip($path);
        $this->play($clip);
    }

    /**
     * Start playing a music track. Stops any currently playing music.
     */
    public function playMusic(string $path): void
    {
        $this->stopMusic();
        $this->currentMusic = $this->loadClip($path);
        $this->musicPlaying = true;
        $this->stream->putData($this->currentMusic->buffer, $this->currentMusic->length);
    }

    /**
     * Stop the currently playing music.
     */
    public function stopMusic(): void
    {
        $this->musicPlaying = false;
        $this->currentMusic = null;
    }

    /**
     * Check if music is currently playing.
     */
    public function isMusicPlaying(): bool
    {
        return $this->musicPlaying;
    }

    /**
     * Set volume for a specific channel (0.0 to 1.0).
     */
    public function setChannelVolume(AudioChannel $channel, float $volume): void
    {
        $this->channelVolumes[$channel->value] = max(0.0, min(1.0, $volume));
    }

    /**
     * Get volume for a specific channel.
     */
    public function getChannelVolume(AudioChannel $channel): float
    {
        return $this->channelVolumes[$channel->value] ?? 1.0;
    }

    /**
     * Queue an AudioClip for playback.
     */
    public function play(AudioClip $clip, float $volume = 1.0): void
    {
        $this->stream->putData($clip->buffer, $clip->length);
    }

    /**
     * Call once per game loop tick.
     * Handles music looping when the stream buffer runs low.
     */
    public function update(): void
    {
        // Simple music looping: re-queue when buffer is nearly empty
        if ($this->musicPlaying && $this->currentMusic !== null) {
            $queued = $this->stream->getQueued();
            if ($queued < $this->currentMusic->length / 2) {
                $this->stream->putData($this->currentMusic->buffer, $this->currentMusic->length);
            }
        }
    }

    public function getStream(): AudioStream
    {
        return $this->stream;
    }

    /**
     * Unload a cached clip.
     */
    public function unloadClip(string $path): void
    {
        unset($this->clipCache[$path]);
    }

    /**
     * Clear all cached clips.
     */
    public function clearCache(): void
    {
        $this->clipCache = [];
    }

    public function __destruct()
    {
        $this->stream->destroy();
    }
}

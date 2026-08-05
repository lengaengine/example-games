<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Audio;

use Lenga\Engine\Core\MathUtil;
use Lenga\Engine\Core\Preferences;

final class AudioPreferences
{
    private const string BGM_VOLUME_KEY = 'audio.bgmVolume';
    private const string SFX_VOLUME_KEY = 'audio.sfxVolume';
    private const array LEGACY_BGM_VOLUME_KEYS = [
        'bgm.volume',
        'audio.musicVolume',
    ];

    private function __construct()
    {
    }

    public static function getBgmVolume(float $defaultValue = 1.0): float
    {
        if (!Preferences::hasKey(self::BGM_VOLUME_KEY)) {
            foreach (self::LEGACY_BGM_VOLUME_KEYS as $legacyKey) {
                if (Preferences::hasKey($legacyKey)) {
                    $volume = self::normalizeVolume(Preferences::getFloat($legacyKey, $defaultValue));
                    self::setBgmVolume($volume);
                    return $volume;
                }
            }
        }

        return self::normalizeVolume(Preferences::getFloat(self::BGM_VOLUME_KEY, $defaultValue));
    }

    public static function setBgmVolume(float $volume): void
    {
        Preferences::setFloat(self::BGM_VOLUME_KEY, self::normalizeVolume($volume));
    }

    public static function getSfxVolume(float $defaultValue = 1.0): float
    {
        return self::normalizeVolume(Preferences::getFloat(self::SFX_VOLUME_KEY, $defaultValue));
    }

    public static function setSfxVolume(float $volume): void
    {
        Preferences::setFloat(self::SFX_VOLUME_KEY, self::normalizeVolume($volume));
    }

    public static function save(): bool
    {
        return Preferences::save();
    }

    public static function normalizeVolume(float $volume): float
    {
        return MathUtil::clamp($volume, 0.0, 1.0);
    }
}

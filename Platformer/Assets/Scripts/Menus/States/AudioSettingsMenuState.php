<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Menus\States;

use Lenga\Engine\Core\Input;
use Lenga\Engine\UI\Canvas;
use Lenga\Engine\UI\Slider;
use Lenga\Platformer\Scripts\Audio\AudioPreferences;
use Lenga\Platformer\Scripts\Input\Enumerations\ButtonType;
use Lenga\Platformer\Scripts\Menus\Enumerations\MenuButtonType;
use Lenga\Platformer\Scripts\Menus\MainMenuContext;
use Override;

class AudioSettingsMenuState extends AbstractMainMenuState
{
    private float $sfxVolume = 1.0;
    private float $bgmVolume = 1.0;
    private ?Slider $sfxVolumeSlider = null;
    private ?Slider $bgmVolumeSlider = null;
    private bool $hasUnsavedChanges = false;

    public function enter(MainMenuContext $context): void
    {
        if ($context->audioSettingsMenu) {
            $context->audioSettingsMenu->enabled = true;
            $this->menu->animateCanvasIn($context->audioSettingsMenu);
        }

        $this->resolveControls($context);
        $this->loadPreferences();
        $this->syncControlsFromPreferences();
        parent::enter($context);

        if ($context->audioSettingsMenu?->getSelectedElement() === null && $this->sfxVolumeSlider !== null) {
            $context->audioSettingsMenu->selectElement($this->sfxVolumeSlider);
        }
    }

    public function exit(MainMenuContext $context): void
    {
        parent::exit($context);

        if ($this->hasUnsavedChanges) {
            AudioPreferences::save();
            $this->hasUnsavedChanges = false;
        }

        if ($context->audioSettingsMenu) {
            $this->menu->restoreCanvasRestState($context->audioSettingsMenu);
            $context->audioSettingsMenu->enabled = false;
        }
    }

    #[Override]
    public function update(MainMenuContext $context): void
    {
        parent::update($context);

        if (Input::getButtonDown(ButtonType::CANCEL->value)) {
            $this->setState(new SettingsMenuState($this->menu));
            return;
        }

        if (Input::getButtonDown(ButtonType::CONFIRM->value)) {
            $selectedElement = $context->audioSettingsMenu?->getSelectedElement();
            if ($selectedElement?->name === MenuButtonType::AUDIO_SETTINGS_BACK->value) {
                $this->menu->onCloseAudioSettingsButtonClicked();
                return;
            }
        }

        $this->syncPreferencesFromControls();
    }

    public function setBgmVolume(float $volume): void
    {
        $normalizedVolume = AudioPreferences::normalizeVolume($volume);
        if (\abs($this->bgmVolume - $normalizedVolume) < 0.0001) {
            return;
        }

        $this->bgmVolume = $normalizedVolume;
        AudioPreferences::setBgmVolume($this->bgmVolume);
        $this->menu->applyBgmVolume($this->bgmVolume);
        $this->hasUnsavedChanges = true;
    }

    public function setSfxVolume(float $volume): void
    {
        $normalizedVolume = AudioPreferences::normalizeVolume($volume);
        if (\abs($this->sfxVolume - $normalizedVolume) < 0.0001) {
            return;
        }

        $this->sfxVolume = $normalizedVolume;
        AudioPreferences::setSfxVolume($this->sfxVolume);
        $this->hasUnsavedChanges = true;
    }

    private function resolveControls(MainMenuContext $context): void
    {
        $this->sfxVolumeSlider = $context->audioSettingsMenu?->findSliderByName('SFX Slider');
        $this->bgmVolumeSlider = $context->audioSettingsMenu?->findSliderByName('BGM Slider');
    }

    private function loadPreferences(): void
    {
        $this->bgmVolume = AudioPreferences::getBgmVolume($this->menu->getDefaultBgmVolume());
        $this->sfxVolume = AudioPreferences::getSfxVolume($this->menu->getDefaultSfxVolume());
        $this->menu->applyBgmVolume($this->bgmVolume);
    }

    private function syncControlsFromPreferences(): void
    {
        if ($this->bgmVolumeSlider !== null) {
            $this->bgmVolumeSlider->minValue = 0.0;
            $this->bgmVolumeSlider->maxValue = 1.0;
            $this->bgmVolumeSlider->value = $this->bgmVolume;
        }

        if ($this->sfxVolumeSlider !== null) {
            $this->sfxVolumeSlider->minValue = 0.0;
            $this->sfxVolumeSlider->maxValue = 1.0;
            $this->sfxVolumeSlider->value = $this->sfxVolume;
        }
    }

    private function syncPreferencesFromControls(): void
    {
        if ($this->bgmVolumeSlider !== null) {
            $this->setBgmVolume($this->bgmVolumeSlider->value);
        }

        if ($this->sfxVolumeSlider !== null) {
            $this->setSfxVolume($this->sfxVolumeSlider->value);
        }
    }

    protected function getNavigationCanvas(MainMenuContext $context): ?Canvas
    {
        return $context->audioSettingsMenu;
    }
}

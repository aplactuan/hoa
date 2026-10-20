<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Module')] class extends Component
{
    public string $title = 'Module';

    public function mount(): void
    {
        $name = request()->route()?->getName();
        $navigation = config('access.navigation');

        if (! is_string($name) || ! is_array($navigation)) {
            return;
        }

        foreach ($navigation as $group) {
            if (! is_array($group) || ! is_array($group['items'] ?? null)) {
                continue;
            }

            foreach ($group['items'] as $item) {
                if (is_array($item) && ($item['route'] ?? null) === $name && is_string($item['label'] ?? null)) {
                    $this->title = $item['label'];

                    return;
                }
            }
        }
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header
        :title="$title"
        :subtitle="__('This module will be added in a later phase.')"
    />
</div>

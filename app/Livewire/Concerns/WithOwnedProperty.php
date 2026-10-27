<?php

namespace App\Livewire\Concerns;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

trait WithOwnedProperty
{
    public string $selectedPropertyId = '';

    public function initializeOwnedProperty(): void
    {
        $firstPropertyId = $this->ownerUser()->currentOwnedProperties()->orderBy('code')->value('id');
        $this->selectedPropertyId = $firstPropertyId === null ? '' : (string) $firstPropertyId;
    }

    public function selectProperty(string|int $propertyId): void
    {
        $propertyId = (int) $propertyId;

        if (! $this->ownerUser()->ownsCurrentProperty($propertyId)) {
            abort(404);
        }

        $this->selectedPropertyId = (string) $propertyId;
        unset($this->ownedProperties, $this->selectedProperty);

        if (method_exists($this, 'ownedPropertySelected')) {
            $this->ownedPropertySelected();
        }
    }

    /**
     * @return Collection<int, Property>
     */
    #[Computed]
    public function ownedProperties(): Collection
    {
        return $this->ownerUser()
            ->currentOwnedProperties()
            ->with(['street', 'section', 'currentOwner'])
            ->orderBy('code')
            ->get();
    }

    #[Computed]
    public function selectedProperty(): ?Property
    {
        if ($this->selectedPropertyId === '') {
            return null;
        }

        return $this->ownerUser()
            ->currentOwnedProperties()
            ->whereKey((int) $this->selectedPropertyId)
            ->firstOrFail();
    }

    protected function ownerUser(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

<?php

use App\Enums\DiscountType;
use App\Enums\PenaltyType;
use App\Models\AdvancePaymentPromo;
use App\Models\DuesStatement;
use App\Models\MonthlyFeeRate;
use App\Models\PenaltyAmnesty;
use App\Models\Setting;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Association settings')] class extends Component
{
    public string $membership_fee = '0.00';

    public string $penalty_type = 'percent';

    public string $penalty_percent = '';

    public string $penalty_fixed_amount = '0.00';

    public int $due_day = 1;

    public int $grace_days = 0;

    public int $expiry_warning_days = 45;

    public string $hoa_name = '';

    public string $hoa_address = '';

    public string $rateAmount = '';

    public string $rateMonth = '';

    public string $rateRemarks = '';

    public string $promoName = '';

    public string $promoDiscountType = 'percent';

    public string $promoDiscountValue = '';

    public string $promoPayBy = '';

    public string $promoCoversFrom = '';

    public string $promoCoversTo = '';

    public string $amnestyName = '';

    public string $amnestyEndsOn = '';

    public function mount(): void
    {
        $setting = Setting::current();
        $this->authorize('view', $setting);

        $this->membership_fee = (string) $setting->membership_fee;
        $this->penalty_type = $setting->penalty_type->value;
        $this->penalty_percent = (string) $setting->penalty_percent;
        $this->penalty_fixed_amount = (string) $setting->penalty_fixed_amount;
        $this->due_day = $setting->due_day;
        $this->grace_days = $setting->grace_days;
        $this->expiry_warning_days = $setting->expiry_warning_days;
        $this->hoa_name = $setting->hoa_name;
        $this->hoa_address = $setting->hoa_address;
        $this->rateMonth = now()->addMonth()->format('Y-m');
    }

    #[Computed]
    public function rates()
    {
        return MonthlyFeeRate::query()->latest('effective_from')->get();
    }

    #[Computed]
    public function currentRate(): string
    {
        return MonthlyFeeRate::amountFor(now()->year, now()->month);
    }

    #[Computed]
    public function amnesties()
    {
        return PenaltyAmnesty::query()->latest('ends_on')->get();
    }

    #[Computed]
    public function promos()
    {
        return AdvancePaymentPromo::query()->withCount('enrollments')->latest('pay_by')->get();
    }

    public function save(): void
    {
        $setting = Setting::current();
        $this->authorize('update', $setting);

        $validated = $this->validate([
            'membership_fee' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'penalty_type' => ['required', Rule::enum(PenaltyType::class)],
            'penalty_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'penalty_fixed_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'due_day' => ['required', 'integer', 'between:1,28'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:60'],
            'expiry_warning_days' => ['required', 'integer', 'min:1', 'max:365'],
            'hoa_name' => ['required', 'string', 'max:255'],
            'hoa_address' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($setting, $validated): void {
            $setting->update($validated);
        });

        Flux::toast(variant: 'success', text: __('Association settings saved.'));
    }

    public function addRate(): void
    {
        $this->authorize('update', Setting::current());

        $this->validate([
            'rateAmount' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'rateMonth' => ['required', 'date_format:Y-m', function (string $attribute, string $value, Closure $fail): void {
                if ($value < now()->format('Y-m') && $this->hasStatementsFrom($value)) {
                    $fail(__('A past month can only get a rate while no statements have been generated for it or any later month. Past statements keep the amount they were billed.'));
                }
            }],
            'rateRemarks' => ['nullable', 'string', 'max:255'],
        ]);

        $effectiveFrom = Carbon::createFromFormat('Y-m', $this->rateMonth)->startOfMonth()->toDateString();

        $rate = MonthlyFeeRate::query()->whereDate('effective_from', $effectiveFrom)->first() ?? new MonthlyFeeRate(['effective_from' => $effectiveFrom]);
        $rate->fill(['amount' => $this->rateAmount, 'remarks' => $this->rateRemarks ?: null, 'created_by' => auth()->id()])->save();

        $this->reset(['rateAmount', 'rateRemarks']);
        unset($this->rates, $this->currentRate);
        Flux::toast(variant: 'success', text: __('Monthly fee rate saved.'));
    }

    /**
     * Whether any dues statement has been generated for the given month (Y-m) or a later one.
     */
    private function hasStatementsFrom(string $month): bool
    {
        [$year, $monthNumber] = array_map('intval', explode('-', $month));

        return DuesStatement::query()->whereHas('billingPeriod', fn ($query) => $query
            ->where('year', '>', $year)
            ->orWhere(fn ($query) => $query->where('year', $year)->where('month', '>=', $monthNumber)))
            ->exists();
    }

    public function removeRate(int $rateId): void
    {
        $this->authorize('update', Setting::current());

        $rate = MonthlyFeeRate::query()->findOrFail($rateId);

        if ($rate->effective_from->lessThanOrEqualTo(now()->startOfMonth())) {
            Flux::toast(variant: 'danger', text: __('Only a rate that has not started yet can be removed.'));

            return;
        }

        $rate->delete();
        unset($this->rates, $this->currentRate);
    }

    public function addPromo(): void
    {
        $this->authorize('update', Setting::current());

        $this->validate([
            'promoName' => ['required', 'string', 'max:255'],
            'promoDiscountType' => ['required', Rule::enum(DiscountType::class)],
            'promoDiscountValue' => ['required', 'numeric', 'gt:0', $this->promoDiscountType === 'percent' ? 'max:100' : 'max:99999999.99'],
            'promoPayBy' => ['required', 'date', 'after_or_equal:today'],
            'promoCoversFrom' => ['required', 'date_format:Y-m'],
            'promoCoversTo' => ['required', 'date_format:Y-m', 'after_or_equal:promoCoversFrom'],
        ]);

        AdvancePaymentPromo::query()->create([
            'name' => $this->promoName,
            'discount_type' => $this->promoDiscountType,
            'discount_value' => $this->promoDiscountValue,
            'pay_by' => $this->promoPayBy,
            'covers_from' => $this->promoCoversFrom.'-01',
            'covers_to' => $this->promoCoversTo.'-01',
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        $this->reset(['promoName', 'promoDiscountValue', 'promoPayBy', 'promoCoversFrom', 'promoCoversTo']);
        unset($this->promos);
        Flux::toast(variant: 'success', text: __('Promo created.'));
    }

    public function addAmnesty(): void
    {
        $this->authorize('update', Setting::current());

        $this->validate([
            'amnestyName' => ['required', 'string', 'max:255'],
            'amnestyEndsOn' => ['required', 'date'],
        ]);

        PenaltyAmnesty::query()->create([
            'name' => $this->amnestyName,
            'ends_on' => $this->amnestyEndsOn,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        $this->reset(['amnestyName', 'amnestyEndsOn']);
        unset($this->amnesties);
        Flux::toast(variant: 'success', text: __('Penalty amnesty created. Balances update overnight, or as soon as a payment is recorded.'));
    }

    public function toggleAmnesty(int $amnestyId): void
    {
        $this->authorize('update', Setting::current());

        $amnesty = PenaltyAmnesty::query()->findOrFail($amnestyId);
        $amnesty->update(['is_active' => ! $amnesty->is_active]);
        unset($this->amnesties);
    }

    public function togglePromo(int $promoId): void
    {
        $this->authorize('update', Setting::current());

        $promo = AdvancePaymentPromo::query()->findOrFail($promoId);
        $promo->update(['is_active' => ! $promo->is_active]);
        unset($this->promos);
    }

    public function showHistory(): void
    {
        $setting = Setting::current();

        $this->dispatch(
            'open-record',
            title: $setting->hoa_name,
            type: Setting::class,
            id: $setting->id,
        );
    }
};
?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <x-page-header :title="__('Settings')" :subtitle="__('Fees, fines, promos and the association name used on printouts.')">
        <x-slot:actions>
            <flux:button variant="ghost" icon="clock" wire:click="showHistory">{{ __('Change history') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="space-y-6">
        <flux:heading size="lg">{{ __('Fees and fines') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Membership fee (one-time, new members)') }}</flux:label>
                <flux:input wire:model="membership_fee" inputmode="decimal" />
                <flux:error name="membership_fee" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Late payment fine') }}</flux:label>
                <flux:select wire:model.live="penalty_type">
                    <flux:select.option value="percent">{{ __('Percent of the month\'s dues, per month late') }}</flux:select.option>
                    <flux:select.option value="fixed">{{ __('Fixed amount per month late') }}</flux:select.option>
                </flux:select>
                <flux:error name="penalty_type" />
            </flux:field>
            @if ($penalty_type === 'fixed')
                <flux:field>
                    <flux:label>{{ __('Fine amount') }}</flux:label>
                    <flux:input wire:model="penalty_fixed_amount" inputmode="decimal" />
                    <flux:error name="penalty_fixed_amount" />
                </flux:field>
            @else
                <flux:field>
                    <flux:label>{{ __('Fine percent') }}</flux:label>
                    <flux:input wire:model="penalty_percent" inputmode="decimal" />
                    <flux:error name="penalty_percent" />
                </flux:field>
            @endif
            <flux:field>
                <flux:label>{{ __('Due day (of the following month)') }}</flux:label>
                <flux:input wire:model="due_day" type="number" min="1" max="28" />
                <flux:error name="due_day" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Grace days before a fine applies') }}</flux:label>
                <flux:input wire:model="grace_days" type="number" min="0" />
                <flux:error name="grace_days" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Expiry warning window (days)') }}</flux:label>
                <flux:input wire:model="expiry_warning_days" type="number" min="1" />
                <flux:error name="expiry_warning_days" />
            </flux:field>
            <flux:field class="sm:col-span-2">
                <flux:label>{{ __('HOA name') }}</flux:label>
                <flux:input wire:model="hoa_name" />
                <flux:error name="hoa_name" />
            </flux:field>
            <flux:field class="sm:col-span-2">
                <flux:label>{{ __('HOA address') }}</flux:label>
                <flux:textarea wire:model="hoa_address" rows="3" />
                <flux:error name="hoa_address" />
            </flux:field>
        </div>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
        </div>
    </form>

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Monthly fee') }}</flux:heading>
            <flux:text>{{ __('A flat fee charged once per owner each month, even if they own several properties. Current rate: ₱:rate.', ['rate' => number_format((float) $this->currentRate, 2)]) }}</flux:text>
        </div>
        <form wire:submit="addRate" class="grid gap-3 sm:grid-cols-4 sm:items-end">
            <flux:input :label="__('Amount')" wire:model="rateAmount" inputmode="decimal" />
            <flux:input :label="__('Effective from')" type="month" wire:model="rateMonth" />
            <flux:input :label="__('Remarks')" wire:model="rateRemarks" />
            <flux:button type="submit">{{ __('Save rate') }}</flux:button>
        </form>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Effective from') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Remarks') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->rates as $rate)
                    <flux:table.row wire:key="rate-{{ $rate->id }}">
                        <flux:table.cell>{{ $rate->effective_from->format('M Y') }}</flux:table.cell>
                        <flux:table.cell align="end">₱{{ number_format((float) $rate->amount, 2) }}</flux:table.cell>
                        <flux:table.cell>{{ $rate->remarks }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($rate->effective_from->greaterThan(now()->startOfMonth()))
                                <flux:button size="sm" variant="ghost" wire:click="removeRate({{ $rate->id }})">{{ __('Remove') }}</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="4">{{ __('No monthly fee set. Statements are billed at ₱0.00 until a rate is added.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Advance payment promos') }}</flux:heading>
            <flux:text>{{ __('Applied automatically: once an owner\'s payments made on or before the deadline cover everything billed through the last covered month (less the discount), the discount is credited to their account.') }}</flux:text>
        </div>
        <form wire:submit="addPromo" class="grid gap-3 sm:grid-cols-3 sm:items-end">
            <flux:input :label="__('Name')" wire:model="promoName" />
            <flux:select :label="__('Discount type')" wire:model.live="promoDiscountType">
                <flux:select.option value="percent">{{ __('Percent of covered dues') }}</flux:select.option>
                <flux:select.option value="fixed">{{ __('Fixed amount (total)') }}</flux:select.option>
            </flux:select>
            <flux:input :label="$promoDiscountType === 'percent' ? __('Discount %') : __('Discount amount')" wire:model="promoDiscountValue" inputmode="decimal" />
            <flux:input :label="__('Pay on or before')" type="date" wire:model="promoPayBy" />
            <flux:input :label="__('Covers from')" type="month" wire:model="promoCoversFrom" />
            <flux:input :label="__('Covers to')" type="month" wire:model="promoCoversTo" />
            <div class="sm:col-span-3 flex justify-end"><flux:button type="submit">{{ __('Create promo') }}</flux:button></div>
        </form>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Promo') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Availed') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->promos as $promo)
                    <flux:table.row wire:key="promo-{{ $promo->id }}">
                        <flux:table.cell>{{ $promo->label() }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $promo->enrollments_count }}</flux:table.cell>
                        <flux:table.cell><x-status-pill :label="$promo->is_active ? 'Active' : 'Inactive'" /></flux:table.cell>
                        <flux:table.cell align="end"><flux:button size="sm" variant="ghost" wire:click="togglePromo({{ $promo->id }})">{{ $promo->is_active ? __('Deactivate') : __('Activate') }}</flux:button></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="4">{{ __('No promos yet.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
<flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Penalty amnesty') }}</flux:heading>
            <flux:text>{{ __('Penalties assessed on or before the end date are held. Dues paid in full by then carry no penalty; dues still unpaid get every held penalty back the day after.') }}</flux:text>
        </div>
        <form wire:submit="addAmnesty" class="grid gap-3 sm:grid-cols-3 sm:items-end">
            <flux:input :label="__('Name')" wire:model="amnestyName" />
            <flux:input :label="__('Pay by (end date)')" type="date" wire:model="amnestyEndsOn" />
            <flux:button type="submit">{{ __('Create amnesty') }}</flux:button>
        </form>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Amnesty') }}</flux:table.column>
                <flux:table.column>{{ __('Ends') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->amnesties as $amnesty)
                    <flux:table.row wire:key="amnesty-{{ $amnesty->id }}">
                        <flux:table.cell>{{ $amnesty->name }}</flux:table.cell>
                        <flux:table.cell>{{ $amnesty->ends_on->format('M j, Y') }}</flux:table.cell>
                        <flux:table.cell><x-status-pill :active="$amnesty->is_active" /></flux:table.cell>
                        <flux:table.cell align="end"><flux:button size="sm" variant="ghost" wire:click="toggleAmnesty({{ $amnesty->id }})">{{ $amnesty->is_active ? __('Deactivate') : __('Activate') }}</flux:button></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="4">{{ __('No amnesties yet.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>

<?php

use App\Data\UserTeam;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public function currentTeam(): ?array
    {
        $team = Auth::user()->currentTeam;

        return $team ? [
            'id' => $team->id,
            'uuid' => $team->uuid,
            'name' => $team->name,
        ] : null;
    }

    /**
     * @return Collection<int, UserTeam>
     */
    public function teams(): Collection
    {
        return Auth::user()->toUserTeams(includeCurrent: true);
    }

    public function switchTeam(string $uuid): void
    {
        $user = Auth::user();

        abort_unless(
            $user->belongsToTeam($team = Team::where('uuid', $uuid)->firstOrFail()),
            403
        );

        $currentTeamUuid = $user->currentTeam?->uuid;

        $user->switchTeam($team);

        // اگر referer نداریم یا تیم قبلی نبود → dashboard
        $referer = request()->header('Referer');
        if (! $referer || ! $currentTeamUuid) {
            $this->redirectRoute('dashboard', ['current_team' => $team->uuid], navigate: true);
            return;
        }

        // استخراج مسیر بعد از UUID تیم قبلی در referer
        // از ~ به عنوان delimiter استفاده می‌کنیم تا با # تداخل نکند
        $pattern = '~/'.preg_quote($currentTeamUuid, '~').'(/[^?#]*)?~';
        if (! preg_match($pattern, $referer, $m)) {
            $this->redirectRoute('dashboard', ['current_team' => $team->uuid], navigate: true);
            return;
        }

        $path = $m[1] ?? '/';

        // فقط مسیرهای مستقل از منابع تیم را نگه دار
        $safePrefixes = ['/dashboard', '/khoneh', '/settings'];
        $isSafe = false;
        foreach ($safePrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $isSafe = true;
                break;
            }
        }

        if (! $isSafe) {
            $this->redirectRoute('dashboard', ['current_team' => $team->uuid], navigate: true);
            return;
        }

        $this->redirect('/'.$team->uuid.$path, navigate: true);
    }
}; ?>

<div>
    <flux:dropdown position="bottom" align="start">
        <flux:button variant="ghost" class="group w-full justify-start in-data-flux-sidebar-collapsed-desktop:justify-center" data-test="team-switcher-trigger">
            <flux:icon name="users" class="hidden size-4 in-data-flux-sidebar-collapsed-desktop:block" />
            <span class="truncate font-semibold in-data-flux-sidebar-collapsed-desktop:hidden">{{ $this->currentTeam()['name'] ?? __('Select team') }}</span>
            <flux:icon
                name="chevrons-up-down"
                variant="micro"
                class="ms-auto size-4 in-data-flux-sidebar-collapsed-desktop:hidden"
            />
        </flux:button>

        <flux:menu class="min-w-56">
            <flux:menu.heading>{{ __('Teams') }}</flux:menu.heading>

            @foreach ($this->teams() as $team)
                <flux:menu.item
                    wire:click="switchTeam('{{ $team->uuid }}')"
                    class="cursor-pointer"
                    data-test="team-switcher-item"
                >
                    <div class="flex w-full items-center justify-between">
                        <span>{{ $team->name }}</span>
                        @if ($team->isCurrent)
                            <flux:icon name="check" class="size-4" />
                        @endif
                    </div>
                </flux:menu.item>
            @endforeach

            <flux:menu.separator />

            <flux:modal.trigger name="create-team-switcher">
                <flux:menu.item icon="plus" class="cursor-pointer" data-test="team-switcher-new-team">
                    {{ __('New team') }}
                </flux:menu.item>
            </flux:modal.trigger>
        </flux:menu>
    </flux:dropdown>
</div>
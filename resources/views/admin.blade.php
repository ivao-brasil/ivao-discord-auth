<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F2F2F7" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
    <title>{{ __('admin.brandSub') }} · {{ config('brauth.title') }}</title>
    <link rel="icon" href="{{ asset('assets/imgs/ivao-br.svg') }}">
    <link rel="stylesheet" href="@versioned('css/app.css')">
    <link rel="stylesheet" href="@versioned('css/admin.css')">
    <script defer src="@versioned('js/admin.js')"></script>
    <script defer src="{{ asset('js/vendor/alpine-3.14.9.min.js') }}"></script>
</head>
<body>
@include('partials.icons')

<div class="app" x-data="admin(@js($settings))" @keydown.escape.window="closeSheet()">
    <aside class="sidebar">
        <div class="brand">
            <img src="{{ asset('assets/imgs/ivao-br.svg') }}" alt="">
            <div>
                <div class="brand__name">{{ __('admin.brand') }}</div>
                <div class="brand__sub">{{ __('admin.brandSub') }}</div>
            </div>
        </div>
        <button type="button" class="nav-item" @click="page = 'rules'" :aria-current="page === 'rules' && 'page'">
            <svg><use href="#icon-rules"/></svg>{{ __('admin.nav.rules') }}
        </button>
        <button type="button" class="nav-item" @click="page = 'members'" :aria-current="page === 'members' && 'page'">
            <svg><use href="#icon-members"/></svg>{{ __('admin.nav.members') }}
        </button>
        <div class="sidebar__user">{{ $settings['user']['name'] }} · VID {{ $settings['user']['vid'] }}</div>
    </aside>

    <main class="main">
        {{-- Rules --}}
        <section class="page" x-show="page === 'rules'">
            <div class="topbar">
                <button type="button" class="text-button" @click="openRule()" :disabled="rulesLoading || rulesError">{{ __('admin.rules.new') }}</button>
            </div>
            <h1 class="large-title">{{ __('admin.rules.title') }}</h1>
            <p class="page-sub">{{ __('admin.rules.subtitle') }}</p>

            <div class="group">
                <div class="status-card">
                    <div class="icon-tile"><svg><use href="#icon-sync"/></svg></div>
                    <div class="row__body">
                        <div class="row__title" x-text="syncTitle"></div>
                        <div class="row__detail" x-text="syncDetail"></div>
                    </div>
                </div>
            </div>

            <div class="section-header" x-show="!rulesLoading && !rulesError" x-text="rulesCount"></div>
            <div class="group" x-show="rulesLoading || rulesError || rules.length === 0" style="margin-top: 24px">
                <div class="placeholder" x-show="rulesLoading">…</div>
                <div class="placeholder text-danger" x-show="rulesError" x-text="rulesError"></div>
                <div class="placeholder" x-show="!rulesLoading && !rulesError && rules.length === 0">{{ __('admin.rules.empty') }}</div>
            </div>
            <div class="group" x-show="!rulesLoading && rules.length > 0">
                <template x-for="rule in rules" :key="rule.id">
                    <button type="button" class="row" @click="openRule(rule)">
                        <span class="row__body">
                            <span class="row__title" x-text="rule.name || '—'"></span>
                            <span class="row__detail" style="display: block" x-text="ruleSummary(rule)"></span>
                            <span class="roles">
                                <template x-for="roleId in rule.roles" :key="roleId">
                                    <span class="role" :style="`--role: ${roleColor(roleId)}`">
                                        <span class="role__dot"></span><span class="truncate" x-text="role(roleId).name"></span>
                                    </span>
                                </template>
                            </span>
                        </span>
                        <svg class="chevron"><use href="#icon-chevron"/></svg>
                    </button>
                </template>
            </div>
            <p class="section-footer">{{ __('admin.rules.footer', ['hours' => $settings['minHours']]) }}</p>
        </section>

        {{-- Members --}}
        <section class="page" x-show="page === 'members'" x-cloak>
            <div class="topbar"></div>
            <h1 class="large-title">{{ __('admin.members.title') }}</h1>
            <p class="page-sub">{{ __('admin.members.subtitle') }}</p>

            <div class="members-filters">
                <label class="search">
                    <svg><use href="#icon-search"/></svg>
                    <span class="visually-hidden">{{ __('admin.members.search') }}</span>
                    <input type="search" placeholder="{{ __('admin.members.search') }}" x-model="search" autocomplete="off">
                </label>

                <div class="segmented" role="group">
                    <button type="button" @click="statusFilter = null" :aria-pressed="String(statusFilter === null)">{{ __('admin.members.all') }}</button>
                    <button type="button" @click="statusFilter = 'away'" :aria-pressed="String(statusFilter === 'away')">{{ __('admin.members.away') }}</button>
                    <button type="button" @click="statusFilter = 'pending'" :aria-pressed="String(statusFilter === 'pending')">{{ __('admin.members.pending') }}</button>
                </div>
            </div>

            <div class="section-header" x-text="membersCount"></div>
            <div class="group">
                <template x-for="account in members" :key="account.id">
                    <button type="button" class="row row--inset" @click="openMember(account)">
                        <span class="avatar" x-text="(account.nickname || '?').charAt(0)"></span>
                        <span class="row__body">
                            <span class="row__title truncate" style="display: block" x-text="account.nickname"></span>
                            <span class="row__detail" style="display: block" x-text="format(t.members.since, { vid: account.vid, date: formatDate(account.linkedAt) })"></span>
                        </span>
                        <span class="status" :class="account.status && `status--${account.status}`" x-text="statusLabel(account.status)"></span>
                        <svg class="chevron"><use href="#icon-chevron"/></svg>
                    </button>
                </template>
                <div class="placeholder" x-show="!membersLoading && !membersError && members.length === 0">{{ __('admin.members.empty') }}</div>
                <div class="placeholder text-danger" x-show="membersError" x-text="membersError"></div>
                <button type="button" class="row row--center text-accent" x-show="nextPage" @click="loadMembers(nextPage)" :disabled="membersLoading">
                    {{ __('admin.members.loadMore') }}
                </button>
            </div>
        </section>
    </main>

    <nav class="tabbar">
        <button type="button" class="tab" @click="page = 'rules'" :aria-current="page === 'rules' && 'page'">
            <svg><use href="#icon-rules"/></svg>{{ __('admin.nav.rules') }}
        </button>
        <button type="button" class="tab" @click="page = 'members'" :aria-current="page === 'members' && 'page'">
            <svg><use href="#icon-members"/></svg>{{ __('admin.nav.members') }}
        </button>
    </nav>

    <div class="scrim" x-show="sheet" x-cloak @click="closeSheet()"
         x-transition:enter="fade-enter" x-transition:enter-start="fade-hidden"
         x-transition:leave="fade-enter" x-transition:leave-end="fade-hidden"></div>

    {{-- Rule editor --}}
    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="rule-sheet-title" x-show="sheet === 'rule'" x-cloak
         x-transition:enter="sheet-enter" x-transition:enter-start="sheet-hidden"
         x-transition:leave="sheet-enter" x-transition:leave-end="sheet-hidden">
        <div class="sheet__grabber"></div>
        <template x-if="draft">
            <div style="display: contents">
                <div class="sheet__bar">
                    <button type="button" class="text-button" @click="closeSheet()" :disabled="busy">{{ __('admin.cancel') }}</button>
                    <div class="sheet__title" id="rule-sheet-title" x-text="draftIsNew ? t.rules.new : t.rules.edit"></div>
                    <button type="button" class="text-button text-button--bold" @click="saveRule()" :disabled="busy">{{ __('admin.save') }}</button>
                </div>

                <form class="sheet__content" @submit.prevent="saveRule()">
                    <p class="sheet__error" x-show="sheetError" x-text="sheetError"></p>

                    <div class="section-header">{{ __('admin.rules.name') }}</div>
                    <div class="group">
                        <label class="row">
                            <span class="visually-hidden">{{ __('admin.rules.name') }}</span>
                            <input class="field field--left" x-model="draft.name" maxlength="60" placeholder="{{ __('admin.rules.namePlaceholder') }}">
                        </label>
                    </div>

                    <div class="section-header">{{ __('admin.rules.roles') }}</div>
                    <label class="search">
                        <svg><use href="#icon-search"/></svg>
                        <span class="visually-hidden">{{ __('admin.rules.searchRoles') }}</span>
                        <input type="search" placeholder="{{ __('admin.rules.searchRoles') }}" x-model="roleSearch" autocomplete="off">
                    </label>
                    <div class="group role-picker">
                        <template x-for="role in filteredRoles" :key="role.id">
                            <button type="button" class="row" @click="toggleRole(role)" :disabled="!role.assignable" :aria-pressed="String(draft.roles.includes(role.id))">
                                <span class="role__dot" :style="`--role: ${role.color ?? '#99AAB5'}; width: 12px; height: 12px`"></span>
                                <span class="row__body">
                                    <span class="row__title" style="display: block" x-text="role.name"></span>
                                    <span class="row__detail" style="display: block" x-show="roleHint(role)" x-text="roleHint(role)"></span>
                                </span>
                                <svg class="role-option__check" x-show="draft.roles.includes(role.id)"><use href="#icon-check"/></svg>
                            </button>
                        </template>
                    </div>

                    <div class="section-header">{{ __('admin.rules.staff') }}</div>
                    <div class="group">
                        <div class="tokens">
                            <template x-for="position in draft.staff" :key="position">
                                <span class="token">
                                    <span x-text="position"></span>
                                    <button type="button" @click="removePosition(position)" :aria-label="position">×</button>
                                </span>
                            </template>
                            <input class="token-input" x-model="positionInput" placeholder="{{ __('admin.rules.addPosition') }}"
                                   @keydown.enter.prevent="addPositions()" @keydown.space.prevent="addPositions()" @keydown.comma.prevent="addPositions()" @blur="addPositions()">
                        </div>
                        <label class="row">
                            <span class="row__body row__title">{{ __('admin.rules.includeTrial') }}</span>
                            <span class="switch"><input type="checkbox" x-model="draft.includeTrial"><span></span></span>
                        </label>
                    </div>
                    <p class="section-footer">{{ __('admin.rules.staffFooter') }}</p>

                    <div class="section-header">{{ __('admin.rules.division') }}</div>
                    <div class="group">
                        <div class="row" style="display: block">
                            <div class="segmented" role="group">
                                <button type="button" @click="draft.divisionMode = 'any'" :aria-pressed="String(draft.divisionMode === 'any')">{{ __('admin.rules.divisionAny') }}</button>
                                <button type="button" @click="draft.divisionMode = 'in'" :aria-pressed="String(draft.divisionMode === 'in')">{{ __('admin.rules.divisionIn') }}</button>
                                <button type="button" @click="draft.divisionMode = 'not_in'" :aria-pressed="String(draft.divisionMode === 'not_in')">{{ __('admin.rules.divisionNotIn') }}</button>
                            </div>
                        </div>
                        <label class="row" x-show="draft.divisionMode !== 'any'">
                            <span class="row__body row__title">{{ __('admin.rules.divisions') }}</span>
                            <input class="field" x-model="divisionsText" placeholder="BR" autocapitalize="characters">
                        </label>
                    </div>

                    <div class="section-header">{{ __('admin.rules.requirements') }}</div>
                    <div class="group">
                        <label class="row">
                            <span class="row__body row__title">{{ __('admin.rules.minAtcRating') }}</span>
                            <select class="field" x-model="draft.minAtcRating">
                                <option value="">{{ __('admin.rules.none') }}</option>
                                @foreach ($settings['ratings']['atc'] as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            <svg class="chevron"><use href="#icon-chevron"/></svg>
                        </label>
                        <label class="row">
                            <span class="row__body row__title">{{ __('admin.rules.minPilotRating') }}</span>
                            <select class="field" x-model="draft.minPilotRating">
                                <option value="">{{ __('admin.rules.none') }}</option>
                                @foreach ($settings['ratings']['pilot'] as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            <svg class="chevron"><use href="#icon-chevron"/></svg>
                        </label>
                        <div class="row">
                            <span class="row__body">
                                <span class="row__title" style="display: block">{{ __('admin.rules.minHours') }}</span>
                                <span class="row__detail" style="display: block" x-text="draft.minHours ? format(t.rules.hoursValue, { hours: draft.minHours }) : t.rules.anyHours"></span>
                            </span>
                            <span class="stepper">
                                <button type="button" @click="stepHours(-10)" aria-label="−10">−</button>
                                <button type="button" @click="stepHours(10)" aria-label="+10">+</button>
                            </span>
                        </div>
                        <label class="row">
                            <span class="row__body row__title">{{ __('admin.rules.requiresGca') }}</span>
                            <span class="switch"><input type="checkbox" x-model="draft.requiresGca"><span></span></span>
                        </label>
                        <label class="row">
                            <span class="row__body row__title">{{ __('admin.rules.requiresVaOwnership') }}</span>
                            <span class="switch"><input type="checkbox" x-model="draft.requiresVaOwnership"><span></span></span>
                        </label>
                    </div>

                    <div class="group" style="margin-top: 32px" x-show="!draftIsNew">
                        <button type="button" class="row row--center text-danger" x-show="!confirmDelete" @click="confirmDelete = true">{{ __('admin.rules.delete') }}</button>
                        <div class="row" style="display: block" x-show="confirmDelete">
                            <p class="row__detail" style="margin: 0 0 12px" x-text="format(t.rules.deleteConfirm, { name: draft.name })"></p>
                            <button type="button" class="button button--danger button--block" @click="deleteRule()" :disabled="busy">{{ __('admin.rules.delete') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </template>
    </div>

    {{-- Member --}}
    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="member-sheet-title" x-show="sheet === 'member'" x-cloak
         x-transition:enter="sheet-enter" x-transition:enter-start="sheet-hidden"
         x-transition:leave="sheet-enter" x-transition:leave-end="sheet-hidden">
        <div class="sheet__grabber"></div>
        <div class="sheet__bar">
            <span></span>
            <div class="sheet__title truncate" id="member-sheet-title" x-text="memberSummary?.nickname || t.members.member"></div>
            <button type="button" class="text-button text-button--bold" @click="closeSheet()" :disabled="busy">{{ __('admin.members.done') }}</button>
        </div>

        <div class="sheet__content">
            <p class="sheet__error" x-show="sheetError" x-text="sheetError"></p>
            <div class="placeholder" x-show="!member && !sheetError">{{ __('admin.members.loading') }}</div>

            <template x-if="member">
                <div>
                    <div class="section-header">{{ __('admin.members.discord') }}</div>
                    <div class="group">
                        <div class="row" x-show="member.away">
                            <span class="row__body text-muted">{{ __('admin.members.notInServer') }}</span>
                        </div>
                        <template x-if="member.discord">
                            <div>
                                <div class="row">
                                    <span class="row__title">{{ __('admin.members.nickname') }}</span>
                                    <span class="row__body row__value truncate" x-text="member.discord.nickname || '—'"></span>
                                </div>
                                <div class="row" style="display: block">
                                    <span class="row__title">{{ __('admin.members.roles') }}</span>
                                    <span class="roles">
                                        <template x-for="name in member.discord.roles" :key="name">
                                            <span class="role"><span class="role__dot"></span><span class="truncate" x-text="name"></span></span>
                                        </template>
                                    </span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="section-header">{{ __('admin.members.ivao') }}</div>
                    <div class="group">
                        <div class="row">
                            <span class="row__body row__title">{{ __('admin.members.vid') }}</span>
                            <span class="row__value" x-text="member.vid"></span>
                        </div>
                        <template x-if="member.ivao && !member.ivao.found">
                            <div class="row"><span class="row__body text-warning">{{ __('admin.members.notFound') }}</span></div>
                        </template>
                        <template x-if="member.ivao?.found">
                            <div>
                                <div class="row">
                                    <span class="row__body row__title">{{ __('admin.members.account') }}</span>
                                    <span class="row__value" :class="!member.ivao.active && 'text-warning'" x-text="member.ivao.active ? t.members.active : t.members.inactive"></span>
                                </div>
                                <div class="row">
                                    <span class="row__body row__title">{{ __('admin.members.divisionLabel') }}</span>
                                    <span class="row__value" x-text="member.ivao.division || '—'"></span>
                                </div>
                                <div class="row">
                                    <span class="row__title">{{ __('admin.members.staffLabel') }}</span>
                                    <span class="row__body row__value" x-text="member.ivao.staff.join(', ') || t.members.noStaff"></span>
                                </div>
                                <div class="row">
                                    <span class="row__body row__title">{{ __('admin.members.hours') }}</span>
                                    <span class="row__value" x-text="member.ivao.hours.toLocaleString()"></span>
                                </div>
                                <div class="row" x-show="member.ivao.active && !member.ivao.eligible">
                                    <span class="row__body text-warning">{{ __('admin.members.notEligible') }}</span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <template x-if="!member.away">
                        <div>
                            <div class="section-header">{{ __('admin.members.nextSync') }}</div>
                            <div class="group">
                                <div class="row" x-show="!memberHasChanges"><span class="row__body text-muted">{{ __('admin.members.upToDate') }}</span></div>
                                <template x-for="name in member.changes.add" :key="`add-${name}`">
                                    <div class="row"><span class="row__body diff text-success" x-text="format(t.members.addRole, { role: name })"></span></div>
                                </template>
                                <template x-for="name in member.changes.remove" :key="`remove-${name}`">
                                    <div class="row"><span class="row__body diff text-danger" x-text="format(t.members.removeRole, { role: name })"></span></div>
                                </template>
                                <div class="row" x-show="member.changes.nickname">
                                    <span class="row__body diff" x-text="format(t.members.setNickname, { nickname: member.changes.nickname })"></span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="group" style="margin-top: 32px">
                        <button type="button" class="row row--center text-accent" x-show="!member.away" @click="syncMember()" :disabled="busy"
                                x-text="busy ? t.members.syncing : t.members.syncNow"></button>
                        <button type="button" class="row row--center text-danger" x-show="!member.away && !confirmRemove" @click="confirmRemove = true" :disabled="busy">
                            {{ __('admin.members.remove') }}
                        </button>
                        <div class="row" style="display: block" x-show="confirmRemove">
                            <p class="row__detail" style="margin: 0 0 12px" x-text="format(t.members.removeConfirm, { nickname: memberSummary.nickname })"></p>
                            <button type="button" class="button button--danger button--block" @click="removeMember()" :disabled="busy">{{ __('admin.members.removeConfirmButton') }}</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div class="toast" role="status" x-show="toast" x-cloak x-text="toast"
         x-transition:enter="fade-enter" x-transition:enter-start="fade-hidden"
         x-transition:leave="fade-enter" x-transition:leave-end="fade-hidden"></div>
</div>
</body>
</html>

<x-layouts.app :title="$title" active="login">
    <div class="flex items-center justify-center py-12">
        <div class="card w-full max-w-sm">
            <div class="p-6">
                <div class="mb-6 text-center">
                    <i class="ti ti-lock-square-rounded text-5xl text-brand dark:text-night-brand" aria-hidden="true"></i>
                </div>

                @error('login')
                    <div class="mb-4 flex items-center rounded-md border border-clay/30 bg-clay/10 p-3 text-sm text-clay dark:border-night-clay/30 dark:bg-night-clay/10 dark:text-night-clay" role="alert">
                        <i class="ti ti-alert-circle-filled me-2" aria-hidden="true"></i>
                        <div>{{ $message }}</div>
                    </div>
                @enderror

                <form action="/admin/login" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="username" class="sr-only">{{ __('admin.login.username') }}</label>
                        <input id="username" name="username" type="text" required autofocus
                            value="{{ old('username') }}"
                            class="input" placeholder="{{ __('admin.login.username') }}">
                    </div>
                    <div class="mb-4">
                        <label for="password" class="sr-only">{{ __('admin.login.password') }}</label>
                        <input id="password" name="password" type="password" required
                            class="input" placeholder="{{ __('admin.login.password') }}">
                    </div>

                    <button type="submit" class="btn-primary w-full justify-center">
                        <i class="ti ti-login" aria-hidden="true"></i>
                        {{ __('admin.login.submit') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>

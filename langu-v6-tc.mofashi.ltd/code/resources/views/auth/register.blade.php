<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/">
                <x-application-logo class="w-20 h-20 fill-current text-gray-500 text-4xl" />
            </a>
        </x-slot>

        <!-- Validation Errors -->
        <x-auth-validation-errors class="mb-4" :errors="$errors" />

        <form method="POST" action="{{ route('register') }}" id="registration-form">
            @csrf

            <!-- Name -->
            <div>
                <x-label for="name" :value="__('Name')" />
                <x-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            </div>

            <!-- Email Address -->
            <div class="mt-4">
                <x-label for="email" :value="__('Email（仅支持QQ邮箱）')" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" placeholder="example@qq.com" required autocomplete="email" />
                <span class="block mt-1 text-sm text-gray-500">本站仅支持 QQ 邮箱（@qq.com）注册</span>
                <span id="email-error" class="text-red-500 text-sm hidden">请使用 QQ 邮箱（@qq.com）注册</span>
            </div>

            <!-- Password -->
            <div class="mt-4">
                <x-label for="password" :value="__('Password')" />
                <x-input id="password" class="block mt-1 w-full"
                                type="password"
                                name="password"
                                required autocomplete="new-password" />
            </div>

            <!-- Confirm Password -->
            <div class="mt-4">
                <x-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-input id="password_confirmation" class="block mt-1 w-full"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('login') }}">
                    {{ __('Already registered?') }}
                </a>

                <x-button class="ml-4">
                    {{ __('Register') }}
                </x-button>
            </div>
        </form>
    </x-auth-card>

    <script>
        document.getElementById('registration-form').addEventListener('submit', function(event) {
            const emailInput = document.getElementById('email');
            const emailError = document.getElementById('email-error');
            const validDomains = ['qq.com']; // 允许的邮箱后缀

            // 统一去空格并转小写再判断，避免用户输入大写后缀（如 QQ.COM）时被误判
            const email = emailInput.value.trim().toLowerCase();
            const domain = email.substring(email.lastIndexOf('@') + 1);

            if (!email.includes('@') || !validDomains.includes(domain)) {
                emailError.classList.remove('hidden');
                event.preventDefault();
            } else {
                emailError.classList.add('hidden');
            }
        });
    </script>
</x-guest-layout>

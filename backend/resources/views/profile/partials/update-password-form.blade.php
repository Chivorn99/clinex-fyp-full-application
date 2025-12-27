<form method="post" action="{{ route('password.update') }}" class="space-y-6">
    @csrf
    @method('put')

    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
        <div class="col-span-1 sm:col-span-2">
            <label for="update_password_current_password" class="block text-sm font-medium text-gray-700">
                {{ __('Current Password') }}
            </label>
            <div class="mt-1 relative rounded-md shadow-sm">
                <input type="password" id="update_password_current_password" name="current_password"
                    class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                    autocomplete="current-password">
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                    <button type="button" class="text-gray-400 hover:text-gray-500 password-toggle"
                        data-target="update_password_current_password">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>
            @error('current_password', 'updatePassword')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="col-span-1">
            <label for="update_password_password" class="block text-sm font-medium text-gray-700">
                {{ __('New Password') }}
            </label>
            <div class="mt-1 relative rounded-md shadow-sm">
                <input type="password" id="update_password_password" name="password"
                    class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                    autocomplete="new-password">
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                    <button type="button" class="text-gray-400 hover:text-gray-500 password-toggle"
                        data-target="update_password_password">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>
            @error('password', 'updatePassword')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-gray-500">Minimum 8 characters</p>
        </div>

        <div class="col-span-1">
            <label for="update_password_password_confirmation" class="block text-sm font-medium text-gray-700">
                {{ __('Confirm Password') }}
            </label>
            <div class="mt-1 relative rounded-md shadow-sm">
                <input type="password" id="update_password_password_confirmation" name="password_confirmation"
                    class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                    autocomplete="new-password">
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                    <button type="button" class="text-gray-400 hover:text-gray-500 password-toggle"
                        data-target="update_password_password_confirmation">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>
            @error('password_confirmation', 'updatePassword')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="mt-8 border-t border-gray-200 pt-5">
        <div class="flex justify-end">
            <button type="submit"
                class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                {{ __('Update Password') }}
            </button>

            @if (session('status') === 'password-updated')
                <div class="ml-3 py-2 px-4 bg-green-100 text-green-700 text-sm font-medium rounded-md transition-opacity">
                    {{ __('Password updated!') }}
                </div>
            @endif
        </div>
    </div>

    <!-- JavaScript for password toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.password-toggle').forEach(button => {
                button.addEventListener('click', function () {
                    const targetId = this.getAttribute('data-target');
                    const passwordInput = document.getElementById(targetId);

                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        this.innerHTML = `<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>`;
                    } else {
                        passwordInput.type = 'password';
                        this.innerHTML = `<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>`;
                    }
                });
            });
        });
    </script>
</form>
</section>
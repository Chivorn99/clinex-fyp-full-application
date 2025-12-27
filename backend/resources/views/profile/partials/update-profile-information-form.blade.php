<form id="send-verification" method="post" action="{{ route('verification.send') }}">
    @csrf
</form>

<form method="post" action="{{ route('profile.update') }}" class="space-y-6">
    @csrf
    @method('patch')

    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
        <div class="col-span-1">
            <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
            <div class="mt-1">
                <input type="text" name="name" id="name"
                    class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                    value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
            </div>
            @error('name')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="col-span-1">
            <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
            <div class="mt-1">
                <input type="email" name="email" id="email"
                    class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                    value="{{ old('email', $user->email) }}" required autocomplete="username">
            </div>
            @error('email')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification"
                            class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
            <div class="col-span-2">
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">
                                {{ __('Your email address is unverified.') }}
                            </h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>
                                    <button form="send-verification"
                                        class="font-medium text-blue-600 hover:text-blue-500 underline">
                                        {{ __('Click here to re-send the verification email.') }}
                                    </button>
                                </p>
                            </div>
                            @if (session('status') === 'verification-link-sent')
                                <div class="mt-2 text-sm font-medium text-green-600">
                                    {{ __('A new verification link has been sent to your email address.') }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Role (Only show for admins) -->
        @if(method_exists($user, 'isAdmin') && $user->isAdmin())
            <div class="col-span-2">
                <label for="role" class="block text-sm font-medium text-gray-700">{{ __('Role') }}</label>
                <div class="mt-1">
                    <select id="role" name="role"
                            class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            <option value="admin" {{ property_exists($user, 'role') && $user->role == 'admin' ? 'selected' : '' }}>Administrator</option>
                            <option value="lab_technician" {{ property_exists($user, 'role') && $user->role == 'lab_technician' ? 'selected' : '' }}>Lab Technician
                            </option>
                        </select>
                </div>
                @error('role')
                    <p class="mt-2 text-sm text-red-600">{{ $errors->first('role') }}</p>
                @enderror
            </div>
        @elseif(property_exists($user, 'role'))
            <div class="col-span-2">
                <label for="role_display" class="block text-sm font-medium text-gray-700">{{ __('Role') }}</label>
                <div class="mt-1">
                    <input type="text" id="role_display"
                        class="bg-gray-100 shadow-sm block w-full sm:text-sm border-gray-300 rounded-md cursor-not-allowed"
                        value="{{ ucwords(str_replace('_', ' ', $user->role)) }}" readonly>
                </div>
                <p class="mt-1 text-sm text-gray-500">{{ __('Contact an administrator to change your role.') }}</p>
            </div>
        @endif
    </div>

    <div class="mt-8 border-t border-gray-200 pt-5">
        <div class="flex justify-end">
            <button type="submit"
                class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                {{ __('Save Changes') }}
            </button>

            @if (session('status') === 'profile-updated')
                <div class="ml-3 py-2 px-4 bg-green-100 text-green-700 text-sm font-medium rounded-md transition-opacity">
                    {{ __('Saved successfully!') }}
                </div>
            @endif
        </div>
    </div>
</form>
</div>
</form>
</section>
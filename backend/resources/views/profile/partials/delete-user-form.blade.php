<div class="space-y-6">
    <p class="text-sm text-gray-500">
        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
    </p>

    <div>
        <button type="button" id="delete-account-button"
            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200">
            {{ __('Delete Account') }}
        </button>
    </div>

    <!-- Delete User Confirmation Modal -->
    <div id="delete-user-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden"
        style="background-color: rgba(0, 0, 0, 0.5);">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
            <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
                @csrf
                @method('delete')

                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <h3 class="mt-5 text-lg leading-6 font-medium text-gray-900">
                        {{ __('Delete Account') }}
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-600">
                            {{ __('Are you sure you want to delete your account? Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm.') }}
                        </p>
                    </div>
                </div>

                <div class="mt-4">
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        {{ __('Password') }}
                    </label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <input id="password" name="password" type="password" placeholder="Your current password"
                            class="shadow-sm focus:ring-red-500 focus:border-red-500 block w-full sm:text-sm border-gray-300 rounded-md" />
                    </div>
                    @error('password', 'userDeletion')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" id="cancel-delete"
                        class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit"
                        class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        {{ __('Delete Account') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const deleteButton = document.getElementById('delete-account-button');
            const modal = document.getElementById('delete-user-modal');
            const cancelButton = document.getElementById('cancel-delete');

            deleteButton.addEventListener('click', function () {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });

            cancelButton.addEventListener('click', function () {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });

            // Close modal when clicking outside
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        });
    </script>
    </section>
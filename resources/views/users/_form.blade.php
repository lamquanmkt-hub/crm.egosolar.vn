<form action="{{ $action }}" method="POST" class="tw:mt-4">
    @csrf
    @if($method === 'PUT')
        @method('PUT')
    @endif

    <div class="tw:mb-4">
        <label>Name</label>
        <x-ui.input type="text"
               name="name"
               value="{{ old('name', $user->name ?? '') }}"
               required />
    </div>

    <div class="tw:mb-4">
        <label>Email</label>
        <x-ui.input type="email"
               name="email"
               value="{{ old('email', $user->email ?? '') }}"
               required />
    </div>

    <div class="tw:mb-4">
        <label>Password</label>
        <x-ui.input type="password"
               name="password"
               :required="! $user" />
        @if($user)
            <small class="tw:text-[rgba(33,37,41,0.75)]">(Để trống nếu không đổi mật khẩu)</small>
        @endif
    </div>

    <div class="tw:mb-4">
        <label>Confirm Password</label>
        <x-ui.input type="password"
               name="password_confirmation"
               :required="! $user" />
    </div>

    @role('admin')
    <div class="tw:mb-4">
        <label>Roles</label>
        <x-ui.input as="select" name="roles[]" multiple>
            @foreach(\Spatie\Permission\Models\Role::all() as $role)
                <option value="{{ $role->name }}"
                        @if($user && $user->hasRole($role->name)) selected @endif>
                    {{ $role->name }}
                </option>
            @endforeach
        </x-ui.input>
    </div>
    @endrole

    <x-ui.button variant="success" type="submit">Save</x-ui.button>
</form>

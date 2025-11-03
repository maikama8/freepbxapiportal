<img 
    src="{{ $getAvatarUrl() }}" 
    alt="{{ $user->name ?? 'User' }}" 
    class="rounded-circle {{ $class }}" 
    width="{{ $size }}" 
    height="{{ $size }}"
    style="object-fit: cover;"
    onerror="this.src='{{ asset('images/placeholders/user-default.svg') }}'"
>
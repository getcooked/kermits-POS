@php($name = $name ?? 'home')
<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
@switch($name)
    @case('home')<path d="M3 11.5 12 4l9 7.5M5.5 10v10h13V10M9.5 20v-6h5v6"/>@break
    @case('pos')<path d="M4 5h2l1.5 9h9.8l2-6H7M9 18.5h.01M17 18.5h.01"/>@break
    @case('reports')<path d="M5 20V10h3v10M10.5 20V4h3v16M16 20v-7h3v7M3 20h18"/>@break
    @case('inventory')<path d="m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7M12 11v10"/>@break
    @case('reservations')<path d="M5 5h14v15H5zM8 3v4M16 3v4M5 9h14M8 13h3M13 13h3M8 16h3"/>@break
    @case('products')<path d="M4 5h16v14H4zM8 9h8M8 13h8M8 17h5"/>@break
    @case('crud')<path d="M5 5h14v14H5zM8 9h8M8 13h5M16.5 14.5l2 2-2 2M7.5 14.5l-2 2 2 2"/>@break
    @case('users')<path d="M8.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM2.5 20v-2a5.5 5.5 0 0 1 11 0v2M16 11a3 3 0 1 0 0-6M15.5 14a5 5 0 0 1 6 4.9V20"/>@break
    @case('cashier')<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM5 21v-2a7 7 0 0 1 14 0v2M9 16h6"/>@break
    @case('settings')<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="m19 13.5 2-1.5-2-1.5-.5-1.3.4-2.5-2.5-.4-1.3-.8L13.5 2 12 4l-1.5.5L8.5 3 7 5l-1.5.5-.4 2.5-1.3.5L2 10l2 1.5v1.5L2.5 15l2 1.5.5 1.3-.4 2.5 2.5.4 1.3.8L10.5 22l1.5-2 1.5-.5 2 1.5 1.5-2 1.5-.5.4-2.5 1.3-.5Z"/>@break
    @case('security')<path d="M6 10V8a6 6 0 0 1 12 0v2M5 10h14v11H5zM12 14v3"/>@break
    @case('orders')<path d="M6 3h12v18H6zM9 7h6M9 11h6M9 15h4"/>@break
    @case('activity')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2M7 4.5 4.5 7"/>@break
@endswitch
</svg>

<a
    @class(['customer-notification-link', 'active' => ($activeCustomerNav ?? '') === 'notifications'])
    href="{{ route('customer.notifications') }}"
    @if(($activeCustomerNav ?? '') === 'notifications') aria-current="page" @endif
    aria-label="Notifications{{ $customerOrderDecisionCount ? ' ('.$customerOrderDecisionCount.' order updates)' : '' }}"
>
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>
    </svg>
    <span>Notifications</span>
    @if($customerOrderDecisionCount)
        <b>{{ $customerOrderDecisionCount > 99 ? '99+' : $customerOrderDecisionCount }}</b>
    @endif
</a>

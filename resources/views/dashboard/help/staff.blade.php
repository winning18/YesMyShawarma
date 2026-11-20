<x-help-guide :title="__('Staff quick-start')" :summary="__('For everyone at the counter and in the kitchen at :business. Print it and keep it by the till.', ['business' => $business])">
    <section>
        <h3>{{ __('1. Sign in') }}</h3>
        <ul>
            <li>{{ __('At a counter: type your Staff ID (like ABC-0001) and your PIN, then tap') }} <b>{{ __('Open till') }}</b>.</li>
            <li>{{ __('Anywhere else: sign in with your email and password.') }}</li>
            <li>{{ __('Forgot your PIN? Ask a manager to reset it. After 5 wrong tries your Staff ID is locked for 15 minutes.') }}</li>
            <li>{{ __('The first time you use a temporary password, you choose your own.') }}</li>
        </ul>
    </section>

    <section>
        <h3>{{ __('2. Open the till') }}</h3>
        <ol>
            <li>{{ __('When you sign in, a box asks you to start your shift.') }}</li>
            <li>{{ __('Count the cash in the drawer and type it as') }} <b>{{ __('Starting cash') }}</b> {{ __('(you can leave it empty).') }}</li>
            <li>{{ __('Tap') }} <b>{{ __('Start shift') }}</b> {{ __('or') }} <b>{{ __('Open till') }}</b>. {{ __('You can now take orders.') }}</li>
        </ol>
    </section>

    <section>
        <h3>{{ __('3. Take an order') }}</h3>
        <ol>
            <li>{{ __('Open') }} <b>{{ __('POS') }}</b>.</li>
            <li>{{ __('Tap each item. If it has choices (size, sauce, extras), pick them, set the') }} <b>{{ __('Qty') }}</b>, {{ __('then tap') }} <b>{{ __('Add') }}</b>.</li>
            <li>{{ __('Check the list and the') }} <b>{{ __('Total') }}</b>. {{ __('Tap') }} <b>{{ __('Remove') }}</b> {{ __('to take something off.') }}</li>
            <li>{{ __('Choose how the customer pays:') }} <b>{{ __('Cash') }}</b>@module('bolt_food'), <b>{{ __('Momo') }}</b> {{ __('or') }} <b>{{ __('Bolt Food') }}</b>@else {{ __('or') }} <b>{{ __('Momo') }}</b>@endmodule.</li>
            <li>{{ __('For Momo, type the transaction ID from the customer\'s confirmation SMS. If it hasn\'t come yet, tap') }} <b>{{ __('Skip') }}</b> {{ __('and add it later on the order.') }}</li>
            @module('delivery')
                <li>{{ __('For a delivery, choose') }} <b>{{ __('Delivery') }}</b> {{ __('and fill in the area and a landmark. The delivery fee is set when the rider arrives.') }}</li>
            @endmodule
            <li>{{ __('Tap') }} <b>{{ __('Place order') }}</b>. {{ __('Take the money, and hand over the receipt.') }}</li>
        </ol>
        <p class="tip">{{ __('A customer phone number is optional, but with it the customer can be found again next time.') }}</p>
        @module('freebies')
            <p class="tip">{{ __('A free meal (staff meal, birthday, a make-good) is a') }} <b>{{ __('Freebie') }}</b>: {{ __('choose who it\'s for and say why. A manager approves it.') }}</p>
        @endmodule
        @module('tax')
            <p class="tip">{{ __('If the business charges VAT and levies, the POS shows them in the total. You don\'t work anything out yourself.') }}</p>
        @endmodule
    </section>

    <section>
        <h3>{{ __('4. The kitchen board') }}</h3>
        <ol>
            <li>{{ __('New orders appear under') }} <b>{{ __('Needs acknowledgement') }}</b> {{ __('with a sound.') }}</li>
            <li>{{ __('Tap') }} <b>{{ __('Accept') }}</b>. {{ __('The kitchen ticket prints. (Only') }} <b>{{ __('Reject') }}</b> {{ __('an order you truly can\'t make.)') }}</li>
            <li>{{ __('Tap') }} <b>{{ __('Start preparing') }}</b> {{ __('when cooking starts, then') }} <b>{{ __('Ready for pickup') }}</b> {{ __('when it\'s packed.') }}</li>
            <li>{{ __('When the customer collects it, tap') }} <b>{{ __('Picked up') }}</b>.</li>
            @module('delivery')
                <li>{{ __('For a delivery, assign a rider with') }} <b>{{ __('Assign to…') }}</b>. {{ __('The rider marks it delivered from their phone.') }}</li>
            @endmodule
        </ol>
    </section>

    <section>
        <h3>{{ __('5. Printing') }}</h3>
        <ul>
            <li>{{ __('Receipts print when an order is placed at the POS; kitchen tickets print when an order is accepted.') }}</li>
            <li>{{ __('Nothing came out? Open the order and tap') }} <b>{{ __('Print receipt') }}</b> {{ __('or') }} <b>{{ __('Print ticket') }}</b> {{ __('(or') }} <b>{{ __('Reprint') }}</b>).</li>
            <li>{{ __('Still nothing: check the printer has paper, is switched on and its cable is in. Then tell a manager.') }}</li>
        </ul>
    </section>

    <section>
        <h3>{{ __('6. Close the till') }}</h3>
        <ol>
            <li>{{ __('Tap') }} <b>{{ __('End shift') }}</b> {{ __('(or') }} <b>{{ __('Close till') }}</b>).</li>
            <li>{{ __('Count the money you took (not the starting cash) and type it as') }} <b>{{ __('Total sales') }}</b>. {{ __('The box shows what the system recorded; your count can\'t be lower.') }}</li>
            <li>{{ __('Add any money you paid out of the till as an expense, or tick') }} <b>{{ __('No expenses today') }}</b>.</li>
            <li>{{ __('Tap') }} <b>{{ __('Close till') }}</b>, {{ __('then sign out.') }}</li>
        </ol>
    </section>

    <section>
        <h3>{{ __('Need help?') }}</h3>
        <p>{{ __('Ask your manager first. For a problem with the system itself, your manager contacts Solusys Digital.') }}</p>
    </section>
</x-help-guide>

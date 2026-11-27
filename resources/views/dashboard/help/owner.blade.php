<x-help-guide :title="__('Owner guide')" :summary="__('Setting up :business and running it day to day. Work through the sections in order the first time.', ['business' => $business])">
    <section>
        <h3>{{ __('1. Your business') }}</h3>
        <p>{{ __('Settings →') }} <b>{{ __('Business profile') }}</b></p>
        <ul>
            <li>{{ __('Name, logo and colours: what staff and customers see, and what prints on receipts.') }}</li>
            <li>{{ __('Receipt message: a line at the bottom of every receipt, e.g. "Thank you, come again!".') }}</li>
            <li>{{ __('TIN: your GRA tax number, printed on receipts when you charge tax.') }}</li>
        </ul>
        <p>{{ __('Settings →') }} <b>{{ __('Modules') }}</b>: {{ __('switch on only what you use (delivery, market list and profit, stock, promotions…). A module that\'s off disappears completely, and its data is kept if you switch it back on.') }}</p>
    </section>

    <section>
        <h3>{{ __('2. Branches') }}</h3>
        <p><b>{{ __('Branches') }}</b>: {{ __('each branch\'s address, phone and opening hours. Use') }} <b>{{ __('Working Hours') }}</b> {{ __('for different hours on different days.') }}</p>
    </section>

    <section>
        <h3>{{ __('3. The menu') }}</h3>
        <ol>
            <li><b>{{ __('Categories') }}</b>: {{ __('the groups on the POS, e.g. Wraps, Rice, Drinks.') }}</li>
            @restaurant<li><b>{{ __('Modifiers') }}</b>: {{ __('choices like size, sauce or extras, with any extra price. Make them once, use them on many items.') }}</li>@endrestaurant
            <li><b>{{ __('Menu Editor') }}</b>: {{ __('each item\'s name, price, picture and modifiers.') }}</li>
            <li><b>{{ __('Item availability') }}</b>: {{ __('switch an item off at a branch when it runs out, and back on later.') }}</li>
        </ol>
        <p class="tip">{{ __('Prices are in cedis. If you charge tax, decide first whether your prices include it (see Tax below).') }}</p>
    </section>

    <section>
        <h3>{{ __('4. Your team') }}</h3>
        <p><b>{{ __('Users') }}</b> → <b>{{ __('Add user') }}</b>: {{ __('name, email and role at a branch. They get a temporary password and choose their own when they first sign in.') }}</p>
        <ul>
            <li><b>{{ __('Staff') }}</b>: {{ __('take orders and work the kitchen board. Each gets a Staff ID; set their') }} <b>{{ __('PIN') }}</b> {{ __('on their user page so they can sign in at a counter.') }}</li>
            <li><b>{{ __('Manager') }}</b>: {{ __('runs a branch: menu availability, staff PINs, refunds, reports.') }}</li>
            <li><b>{{ __('General manager') }}</b> / <b>{{ __('Owner') }}</b>: {{ __('every branch, settings and money reports.') }}</li>
        </ul>
    </section>

    <section>
        <h3>{{ __('5. Counters (tills)') }}</h3>
        <p>{{ __('Settings →') }} <b>{{ __('Counters') }}</b>. {{ __('With no counters a branch has one shared till. Add a counter for each till or device and each gets its own starting cash, sales and cash-up.') }}</p>
        <ol>
            <li>{{ __('Add a counter, e.g. "Counter 1".') }}</li>
            <li>{{ __('On the device that will be that till, sign in as a manager, open Counters and tap') }} <b>{{ __('Use this device as Counter 1') }}</b>.</li>
            <li>{{ __('From then on, staff sign in on that device with Staff ID and PIN.') }}</li>
        </ol>
    </section>

    <section>
        <h3>{{ __('6. Printers') }}</h3>
        <p>{{ __('Settings →') }} <b>{{ __('Printers') }}</b> {{ __('(one branch at a time).') }}</p>
        <ol>
            <li>{{ __('Add the receipt printer and the kitchen printer: network (its IP address, from the printer\'s status slip) or USB. Tick the cash drawer if one is plugged into the receipt printer.') }}</li>
            <li>{{ __('For printing without a print dialog, install the print agent on the branch\'s Windows PC:') }} <b>{{ __('Download for Windows') }}</b>, {{ __('then enter the connection code shown on the page.') }}</li>
            <li>{{ __('Tap') }} <b>{{ __('Print a test page') }}</b> {{ __('for each printer.') }}</li>
            <li>{{ __('Pick each counter\'s receipt printer on the Counters page.') }}</li>
        </ol>
    </section>

    @module('tax')
        <section>
            <h3>{{ __('7. Tax') }}</h3>
            <p>{{ __('Settings →') }} <b>{{ __('Tax') }}</b>. {{ __('List the taxes you charge and their rates, and say whether your menu prices already include them or tax is added on top. Only food is taxed, never delivery fees. Check your rates with GRA or your accountant. Changes apply to new orders only.') }}</p>
        </section>
    @endmodule

    <section>
        <h3>{{ __('Day to day') }}</h3>
        <ul>
            <li><b>{{ __('Reports and invoices') }}</b> → <b>{{ __('Sales') }}</b>: {{ __('today\'s sales by payment method and by till, and each till\'s cash-up against what it recorded.') }}</li>
            <li><b>{{ __('Invoices and sales') }}</b>: {{ __('a sales report for any dates, to download as PDF or spreadsheet.') }}</li>
            @module('market_list')
                <li><b>{{ __('Market list') }}</b>: {{ __('record purchases and approve them; then') }} <b>{{ __('Profit') }}</b> {{ __('shows sales minus what you spent.') }}</li>
            @endmodule
            @module('tax')
                <li><b>{{ __('Tax') }}</b> {{ __('report: tax charged per tax and per day, for filing.') }}</li>
            @endmodule
            <li><b>{{ __('Order History') }}</b>: {{ __('find any order, reprint its receipt.') }}</li>
            @module('refunds')
                <li><b>{{ __('Refunds') }}</b>: {{ __('staff request, a manager approves.') }}</li>
            @endmodule
        </ul>
        <p class="tip">{{ __('Sales figures are always before tax and exclude delivery fees: they\'re what the business earned.') }}</p>
    </section>
</x-help-guide>
